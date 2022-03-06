<?php

use app\models\forms\LoginForm;
use kiss\helpers\HTML;
use kiss\helpers\HTTP;
use kiss\Kiss;

/** @var LoginForm $model */

?>


<section  class="section">
    <div class="container">
        <div class="columns">
            <div class='column is-one-third'>
                <div class="card">
                    <form method="POST" class="m-0">
                        <div class="card-content">
                            <p class="title">Account</p>
                            <p class="subtitle">Signin with your Cockatrice account</p>
                            <p>
                                <?= $model->render(); ?>
                            </p>
                        </div>
                        <footer class="card-footer">
                            <button class="card-footer-item" type="submit" name="btn_recover"><span><i class="fal fa-envelope"></i> Recover</span></button>
                            <button class="card-footer-item" type="submit" name="btn_register"><span>Register</span></button>
                            <button class="card-footer-item" type="submit" name="btn_login"><span><i class="fal fa-sign-in"></i> Login</span></button>
                        </footer>
                    </form>
                </div> 
            </div>
            <div class='column is-one-third'>
                <div class="card">
                    <div class="card-content">
                        <p class="title">Third-Party</p>
                        <p class="subtitle">Login with an Authorised oAuth2 provider</p>
                            <a class="button is-fullwidth has-text-left" id="login-button" href="<?= $discordUrl ?>" data-tooltip="Login">
                                <span class="icon"><i class="fab fa-discord"></i></span>
                                <span>Discord</span>
                            </a>
                    </div>
                </div>
            </div>

        </div>
    </div>
</section>
