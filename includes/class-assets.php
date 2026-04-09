<?php

if (!defined('ABSPATH')) {
    exit;
}

class CMS_Telegram_Assets
{
    public static function init()
    {
        add_action('admin_enqueue_scripts', [self::class, 'enqueue_admin_assets']);
    }

    public static function enqueue_admin_assets($hook)
    {
        if (strpos($hook, 'cms-telegram') === false) {
            return;
        }

        wp_enqueue_style(
            'cms-telegram-admin',
            CMS_TELEGRAM_URL . 'assets/css/admin.css',
            [],
            '1.0.0'
        );

        wp_enqueue_script(
            'cms-telegram-admin',
            CMS_TELEGRAM_URL . 'assets/js/admin.js',
            [],
            '1.0.0',
            true
        );


    }
}