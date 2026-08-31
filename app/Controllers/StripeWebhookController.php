<?php
namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Services\StripeService;

class StripeWebhookController {
    public function handle(Request $request): string {
        $payload = file_get_contents('php://input');
        $sigHeader = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

        if (empty($payload)) {
            http_response_code(400);
            return json_encode(['error' => 'Empty webhook payload']);
        }

        try {
            $result = StripeService::handleWebhook($payload, $sigHeader);
            http_response_code(200);
            return json_encode(['status' => 'success', 'result' => $result]);
        } catch (\Throwable $e) {
            logger("Stripe Webhook Error: " . $e->getMessage(), 'error');
            http_response_code(400);
            return json_encode(['error' => $e->getMessage()]);
        }
    }
}
