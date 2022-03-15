<?php

use kiss\controllers\Controller;
use kiss\helpers\HTML;
use kiss\helpers\HTTP;
use kiss\helpers\Strings;
use kiss\Kiss;

$theme = HTTP::get('theme', 'lumen');
?>
<head>
    <title><?= HTML::encode(HTML::$title) ?></title>
    <base href="<?= Kiss::$app->baseURL()?>">
    
    <!-- Meta Data -->
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="<?= Kiss::$app->themeColor; ?>">

    <?= HTML::openGraphMeta(); ?>

    <?php if (Strings::endsWith(Kiss::$app->favicon ?: '', '.ico')): ?>
        <link rel="icon" href="<?= Kiss::$app->favicon ?>" type="image/x-icon">
    <?php else: ?>
        <link rel="icon" href="<?= Kiss::$app->favicon ?: Kiss::$app->logo ?>" sizes="128x128" type="image/png">
    <?php endif; ?>

    <!-- JQuery -->
    <script
        src="https://code.jquery.com/jquery-3.4.1.min.js"
        integrity="sha256-CSXorXvZcTkaix6Yvo6HppcZGetbYMGWSFlBw8HfCJo="
        crossorigin="anonymous"></script>

    <!-- Bulma Version 0.8.x--> 
    <!--<link rel="stylesheet" href="https://unpkg.com/bulma@0.8.0/css/bulma.min.css" />-->
    <!--https://jenil.github.io/bulmaswatch/-->
    <!--<?php if (!empty($theme)) ?> <link rel="stylesheet" href="https://unpkg.com/bulmaswatch/<?= $theme ?>/bulmaswatch.min.css">-->

    <!-- JSON Form -->
    <script src="https://cdn.jsdelivr.net/npm/@json-editor/json-editor@latest/dist/jsoneditor.min.js"></script>

    <!-- SELECT 2 -->
    <script src="https://cdn.jsdelivr.net/npm/select2@4.0.13/dist/js/select2.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.0.13/dist/css/select2.min.css">

    <!-- Autocomplete -->
    <link rel="stylesheet" href="https://unpkg.com/@tarekraafat/autocomplete.js@8.3.0/dist/css/autoComplete.css">
    <script src="https://unpkg.com/@tarekraafat/autocomplete.js@8.3.0/dist/js/autoComplete.min.js"></script>

    <!-- Webpacks -->
    <script src="/dist/kiss/kiss.js"></script>
    <link rel="stylesheet" href="/dist/kiss/kiss.css">

    <!-- JS Variables -->
    <?= $this->renderJsVariables(Controller::POS_HEAD); ?>

    <!-- Dependencies -->
    <?= $this->renderDependencies(); ?>
    
    <!-- JS Variables -->
    <?= $this->renderJsVariables(Controller::POS_START); ?>
</head>