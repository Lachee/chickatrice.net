<?php

use kiss\controllers\Controller;
use kiss\db\Query;
use kiss\helpers\HTTP;
use kiss\Kiss;

?>

<html>
    <?= $this->renderContent('@/views/base/header', $_params_) ?>
    <body>
        <?= $this->renderContent('@/views/base/navigation') ?>
        <div class="page">
            <?= $this->renderContent('@/views/base/content', $_params_) ?>
            <?= $this->renderContent('@/views/base/footer', $_params_) ?>
        </div>
    </body>
    <?= $this->renderJsVariables(Controller::POS_END); ?>
    <?php if (KISS_DEBUG): ?>
        <?= '<pre class="referal">Referal: ' . HTTP::referral() . '</pre>' ?>
        <?php var_dump(Query::getLog()) ?>
    <?php endif; ?>
</html>