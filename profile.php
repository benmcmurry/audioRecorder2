<?php
include_once __DIR__ . '/bootstrap.php';
include_once __DIR__ . '/auth/common.php';

$success = isset($_GET['success']) ? $_GET['success'] : '';
$error = isset($_GET['error']) ? $_GET['error'] : '';
$appRoot = ar_web_root();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Profile Settings</title>
    <?php include_once __DIR__ . '/includes/styles_and_scripts.php'; ?>
</head>
<body>
    <?php include_once __DIR__ . '/includes/site-header.php'; ?>

    <main class="container py-5">
        <div class="row justify-content-center">
            <div class="col-12 col-lg-8">
                <section class="card shadow-sm">
                    <div class="card-body p-4 p-md-5">
                        <p class="text-uppercase text-muted fw-bold small mb-2">Account</p>
                        <h1 class="h3 mb-3">Profile Settings</h1>
                        <div class="note">Signed in as <strong><?php echo htmlspecialchars($name); ?></strong> (<?php echo htmlspecialchars($netid); ?>)</div>

                        <?php if ($success !== '') { ?><div class="alert alert-success mt-3 mb-0"><?php echo htmlspecialchars($success); ?></div><?php } ?>
                        <?php if ($error !== '') { ?><div class="alert alert-danger mt-3 mb-0"><?php echo htmlspecialchars($error); ?></div><?php } ?>

                        <div class="row mt-4">
                            <div class="col-12">
                                <strong>Additional login options</strong>
                                <div class="note mt-2">Google account linking is temporarily disabled while the BYU login flow is being finalized.</div>
                            </div>
                        </div>

                        <div class="row mt-4">
                            <div class="col-12">
                                <a class="btn btn-primary" href="<?php echo $appRoot; ?>/index.php">Back to Home</a>
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </main>

    <?php include_once __DIR__ . '/includes/site-footer.php'; ?>
</body>
</html>
