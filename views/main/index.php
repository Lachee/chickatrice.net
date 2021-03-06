<?php

use kiss\helpers\HTML;
use kiss\helpers\HTTP;
use kiss\Kiss;
?>

<section class="hero is-medium is-primary is-bold is-fullheight-with-navbar">
    <div class="hero-body">
        <div class="container">
            <div class="columns">
                <div class="column is-one-fifth"></div>
                <div class="column" id="site-heading">
                    <h1 class="title is-size-1">Chickatrice</h1>
                    <h2 class="subtitle is-size-3">Australian Cockatrice Server</h2>
                    <a class="button is-primary is-inverted is-outlined is-large" id="download-button" href="https://cockatrice.github.io/" target="_BLANK">
                        <span class="icon"><i class="fal fa-share"></i></span>
                        <span>Get Cockatrice</span>
                    </a>
                    <a class="button is-primary is-inverted is-outlined is-large" id="invite-button" target="_BLANK" href="https://discord.gg/py3Xbnv">
                        <span class="icon"><i class="fab fa-discord"></i></span>
                        <span>Join Discord</span>
                    </a>
                    <!--
                    <iframe  src="https://github.com/sponsors/Lachee/card" title="Sponsor Lachee" height="225" width="600" style="border: 0;"></iframe>
                    -->
                </div>
                <div class="column is-one-third">
                    <div class="block has-text-centered">
                        Server Time: <time id="server-time"></time>
                    </div>
                    <div class="card">
                        <div class="card-content">
                            <div class="content">
                                <canvas id="chart-uptime"></canvas>
                            </div>
                            <div class="block has-text-centered">
                                <time id="uptime"></time>
                            </div>
                        </div>
                    </div>
                    <br>
                    <div class="card">
                        <div class="card-content">
                            <div class="content">
                                <div class="field">
                                    <label class="label">Connect with Cockatrice!</label>
                                    <div class="columns">
                                        <div class="control column">
                                            <input class="input" type="text" value="mtg.chickatrice.net" readonly>
                                        </div>
                                        <div class="control column is-one-third">
                                            <input class="input" type="text" value="4748" readonly>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
</section>