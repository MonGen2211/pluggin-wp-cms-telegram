<?php

if (!defined('ABSPATH')) {
    exit;
}

function cms_tg_admin_header($title, $subtitle = '')
{
    ?>
    <div class="cms-tg-page-header">
        <div>
            <h1><?php echo esc_html($title); ?></h1>
            <?php if (!empty($subtitle)) : ?>
                <p><?php echo esc_html($subtitle); ?></p>
            <?php endif; ?>
        </div>
    </div>
    <?php
}