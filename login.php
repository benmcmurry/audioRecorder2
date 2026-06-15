<?php
include_once __DIR__ . '/auth/common.php';

$appRoot = ar_web_root();
$indexUrl = $appRoot . '/index.php';
$requestedRedirect = isset($_GET['redirect']) ? shared_auth_safe_return_to((string) $_GET['redirect']) : $indexUrl;
if ($requestedRedirect === '') {
    $requestedRedirect = $indexUrl;
}

$currentUser = ar_get_session_user();
$sharedUser = shared_auth_current_session_user();
$logoutBlocked = shared_auth_logout_blocked();
if (!$logoutBlocked && ($currentUser || $sharedUser)) {
    ar_redirect($indexUrl);
}

$loginUrl = shared_auth_login_url($requestedRedirect, 'audioRecorder');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ELC Audio Recorder</title>
    <?php include_once __DIR__ . '/includes/styles_and_scripts.php'; ?>
</head>
<body>
    <?php include_once __DIR__ . '/includes/site-header.php'; ?>

    <main class="container py-5">
        <div class="row justify-content-center">
            <div class="col-12 col-md-9 col-lg-7">
                <div class="card shadow-sm">
                    <div class="card-body p-4 p-md-5">
                        <p class="text-uppercase text-muted fw-bold small mb-2">Audio recording tool</p>
                        <h1 class="h3 mb-3">Sign in to ELC Audio Recorder</h1>
                        <p class="mb-4">
                            This app lets you record prompts, review recordings, and manage prompt responses for ELC work.
                        </p>
                        <div class="d-grid gap-2">
                            <a class="btn btn-primary btn-lg" href="<?php echo htmlspecialchars($loginUrl, ENT_QUOTES, 'UTF-8'); ?>">Login with BYU</a>
                        </div>
                        <p class="text-muted small mt-4 mb-0">
                            You will be returned to the page you requested after sign-in.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <?php include_once __DIR__ . '/includes/site-footer.php'; ?>
</body>
</html>
