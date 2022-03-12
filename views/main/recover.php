<?php

use app\models\forms\LoginForm;
use app\models\forms\RecoverForm;
use kiss\helpers\HTML;
use kiss\helpers\HTTP;
use kiss\Kiss;

/** @var RecoverForm $form */

?>


<section  class="section">
    <div class="container">
        <div class="columns">
            <div class='column is-one-third'></div>
            <div class='column'>
                <div class="card">
                    <form method="POST" class="m-0">
                        <div class="card-content">
                            <p class="title">Recover Account</p>
                            <p class="subtitle">Set a new password for <code><?= HTML::encode($form->username) ?></code></p>
                            <p>
                                <?= $form->render(); ?>
                            </p>
                        </div>
                        <footer class="card-footer">
                            <button class="card-footer-item" type="submit"><span><i class="fal fa-life-ring"></i> Recover</span></button>
                        </footer>
                    </form>
                </div> 
            </div>
            <div class='column is-one-third'></div>
        </div>
    </div>
</section>
