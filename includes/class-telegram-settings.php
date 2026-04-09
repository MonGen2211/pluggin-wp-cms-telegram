<?php

if (!defined('ABSPATH')) {
    exit;
}

class CMS_Telegram_Settings
{
    public static function init()
    {
        add_action('admin_init', [self::class, 'register_settings']);
    }

    public static function register_settings()
    {
        // Bot credentials
        register_setting('cms_tg_settings_group', 'cms_tg_bot_token', [
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => '',
        ]);
        register_setting('cms_tg_settings_group', 'cms_tg_chat_id', [
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => '',
        ]);

        // Toggles
        $toggles = [
            'notify_new_post',
            'notify_trash_post',
            'notify_delete_post',
            'notify_login',
            'notify_plugin_update',
            'notify_cpu_spike',
            'notify_bulk_action',
        ];

        foreach ($toggles as $key) {
            register_setting('cms_tg_settings_group', $key, [
                'sanitize_callback' => 'absint',
                'default'           => 1,
            ]);
        }

        // CPU threshold
        register_setting('cms_tg_settings_group', 'cms_tg_cpu_threshold', [
            'sanitize_callback' => function ($val) {
                $val = floatval($val);
                return $val > 0 ? $val : 1.5;
            },
            'default' => 1.5,
        ]);

        // Login concurrent threshold
        register_setting('cms_tg_settings_group', 'cms_tg_login_threshold', [
            'sanitize_callback' => 'absint',
            'default'           => 2,
        ]);
    }

    /**
     * Render the settings page.
     */
    public static function render()
    {
        if (!current_user_can('administrator')) {
            wp_die('Bạn không có quyền truy cập trang này.');
        }
        CMS_Telegram_Render::view('telegram-settings');
    }
}
