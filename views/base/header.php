<?php

use kiss\controllers\Controller;
use kiss\helpers\HTML;
use kiss\helpers\HTTP;
use kiss\Kiss;

$theme = HTTP::get('theme', 'lumen');
?>
<head>
    <title><?= HTML::$title ?></title>
    <base href="<?= Kiss::$app->baseURL()?>">
    
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#17181c">

    <link rel="icon" href="<?= Kiss::$app->favicon ?: Kiss::$app->logo ?>" sizes="16x16" type="image/png">

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

    <!-- LIGHTGALLERY --> 
    <script src="https://cdnjs.cloudflare.com/ajax/libs/lightgallery/1.10.0/js/lightgallery-all.min.js" integrity="sha512-Qpvw3WG46QyOqV/YS9BosbxEbMKPREA+QS+iWAKXfvb/87tdfsGGQdT7vqYbQzBOgLvF2I/MHMacA86oURHsCw==" crossorigin="anonymous"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/lightgallery/1.10.0/css/lightgallery.min.css" integrity="sha512-gk6oCFFexhboh5r/6fov3zqTCA2plJ+uIoUx941tQSFg6TNYahuvh1esZVV0kkK+i5Kl74jPmNJTTaHAovWIhw==" crossorigin="anonymous" />

    <!-- Autocomplete -->
    <link rel="stylesheet" href="https://unpkg.com/@tarekraafat/autocomplete.js@8.3.0/dist/css/autoComplete.css">
    <script src="https://unpkg.com/@tarekraafat/autocomplete.js@8.3.0/dist/js/autoComplete.min.js"></script>


    <!-- Webpacks -->
    <!--<script src="/dist/kiss/kiss.js"></script>          -->
    <!--<link rel="stylesheet" href="/dist/kiss/kiss.css">  -->
    <script src="/dist/kiss/kiss.js"></script>
    <link rel="stylesheet" href="/dist/kiss/kiss.css">

    <!-- JS Variables -->
    <?= $this->renderJsVariables(Controller::POS_HEAD); ?>

    <!-- Dependencies -->
    
    <!-- App -->
    <!-- <script src="/dist/app/app.js"></script>           -->
    <!-- <link rel="stylesheet" href="/dist/app/app.css">   -->
    <script src="/dist/app.js"></script>
    <link rel="stylesheet" href="/dist/app.css">

    <!-- JS Variables -->
    <?= $this->renderJsVariables(Controller::POS_START); ?>
</head>