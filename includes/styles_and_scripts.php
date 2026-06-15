<?php
require_once dirname(__DIR__, 2) . '/shared-ui/layout.php';

$appRoot = function_exists('ar_web_root') ? ar_web_root() : '';
?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-EVSTQN3/azprG1Anm3QDgpJLIm9Nao0Yz1ztcQTwFspd3yD65VohhpuuCOmLASjC" crossorigin="anonymous">
<link href="<?php echo shared_ui_asset_url('theme.css'); ?>" rel="stylesheet">
<link href="<?php echo htmlspecialchars($appRoot, ENT_QUOTES, 'UTF-8'); ?>/css/style.css" rel="stylesheet">
<script defer src="<?php echo shared_ui_asset_url('ui.js'); ?>"></script>
