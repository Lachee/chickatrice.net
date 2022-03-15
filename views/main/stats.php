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
                <div id="users_count" class="graph"></div>
            </div>
            <div class="box is-overflow-auto">
                <div class="title">Active Games</div>
                <div class="subtitle">Number of games currently running</div>
                <div id="games_count" class="graph"></div>
            </div>
            <div class="box is-overflow-auto">
                <div class="title">Uptime</div>
                <div class="subtitle">Stablity of the server over time</div>
                <div id="uptime" class="graph"></div>
            </div>
        </div>

        <div class="column">
        <div class="box is-overflow-auto">
                <div class="title">Unique Visitors</div>
                <div class="subtitle">Number of unique visitors per day</div>
                <div id="visitor_count" class="graph"></div>
            </div>
            <div class="box is-overflow-auto">
                <div class="title">Total Games</div>
                <div class="subtitle">Number of total games per day</div>
                <div id="total_games_count" class="graph"></div>
            </div>
        </div>
    </div>
</section>