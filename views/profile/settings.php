<?php

use app\models\Gallery;
use app\models\Image;
use app\models\Tag;
use app\widget\GalleryList;
use app\widget\ProfileCard;
use Formr\Formr;
use kiss\helpers\HTML;
use kiss\helpers\HTTP;
use kiss\models\BaseObject;

/** @var User $profile */
/** @var Form $model */

/*
$form = new Formr('bulma');
$rules = [
    'username' => ['Username', 'required|min[3]|max[16]|slug'],
    'password' => ['Password', 'required|hash']
];
$form->fastForm($rules);
*/

?>

<style>
    input[name="email"] { color: black; filter: blur(4px); transition: linear 0.25s filter; }
    /* input[name="email"]:hover { filter: blur(0px); } */
</style>

<div class="columns">
    <!-- Settings -->
    <div class="column is-half">
        <form method='POST' class='m-0'>
            <div class="card">
                <header class="card-header">
                    <p class="card-header-title">
                        <span class="icon"><i class="fal fa-user-cog"></i></span> Account Settings
                    </p>
                </header>
                <div class="card-content">
                    <div class="content">
                        <div class="columns">
                            <div class="column is-one-third">
                                <img src="<?= $profile->getAvatarUrl() ?>" alt="Ingame Avatar" width="1024" />
                            </div>
                            <div class="column">
                                <?= $model->render(); ?>
                            </div>
                        </div>
                    </div>
                </div>
                <footer class="card-footer">
                    <button class="card-footer-item" type="submit">
                        <span class="icon"><i class="fal fa-save"></i></span>
                        <span>Save</span>
                    </button>
                </footer>
            </div>
        </form>
    </div>

    <!-- Discord Link -->
    <div class="column is-half">
        <?php if ($discord != null) : ?>
            <div class="card">
                <header class="card-header">
                    <p class="card-header-title">
                        <span class="icon"><i class="fab fa-discord"></i></span> Discord
                    </p>
                </header>
                <div class="card-content">
                    <div class="content">
                        <div class="columns">
                            <div class="column is-one-third">
                                <img src="<?= $discord->getAvatarUrl() ?>.png?size=1024" alt="Discord Avatar" width="1024" />
                            </div>
                            <div class="column">
                                <p>
                                    <label class='label'>Username</label>
                                    <?= HTML::input('text', ['value' => $discord->username . '#' . $discord->discriminator, 'disabled' => true, 'class' => 'input']); ?>
                                </p>
                                <p>
                                    <label class='label'>Email</label>
                                    <?= HTML::input('text', ['value' => $discord->email, 'disabled' => true, 'class' => 'input', 'name' => 'email']); ?>
                                </p>
                                <p>
                                    <a href="<?= HTTP::url(['settings', 'sync' => true]) ?>" ><span class='icon'><i class='fal fa-sync'></i></span> Synchronise Avatar</a>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php else : ?>
            <form method='POST' class='m-0'>
                <div class="card">
                    <div class="card-content">
                        <div class="content">
                            <?= $model->render(); ?>
                        </div>
                    </div>
                    <footer class="card-footer">
                        <button class="card-footer-item" type="submit">
                            <span class="icon"><i class="fal fa-save"></i></span>
                            <span>Save</span>
                        </button>
                    </footer>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>


<hr>
<div class="field ">
    <label class="label">API Key</label>
    <div class="field has-addons is-fullwidth">
        <div class="control" style="flex: 1">
            <input class="input" name="api_key" placeholder="cooldude69" value="<?= $key ?>" type="text" readonly>
        </div>
        <div class="control"><a href="<?= HTTP::url(['settings', 'regen' => true]) ?>" class="button" type="submit">Regenerate</a></div>
    </div>
    <p class="help">Used to access the API</p>
</div>