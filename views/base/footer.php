<?php

use kiss\helpers\HTTP;
?>
<footer class="footer">
    <div class="content has-text-centered">
        <div class="level">
            <div class="level-item">
                <a class="badge" href="https://github.com/lachee/chickatrice.net" target="_blank" title="Visit GitHub Repository"> <i class="fab fa-github-alt"></i> GPL-3.0 Open Source </a>
                <a class="badge" href="https://twitter.com/Lachee_" target="_blank" title="@Lachee_"> <i class="fab fa-twitter"></i> Lachee_ </a>
                <a class="badge" href="https://discord.com/users/130973321683533824" target="_blank" title="130973321683533824"> <i class="fab fa-discord"></i> Lachee</a>
                <!--<a class="badge" href="https://www.copyright.com.au/about-copyright/" target="_blank"><i class="far fa-copyright"></i> 2020</a>-->
                
            </div>
            <div class="level-item">    
                <iframe src="https://github.com/sponsors/Lachee/button" title="Sponsor Lachee" height="35" width="116" style="border: 0;"></iframe>
                <a class="badge" href="<?= HTTP::url('/jwt')?>" target="_blank"><img src="https://jwt.io/img/badge-compatible.svg"></a>
                <a class="badge" href="https://bulma.io">
                    <img src="/images/made-with-bulma.png" alt="Made with Bulma" width="128" height="24">
                </a>
            </div>
        </div>
    </div>
</footer>