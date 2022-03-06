
<?php

use kiss\controllers\Controller;
use kiss\helpers\HTML;
use app\widget\Breadcrumb;
use app\widget\Menu;
use app\widget\Notification;
use kiss\helpers\HTTP;

$fullwidth = isset($fullwidth) ? $fullwidth : true;
?>

<div class="<?= !isset($wrapContents) || $wrapContents === true ? 'page-contents' : ''; ?>">
    <?php if (isset($fullWidth) && $fullWidth === true): ?>
        <?= Notification::widget(); ?>
        <?= $_VIEW; ?>
    <?php else: ?>
        <div class="container <?= $fullwidth ? 'is-fluid' : '' ?>">
            <div class="columns"> 
                <?php if (Breadcrumb::count() > 0): ?>
                        <div class="column is-3 ">
                            <?= Menu::widget(); ?>
                        </div>
                        <div class="column is-9">
                            <?= Breadcrumb::widget(); ?>
                            <?= Notification::widget(); ?>
                            <?= $_VIEW; ?>
                        </div>
                <?php else: ?>
                    <div class="column is-12">
                        <?= Notification::widget(); ?>
                        <?= $_VIEW; ?>
                    </div>
                <?php endif;?>
            </div>
        </div>
    <?php endif; ?>
</div>