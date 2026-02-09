<?php
include_once __DIR__ . '/common.php';

$currentUser = ar_get_session_user();
if ($currentUser) {
    ar_redirect(ar_web_root() . '/index.php');
}

$defaultRedirect = ar_web_root() . '/index.php';
$requestedRedirect = isset($_GET['redirect']) ? $_GET['redirect'] : $defaultRedirect;
$redirect = ar_safe_redirect_target($requestedRedirect, $defaultRedirect);
$error = isset($_GET['error']) ? $_GET['error'] : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign in - ELC Audio Recorder</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f6f8fb; margin: 0; }
        .wrap { max-width: 520px; margin: 70px auto; background: #fff; border: 1px solid #d9e0ea; border-radius: 10px; padding: 28px; }
        h1 { margin: 0 0 8px; font-size: 24px; color: #002e5d; }
        p { margin: 0 0 20px; color: #334155; }
        .btn { display: block; text-decoration: none; padding: 12px 16px; border-radius: 8px; margin-bottom: 12px; text-align: center; font-weight: 600; }
        .btn-cas { background: #002e5d; color: #fff; }
        .btn-google { background: #fff; color: #1f2937; border: 1px solid #d1d5db; }
        .error { background: #fee2e2; color: #991b1b; padding: 10px 12px; border-radius: 6px; margin-bottom: 14px; }
    </style>
</head>
<body>
    <div class="wrap">
        <h1>Sign in</h1>
        <p>Use BYU CAS or Google to continue.</p>
        <?php if ($error !== '') { ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php } ?>
        <a class="btn btn-cas" href="<?php echo ar_web_root(); ?>/auth/cas_start.php?redirect=<?php echo urlencode($redirect); ?>">Continue with CAS</a>
        <a class="btn btn-google" href="<?php echo ar_web_root(); ?>/auth/google_start.php?mode=login&redirect=<?php echo urlencode($redirect); ?>">Continue with Google</a>
    </div>
</body>
</html>
