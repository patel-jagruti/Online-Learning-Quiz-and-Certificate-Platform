<?php
/**
 * QuizMaster LMS - Page Header
 * Include at the top of every public/authenticated page.
 */
require_once __DIR__ . '/../config/config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="<?= getSetting('site_description', APP_NAME) ?>">
  <title><?= isset($pageTitle) ? sanitize($pageTitle) . ' — ' . APP_NAME : APP_NAME ?></title>

  <!-- Bootstrap 5 -->
  <link href="<?= ASSET_URL ?>/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
  <!-- Font Awesome 6 -->
  <link href="<?= ASSET_URL ?>/vendor/fontawesome/css/all.min.css" rel="stylesheet">
  <!-- App stylesheet -->
  <link href="<?= ASSET_URL ?>/css/style.css" rel="stylesheet">
  <?php if (isset($extraCss)) echo $extraCss; ?>
</head>
<?php $bodyClass = str_contains(str_replace('\\', '/', $_SERVER['PHP_SELF'] ?? ''), '/admin/') ? 'admin-body' : ''; ?>
<body class="<?= $bodyClass ?>">
<?php require_once __DIR__ . '/nav.php'; ?>
<main class="main-content">
<?php showFlash(); ?>
