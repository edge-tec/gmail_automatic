<?php
namespace App\Services;

use App\Models\EmailCampaignRecipient;
use App\Models\EmailCampaign;
use Exception;
use ZipArchive;
use XMLReader;

class RecipientImportService {

    /**
     * Import recipients from a file (.txt, .csv, .xlsx) into an EmailCampaign
     * 
     * @param int $campaignId
     * @param int $userId
     * @param string $filePath Absolute path to uploaded file
     * @param string $originalExtension File extension
     * @return array [total_rows, valid_emails, invalid_emails, duplicates, imported]
     */
    public function importFile(int $campaignId, int $userId, string $filePath, string $originalExtension): array {
        $ext = strtolower(trim($originalExtension, '. '));

        $stats = [
            'total_rows' => 0,
            'valid_emails' => 0,
            'invalid_emails' => 0,
            'duplicates' => 0,
            'imported' => 0,
        ];

        // Ensure campaign exists and belongs to user
        $campaign = EmailCampaign::findByUserAndId($userId, $campaignId);
        if (!$campaign) {
            throw new Exception("Campaign #{$campaignId} not found or unauthorized");
        }

        // To track duplicates across the import efficiently without storing huge arrays in memory,
        // we keep a set of hash prefixes or clean emails for deduplication.
        $seenEmails = [];
        $batch = [];
        $batchSize = 250;

        $rowHandler = function(array $row) use (&$stats, &$seenEmails, &$batch, $batchSize, $campaignId, $userId) {
            $stats['total_rows']++;

            $rawEmail = trim($row['email'] ?? '');
            if (empty($rawEmail)) {
                $stats['invalid_emails']++;
                return;
            }

            // Normalization
            $email = strtolower($rawEmail);
            $email = filter_var($email, FILTER_SANITIZE_EMAIL);

            // RFC 5322 validation
            if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254 || !str_contains($email, '.')) {
                $stats['invalid_emails']++;
                return;
            }

            // Intra-file deduplication
            if (isset($seenEmails[$email])) {
                $stats['duplicates']++;
                return;
            }

            // Check if already in campaign in DB
            $existing = EmailCampaignRecipient::findByCampaignAndEmail($campaignId, $email);
            if ($existing) {
                $seenEmails[$email] = true;
                $stats['duplicates']++;
                return;
            }

            $seenEmails[$email] = true;
            $stats['valid_emails']++;

            $batch[] = [
                'email' => $email,
                'first_name' => $row['first_name'] ?? null,
                'last_name' => $row['last_name'] ?? null,
                'company' => $row['company'] ?? null,
                'custom_field_1' => $row['custom_field_1'] ?? null,
                'custom_field_2' => $row['custom_field_2'] ?? null,
                'custom_data' => $row['custom_data'] ?? null,
            ];

            if (count($batch) >= $batchSize) {
                $inserted = EmailCampaignRecipient::insertBatch($campaignId, $userId, $batch);
                $stats['imported'] += $inserted;
                $batch = [];
            }
        };

        if ($ext === 'txt') {
            $this->parseTxt($filePath, $rowHandler);
        } elseif ($ext === 'csv') {
            $this->parseCsv($filePath, $rowHandler);
        } elseif ($ext === 'xlsx') {
            $this->parseXlsx($filePath, $rowHandler);
        } else {
            throw new Exception("Unsupported file format: .{$ext}. Allowed formats: .txt, .csv, .xlsx");
        }

        // Flush remaining batch
        if (!empty($batch)) {
            $inserted = EmailCampaignRecipient::insertBatch($campaignId, $userId, $batch);
            $stats['imported'] += $inserted;
            $batch = [];
        }

        // Update campaign total recipients
        $campaign->recalculateStats();

        return $stats;
    }

    /**
     * Stream TXT files line by line
     */
    private function parseTxt(string $filePath, callable $rowHandler): void {
        $handle = fopen($filePath, 'r');
        if (!$handle) {
            throw new Exception("Unable to open text file for reading");
        }

        try {
            while (($line = fgets($handle)) !== false) {
                $trimmed = trim($line);
                if ($trimmed === '') continue;

                // Support "Name <email@example.com>" or "email@example.com" or "email, name"
                $email = '';
                $name = '';

                if (preg_match('/(.*)<(.+@.+?)>/', $trimmed, $m)) {
                    $name = trim(trim($m[1]), '"\'');
                    $email = trim($m[2]);
                } elseif (str_contains($trimmed, ',')) {
                    $parts = array_map('trim', explode(',', $trimmed, 2));
                    if (filter_var($parts[0], FILTER_VALIDATE_EMAIL)) {
                        $email = $parts[0];
                        $name = $parts[1] ?? '';
                    } elseif (isset($parts[1]) && filter_var($parts[1], FILTER_VALIDATE_EMAIL)) {
                        $email = $parts[1];
                        $name = $parts[0];
                    } else {
                        $email = $parts[0];
                    }
                } else {
                    $email = $trimmed;
                }

                $firstName = '';
                $lastName = '';
                if ($name) {
                    $np = explode(' ', $name, 2);
                    $firstName = $np[0];
                    $lastName = $np[1] ?? '';
                }

                $rowHandler([
                    'email' => $email,
                    'first_name' => $firstName ?: null,
                    'last_name' => $lastName ?: null,
                ]);
            }
        } finally {
            fclose($handle);
        }
    }

    /**
     * Stream CSV files row by row
     */
    private function parseCsv(string $filePath, callable $rowHandler): void {
        $handle = fopen($filePath, 'r');
        if (!$handle) {
            throw new Exception("Unable to open CSV file for reading");
        }

        try {
            // Detect delimiter (, ; \t)
            $firstLine = fgets($handle);
            if ($firstLine === false) {
                return;
            }
            $delimiter = ',';
            if (substr_count($firstLine, ';') > substr_count($firstLine, ',')) {
                $delimiter = ';';
            } elseif (substr_count($firstLine, "\t") > substr_count($firstLine, ',')) {
                $delimiter = "\t";
            }

            // Rewind to beginning
            rewind($handle);

            // Read header row
            $header = fgetcsv($handle, 4096, $delimiter, '"', "\\");
            if ($header === false) {
                return;
            }

            // Normalize header names
            $headerMap = [];
            $hasEmailHeader = false;
            foreach ($header as $colIdx => $colName) {
                $clean = strtolower(trim($colName));
                $clean = preg_replace('/[^a-z0-9_]/', '', str_replace([' ', '-'], '_', $clean));
                $headerMap[$colIdx] = $clean;
                if ($clean === 'email' || $clean === 'email_address' || $clean === 'e_mail') {
                    $hasEmailHeader = true;
                }
            }

            // If first row was actually an email without headers, rewind and process as data
            if (!$hasEmailHeader && filter_var(trim($header[0] ?? ''), FILTER_VALIDATE_EMAIL)) {
                rewind($handle);
                $headerMap = [0 => 'email', 1 => 'first_name', 2 => 'last_name', 3 => 'company'];
            }

            while (($row = fgetcsv($handle, 4096, $delimiter, '"', "\\")) !== false) {
                if (empty($row) || (count($row) === 1 && $row[0] === null)) continue;

                $rowData = [
                    'email' => '',
                    'first_name' => null,
                    'last_name' => null,
                    'company' => null,
                    'custom_field_1' => null,
                    'custom_field_2' => null,
                ];

                $customData = [];

                foreach ($row as $idx => $val) {
                    $field = $headerMap[$idx] ?? "col_{$idx}";
                    $val = trim($val ?? '');

                    if (in_array($field, ['email', 'email_address', 'e_mail'])) {
                        $rowData['email'] = $val;
                    } elseif (in_array($field, ['first_name', 'firstname', 'first', 'fname', 'name'])) {
                        $rowData['first_name'] = $val;
                    } elseif (in_array($field, ['last_name', 'lastname', 'last', 'lname', 'surname'])) {
                        $rowData['last_name'] = $val;
                    } elseif (in_array($field, ['company', 'organization', 'org', 'company_name'])) {
                        $rowData['company'] = $val;
                    } elseif ($field === 'custom_field_1') {
                        $rowData['custom_field_1'] = $val;
                    } elseif ($field === 'custom_field_2') {
                        $rowData['custom_field_2'] = $val;
                    } else {
                        if ($val !== '') {
                            $customData[$field] = $val;
                        }
                    }
                }

                // If no email header matched, try column 0
                if (empty($rowData['email']) && isset($row[0])) {
                    $rowData['email'] = trim($row[0]);
                }

                if (!empty($customData)) {
                    $rowData['custom_data'] = $customData;
                }

                $rowHandler($rowData);
            }
        } finally {
            fclose($handle);
        }
    }

    /**
     * Stream XLSX files row by row using PHP's native ZipArchive and XMLReader
     */
    private function parseXlsx(string $filePath, callable $rowHandler): void {
        $zip = new ZipArchive();
        if ($zip->open($filePath) !== true) {
            throw new Exception("Unable to open XLSX file archive");
        }

        $tempDir = storage_path('temp/xlsx_' . uniqid());
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0775, true);
        }

        try {
            // Extract sharedStrings.xml if present
            $sharedStrings = [];
            $sharedIndex = $zip->locateName('xl/sharedStrings.xml');
            if ($sharedIndex !== false) {
                $zip->extractTo($tempDir, 'xl/sharedStrings.xml');
                $ssFile = $tempDir . '/xl/sharedStrings.xml';
                if (file_exists($ssFile)) {
                    $reader = new XMLReader();
                    if ($reader->open($ssFile)) {
                        while ($reader->read()) {
                            if ($reader->nodeType == XMLReader::ELEMENT && $reader->name == 'si') {
                                $node = $reader->readOuterXml();
                                $text = strip_tags($node);
                                $sharedStrings[] = html_entity_decode($text, ENT_QUOTES | ENT_XML1, 'UTF-8');
                            }
                        }
                        $reader->close();
                    }
                }
            }

            // Extract sheet1.xml
            $sheetIndex = $zip->locateName('xl/worksheets/sheet1.xml');
            if ($sheetIndex === false) {
                // Try finding any worksheet
                for ($i = 0; $i < $zip->numFiles; $i++) {
                    $stat = $zip->statIndex($i);
                    if (str_starts_with($stat['name'], 'xl/worksheets/sheet')) {
                        $sheetIndex = $i;
                        break;
                    }
                }
            }

            if ($sheetIndex === false) {
                throw new Exception("No worksheet found in XLSX file");
            }

            $sheetName = $zip->getNameIndex($sheetIndex);
            $zip->extractTo($tempDir, $sheetName);
            $sheetFile = $tempDir . '/' . $sheetName;

            $reader = new XMLReader();
            if (!$reader->open($sheetFile)) {
                throw new Exception("Failed to open worksheet XML reader");
            }

            $isFirstRow = true;
            $headerMap = [];
            $hasEmailHeader = false;

            while ($reader->read()) {
                if ($reader->nodeType == XMLReader::ELEMENT && $reader->name == 'row') {
                    $rowXml = $reader->readOuterXml();
                    $rowDoc = simplexml_load_string($rowXml);
                    if ($rowDoc === false) continue;

                    $cols = [];
                    foreach ($rowDoc->c as $c) {
                        $cellRef = (string)$c['r'];
                        // Calculate 0-based column index from cell reference (e.g. A1, B1, AA1)
                        $colLetters = preg_replace('/[0-9]/', '', $cellRef);
                        $colIdx = 0;
                        for ($i = 0; $i < strlen($colLetters); $i++) {
                            $colIdx = $colIdx * 26 + (ord(strtoupper($colLetters[$i])) - ord('A') + 1);
                        }
                        $colIdx -= 1;

                        $type = (string)$c['t'];
                        $val = (string)$c->v;

                        if ($type === 's' && isset($sharedStrings[(int)$val])) {
                            $cellVal = $sharedStrings[(int)$val];
                        } elseif ($type === 'inlineStr' && isset($c->is->t)) {
                            $cellVal = (string)$c->is->t;
                        } else {
                            $cellVal = $val;
                        }

                        $cols[$colIdx] = trim($cellVal);
                    }

                    if (empty($cols)) continue;

                    if ($isFirstRow) {
                        $isFirstRow = false;
                        foreach ($cols as $idx => $name) {
                            $clean = strtolower(trim($name));
                            $clean = preg_replace('/[^a-z0-9_]/', '', str_replace([' ', '-'], '_', $clean));
                            $headerMap[$idx] = $clean;
                            if (in_array($clean, ['email', 'email_address', 'e_mail'])) {
                                $hasEmailHeader = true;
                            }
                        }

                        // If first row is already an email, treat as data
                        if (!$hasEmailHeader && filter_var(trim($cols[0] ?? ''), FILTER_VALIDATE_EMAIL)) {
                            $headerMap = [0 => 'email', 1 => 'first_name', 2 => 'last_name', 3 => 'company'];
                            // process this row as data
                        } else {
                            continue; // Move to next row
                        }
                    }

                    $rowData = [
                        'email' => '',
                        'first_name' => null,
                        'last_name' => null,
                        'company' => null,
                        'custom_field_1' => null,
                        'custom_field_2' => null,
                    ];
                    $customData = [];

                    foreach ($cols as $idx => $val) {
                        $field = $headerMap[$idx] ?? "col_{$idx}";
                        if (in_array($field, ['email', 'email_address', 'e_mail'])) {
                            $rowData['email'] = $val;
                        } elseif (in_array($field, ['first_name', 'firstname', 'first', 'fname', 'name'])) {
                            $rowData['first_name'] = $val;
                        } elseif (in_array($field, ['last_name', 'lastname', 'last', 'lname', 'surname'])) {
                            $rowData['last_name'] = $val;
                        } elseif (in_array($field, ['company', 'organization', 'org'])) {
                            $rowData['company'] = $val;
                        } elseif ($field === 'custom_field_1') {
                            $rowData['custom_field_1'] = $val;
                        } elseif ($field === 'custom_field_2') {
                            $rowData['custom_field_2'] = $val;
                        } else {
                            if ($val !== '') {
                                $customData[$field] = $val;
                            }
                        }
                    }

                    if (empty($rowData['email']) && isset($cols[0])) {
                        $rowData['email'] = $cols[0];
                    }

                    if (!empty($customData)) {
                        $rowData['custom_data'] = $customData;
                    }

                    $rowHandler($rowData);
                }
            }

            $reader->close();

        } finally {
            $zip->close();
            // Cleanup temp directory
            $this->deleteDirectory($tempDir);
        }
    }

    private function deleteDirectory(string $dir): void {
        if (!is_dir($dir)) return;
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = "$dir/$file";
            is_dir($path) ? $this->deleteDirectory($path) : @unlink($path);
        }
        @rmdir($dir);
    }
}
