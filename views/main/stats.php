<?php

use kiss\helpers\HTML;
use kiss\helpers\HTTP;
use kiss\Kiss;
?>

<section class="section container">
    <div class="columns">
    <div class="column">
            <div class="box is-overflow-auto">
                <div class="title">Users Online</div>
                <div class="subtitle">Number of users online at a given moment</div>
                <div id="users_count" style="width:100%"></div>
            </div>
            <div class="box is-overflow-auto">
                <div class="title">Active Games</div>
                <div class="subtitle">Number of games currently running</div>
                <div id="games_count" style="width:100%"></div>
            </div>
            <div class="box is-overflow-auto">
                <div class="title">Uptime</div>
                <div class="subtitle">Stablity of the server over time</div>
                <div id="uptime" style="width:100%"></div>
            </div>
        </div>

        <div class="column">
            <div class="box is-overflow-auto">
                <div class="title">Unique Visitors</div>
                <div class="subtitle">Number of unique visitors per day</div>
                <div id="visitor_count" style="width:100%"></div>
            </div>
        </div>
    </div>
</section>