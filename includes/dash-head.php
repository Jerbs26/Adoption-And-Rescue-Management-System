<?php
// Keep the raw page title for topbar display (before appending app name)
$pageDisplayTitle = isset($pageTitle)
    ? html_entity_decode($pageTitle, ENT_QUOTES | ENT_HTML5, 'UTF-8')
    : '';

// Full title for <title> tag (browser tab)
$pageTitle = isset($pageTitle)
    ? html_entity_decode($pageTitle, ENT_QUOTES | ENT_HTML5, 'UTF-8') . ' — ' . APP_NAME
    : APP_NAME;
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <meta name="theme-color" content="#2d5c3e">
  <title><?= e($pageTitle) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Fraunces:wght@500;600;700;800&family=Nunito:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>/public/css/styles.css">
</head>
<body>
<div class="dashboard-wrap">