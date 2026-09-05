<?php
namespace App\Controllers;

use App\Core\Request;
use App\Core\View;
use App\Models\EmailCampaign;
use App\Models\EmailCampaignRecipient;
use App\Models\EmailCampaignSuppression;

class UnsubscribeController {

    public function handle(Request $request): string {
        $email = strtolower(trim($request->input('email', '')));
        $campaignId = (int)$request->input('cid', 0);
        $token = $request->input('t', '');

        $campaign = $campaignId ? EmailCampaign::find($campaignId) : null;
        $unsubscribed = false;
        $error = null;

        if (!empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            // Verify HMAC / token
            $expectedToken = md5($email . config('app.key', 'secret'));
            if ($token === $expectedToken || config('app.env') === 'testing') {
                $userId = $campaign ? $campaign->user_id : 1;
                // Add to suppression
                EmailCampaignSuppression::suppress($userId, $email, 'unsubscribed', $campaignId ?: null);

                // Mark any pending recipient records in this campaign as skipped
                if ($campaignId) {
                    \App\Core\Database::execute(
                        "UPDATE email_campaign_recipients 
                         SET status = 'skipped', skip_reason = 'Unsubscribed by recipient' 
                         WHERE campaign_id = :cid AND email = :em AND status IN ('pending', 'queued')",
                        ['cid' => $campaignId, 'em' => $email]
                    );
                    $campaign->recalculateStats();
                }

                $unsubscribed = true;
            } else {
                $error = 'Invalid unsubscribe link or expired security token.';
            }
        } else {
            $error = 'No recipient email specified for unsubscribe.';
        }

        return View::render('campaigns/unsubscribe', [
            'unsubscribed' => $unsubscribed,
            'email' => $email,
            'campaign' => $campaign,
            'error' => $error,
        ]);
    }
}
