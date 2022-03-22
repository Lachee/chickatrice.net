<?php

use kiss\helpers\HTML;
use kiss\helpers\HTTP;
use kiss\Kiss;


?>

<section class="hero is-medium is-dark is-bold is-fullheight-with-navbar">

    <div class="hero-body">
        <div class="container">
            <div class="columns">
                <div class="column"></div>
                <div class="column is-one-third">
                    <div id="webatrice-status" class="title">Connecting</div>
                    <progress class="progress is-small is-primary" max="100">15%</progress>
                </div>
                <div class="column"></div>
            </div>
        </div>
    </div>


    <iframe id="webatrice" style="width: 100%; height: 100%; position: absolute;" />
</section>