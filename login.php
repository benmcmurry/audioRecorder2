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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-EVSTQN3/azprG1Anm3QDgpJLIm9Nao0Yz1ztcQTwFspd3yD65VohhpuuCOmLASjC" crossorigin="anonymous">
    <link href="css/style.css" rel="stylesheet">
</head>
<body class="bg-light">
    <header id="header" class="p-2 bg-byu-navy text-white">
        <div class="container">
            <div class="d-flex flex-wrap align-items-center justify-content-between">
                <div id="title">
                    <a href="<?php echo htmlspecialchars($indexUrl, ENT_QUOTES, 'UTF-8'); ?>">ELC Audio Recorder</a>
                </div>
                <div class="text-end small">Public login</div>
            </div>
        </div>
    </header>

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

    <footer class="p-2 bg-byu-navy text-white fixed-bottom">
        <div class="container-fluid">
            <div class="d-flex flex-wrap align-items-center justify-content-around">
                <div class="text-center small">
                    <div>Developed by Ben McMurry</div>
                    <div>
                        <a href="https://elc.byu.edu">English Language Center</a>, <a href="https://www.byu.edu">BYU</a>
                    </div>
                </div>
            </div>
        </div>
    </footer>
</body>
</html>
