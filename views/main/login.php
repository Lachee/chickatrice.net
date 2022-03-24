<?php

use app\models\forms\LoginForm;
use kiss\helpers\HTML;
use kiss\helpers\HTTP;
use kiss\Kiss;

/** @var LoginForm $loginForm */
/** @var RegisterForm $registerForm */

HTML::$title = 'Login';
?>


<section  class="section">
    <div class="container">
        <div class="columns">
            <div class='column is-one-third'>
                <div class="card">
                    <form method="POST" class="m-0">
                        <div class="card-content">
                            <p class="title">Account</p>
                            <p class="subtitle">Sign-in with your Cockatrice Account</p>
                            <p>
                                <?= $loginForm->render(); ?>
                            </p>
                        </div>
                        <footer class="card-footer">
                            <button class="card-footer-item" type="submit" name="login[btn_recover]"><span><i class="fal fa-life-ring"></i> Recover</span></button>
                            <button class="card-footer-item" type="submit" name="login[btn_login]"><span><i class="fal fa-sign-in"></i> Login</span></button>
                        </footer>
                    </form>
                </div> 
            </div>
            <div class='column is-one-third'>
                <div class="card">
                    <div class="card-content">
                        <p class="title">Third-Party</p>
                        <p class="subtitle">Sign-in using your Discord Account</p>
                            <a class="button is-fullwidth has-text-left" id="login-button" href="<?= $discordUrl ?>" data-tooltip="Login">
                                <span class="icon"><i class="fab fa-discord"></i></span>
                                <span>Discord</span>
                            </a>
                    </div>
                </div>
            </div>
            <div class='column is-one-third' style="border-left: 3px solid #ffffffcf">
                <div class="card">
                    <form method="POST" class="m-0">
                        <div class="card-content">
                            <p class="title">Register</p>
                            <p class="subtitle">Register a new Cockatrice Account</p>
                            <p>By signing up using this form, you agree to let us share your email with third-party anti-spam APIs.</p><br>
                            <p>If you wish to opt-out, please use the Discord Login method.</p><br>
                            <?= $registerForm->render(); ?>
                        </div>
                        <footer class="card-footer">
                            <button class="card-footer-item" type="submit"><span><i class="fal fa-user-plus"></i> Register</span></button>
                        </footer>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>
