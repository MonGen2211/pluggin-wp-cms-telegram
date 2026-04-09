<?php
/**
 * Plugin Name: CMS Telegram
 * Description: CMS manager UI for posts dashboard.
 * Version: 1.0.0
 * Author: Vu
 */

if (!defined('ABSPATH')) {
    exit;
}

define('CMS_TELEGRAM_PATH', plugin_dir_path(__FILE__));
define('CMS_TELEGRAM_URL', plugin_dir_url(__FILE__));

require_once CMS_TELEGRAM_PATH . 'includes/helpers.php';
require_once CMS_TELEGRAM_PATH . 'includes/class-assets.php';
require_once CMS_TELEGRAM_PATH . 'includes/class-render.php';
require_once CMS_TELEGRAM_PATH . 'includes/class-auth.php';
require_once CMS_TELEGRAM_PATH . 'includes/class-hide-layout.php';
require_once CMS_TELEGRAM_PATH . 'includes/class-image-replace.php';
require_once CMS_TELEGRAM_PATH . 'includes/class-admin-menu.php';

require_once CMS_TELEGRAM_PATH . 'includes/models/Post.php';
require_once CMS_TELEGRAM_PATH . 'includes/repositories/PostRepository.php';
require_once CMS_TELEGRAM_PATH . 'includes/controllers/PostController.php';

// Telegram notification system
require_once CMS_TELEGRAM_PATH . 'includes/class-telegram-notifier.php';
require_once CMS_TELEGRAM_PATH . 'includes/class-telegram-settings.php';
require_once CMS_TELEGRAM_PATH . 'includes/class-telegram-events.php';

add_action('plugins_loaded', function () {
    CMS_Telegram_Auth::init();
    CMS_Telegram_Assets::init();
    CMS_Telegram_Hide_Layout::init();
    CMS_Telegram_Image_Replace::init();
    CMS_Telegram_Admin_Menu::init();
    CMS_Telegram_Settings::init();
    CMS_Telegram_Events::init();
    PostController::register();
});

// Clean up cron job on deactivation
register_deactivation_hook(__FILE__, ['CMS_Telegram_Events', 'deactivate']);

// ── Kiểm soát Heartbeat API để tránh gọi admin-ajax.php liên tục ────────────
add_filter('heartbeat_settings', function ($settings) {
    // Tắt hoàn toàn heartbeat trên các trang của plugin (không cần autosave)
    $page = $_GET['page'] ?? '';
    if (strpos($page, 'cms-telegram') !== false) {
        $settings['interval'] = 0; // Hệ số 0 = tắt
        return $settings;
    }
    // Các trang admin khác: giảm xuống 60 giây thay vì mặc định 15-60s
    $settings['interval'] = 60;
    return $settings;
});

add_action('admin_enqueue_scripts', function ($hook) {
    // Tắt hẳn heartbeat script trên các trang của plugin
    $page = $_GET['page'] ?? '';
    if (strpos($page, 'cms-telegram') !== false) {
        wp_deregister_script('heartbeat');
    }
}, 1);