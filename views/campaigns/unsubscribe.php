<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Unsubscribe Confirmation</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        body {
            background-color: #f8fafc;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
    </style>
</head>
<body>
    <div class="container" style="max-width: 520px;">
        <div class="card border-0 shadow-sm p-4 p-md-5 text-center bg-white rounded-4">
            <?php if (!empty($unsubscribed)): ?>
                <div class="mb-4">
                    <div class="d-inline-flex p-3 rounded-circle bg-success bg-opacity-10 text-success fs-1">
                        <i class="fa-solid fa-circle-check"></i>
                    </div>
                </div>
                <h4 class="fw-bold mb-2 text-dark">You have been unsubscribed</h4>
                <p class="text-muted small mb-4">
                    Your email address <strong><?= e($email) ?></strong> has been successfully added to our suppression list. You will not receive any further emails from this campaign.
                </p>
                <div class="p-3 bg-light rounded-3 small text-muted">
                    If this was done in error or you have questions, please reach out to the sender directly.
                </div>
            <?php else: ?>
                <div class="mb-4">
                    <div class="d-inline-flex p-3 rounded-circle bg-danger bg-opacity-10 text-danger fs-1">
                        <i class="fa-solid fa-triangle-exclamation"></i>
                    </div>
                </div>
                <h4 class="fw-bold mb-2 text-dark">Unsubscribe Error</h4>
                <p class="text-muted small mb-4">
                    <?= e($error ?: 'Unable to process your unsubscribe request. Please verify the link in your email.') ?>
                </p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
