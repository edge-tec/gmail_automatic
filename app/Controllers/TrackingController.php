<?php
namespace App\Controllers;

use App\Core\Request;
use App\Services\EmailOpenTrackingService;

class TrackingController {

    // Transparent 1x1 8-bit GIF binary (43 bytes)
    private const PIXEL_GIF = "GIF89a\x01\x00\x01\x00\x80\x00\x00\xff\xff\xff\x00\x00\x00!\xf9\x04\x01\x00\x00\x00\x00,\x00\x00\x00\x00\x01\x00\x01\x00\x00\x02\x02D\x01\x00;";

    /**
     * Handle public email open tracking pixel request
     * GET /tracking/open/{token}
     */
    public function trackOpen(Request $request, string $token): string {
        // Collect request headers and metadata safely
        $headers = [];
        $serverVars = array_merge($_SERVER, $request->all());
        foreach ($serverVars as $k => $v) {
            if (is_string($k) && str_starts_with($k, 'HTTP_')) {
                $headerName = strtolower(str_replace('_', '-', substr($k, 5)));
                $headers[$headerName] = (string)$v;
            }
        }

        $ua = $request->server('HTTP_USER_AGENT') ?? $_SERVER['HTTP_USER_AGENT'] ?? '';
        $requestMeta = [
            'ip' => $request->ip(),
            'user_agent' => $ua,
            'headers' => $headers,
        ];

        // Safely record open event in background
        try {
            EmailOpenTrackingService::recordOpen($token, $requestMeta);
        } catch (\Throwable $e) {
            error_log("TrackingController error recording open: " . $e->getMessage());
        }

        // Return valid transparent 1x1 image with anti-caching headers
        if (!headers_sent()) {
            header('Content-Type: image/gif');
            header('Cache-Control: no-cache, no-store, must-revalidate, max-age=0, private');
            header('Pragma: no-cache');
            header('Expires: Fri, 01 Jan 1990 00:00:00 GMT');
            header('Content-Length: ' . strlen(self::PIXEL_GIF));
        }

        echo self::PIXEL_GIF;
        return self::PIXEL_GIF;
    }

    public function track(Request $request, string $token): string {
        return $this->trackOpen($request, $token);
    }
}
