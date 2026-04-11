<?php

if (!defined('ABSPATH')) {
    exit;
}

class CMS_Telegram_Plugin_Update_Notify
{
    public static function init()
    {
        add_action('upgrader_process_complete', [self::class, 'notify_update_complete'], 10, 2);
        add_action('admin_init', [self::class, 'check_plugin_version_change']);
    }

    public static function check_plugin_version_change()
    {
        if (!class_exists('CMS_Telegram_Notifier')) {
            return;
        }

        $current_version = defined('CMS_TELEGRAM_VERSION') ? CMS_TELEGRAM_VERSION : '1.0.0';
        
        $stored_version = get_option('cms_telegram_current_version');
        
        // Cài mới lần đầu hoặc ghi đè zip file mà database chưa có log version này
        if ($stored_version === false) {
            update_option('cms_telegram_current_version', $current_version);
            
            $site_name = get_bloginfo('name');
            $message = "🔧 <b>Plugin vừa được cài đặt hoặc nâng cấp (qua upload Zip) trên website {$site_name}</b>:\n- CMS Telegram (v{$current_version})";
            CMS_Telegram_Notifier::send($message);
            return;
        }

        // Nhảy version (Ví dụ 1.0.4 -> 1.0.5)
        if (version_compare($current_version, $stored_version, '>')) {
            update_option('cms_telegram_current_version', $current_version);
            
            $site_name = get_bloginfo('name');
            $message = "🔧 <b>Plugin đã được thay đổi phiên bản trên website {$site_name}</b>:\n- CMS Telegram (v{$current_version}) (Từ v{$stored_version})";
            CMS_Telegram_Notifier::send($message);
        }
    }

    public static function notify_update_complete($upgrader_object, $options)
    {
        if (!class_exists('CMS_Telegram_Notifier')) {
            return;
        }

        $site_name = get_bloginfo('name');

        if (($options['action'] ?? '') !== 'update') {
            return;
        }

        $items_updated = [];

        if (($options['type'] ?? '') === 'plugin') {
            $type = 'Plugin';
            $plugins = $options['plugins'] ?? [];
            
            if (!function_exists('get_plugin_data')) {
                require_once ABSPATH . 'wp-admin/includes/plugin.php';
            }

            foreach ($plugins as $plugin_file) {
                $plugin_path = WP_PLUGIN_DIR . '/' . $plugin_file;
                if (file_exists($plugin_path)) {
                    $plugin_data = get_plugin_data($plugin_path);
                    $items_updated[] = $plugin_data['Name'] . ' (v' . $plugin_data['Version'] . ')';
                } else {
                    $items_updated[] = $plugin_file;
                }
            }
        } elseif (($options['type'] ?? '') === 'theme') {
            $type = 'Theme';
            $themes = $options['themes'] ?? [];
            
            foreach ($themes as $theme_slug) {
                $theme = wp_get_theme($theme_slug);
                if ($theme->exists()) {
                    $items_updated[] = $theme->get('Name') . ' (v' . $theme->get('Version') . ')';
                } else {
                    $items_updated[] = $theme_slug;
                }
            }
        } elseif (($options['type'] ?? '') === 'core') {
            $type = 'WordPress Core';
            global $wp_version;
            $items_updated[] = 'Phiên bản ' . $wp_version;
        } else {
            return;
        }

        if (empty($items_updated)) {
            return;
        }

        $list = implode("\n- ", $items_updated);
        $message = "🔧 <b>{$type} đã cập nhật trên website {$site_name}</b>:\n- {$list}";

        CMS_Telegram_Notifier::send($message);
    }
}