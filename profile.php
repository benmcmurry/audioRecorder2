<?php
include_once __DIR__ . '/bootstrap.php';
include_once __DIR__ . '/auth/common.php';

$success = isset($_GET['success']) ? $_GET['success'] : '';
$error = isset($_GET['error']) ? $_GET['error'] : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile Settings</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f8fafc; margin: 0; }
        .wrap { max-width: 720px; margin: 50px auto; background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 24px; }
        h1 { color: #002e5d; margin-top: 0; }
        .note { margin: 12px 0; color: #1f2937; }
        .ok { background: #dcfce7; color: #166534; padding: 10px 12px; border-radius: 6px; }
        .err { background: #fee2e2; color: #991b1b; padding: 10px 12px; border-radius: 6px; }
        .btn { display: inline-block; margin-top: 10px; text-decoration: none; padding: 10px 14px; border-radius: 7px; border: 1px solid #cbd5e1; color: #0f172a; }
        .btn-primary { background: #002e5d; color: #fff; border-color: #002e5d; }
        .row { margin-top: 14px; }
    </style>
</head>
<body>
    <div class="wrap">
        <h1>Profile Settings</h1>
        <div class="note">Signed in as <strong><?php echo htmlspecialchars($name); ?></strong> (<?php echo htmlspecialchars($netid); ?>)</div>

        <?php if ($success !== '') { ?><div class="ok"><?php echo htmlspecialchars($success); ?></div><?php } ?>
        <?php if ($error !== '') { ?><div class="err"><?php echo htmlspecialchars($error); ?></div><?php } ?>

        <div class="row">
            <strong>Additional login options</strong><br>
            <div class="note">Google account linking is temporarily disabled while the BYU login flow is being finalized.</div>
        </div>

        <div class="row">
            <a class="btn" href="<?php echo $app_root; ?>/index.php">Back to Home</a>
        </div>
    </div>
</body>
</html>
