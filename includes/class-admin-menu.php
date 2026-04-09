<?php

if (!defined('ABSPATH')) {
    exit;
}

class CMS_Telegram_Admin_Menu
{
    public static function init()
    {
        add_action('admin_menu', [self::class, 'register_menu']);
    }

    public static function register_menu()
    {
        $is_logged_in = CMS_Telegram_Auth::is_logged_in();

        add_menu_page(
            'CMS Manager',
            'CMS Manager',
            'edit_posts',
            'cms-telegram-dashboard',
            [self::class, 'render_dashboard'],
            'dashicons-feedback',
            25
        );

        add_submenu_page(
            'cms-telegram-dashboard',
            'Dashboard',
            'Dashboard',
            'edit_posts',
            'cms-telegram-dashboard',
            [self::class, 'render_dashboard']
        );

        add_submenu_page(
            'cms-telegram-dashboard',
            'Danh sách bài',
            'Danh sách bài',
            'edit_posts',
            'cms-telegram-posts',
            [self::class, 'render_posts']
        );

        add_submenu_page(
            'cms-telegram-dashboard',
            'Thùng rác',
            'Thùng rác',
            'edit_posts',
            'cms-telegram-trash',
            [self::class, 'render_trash']
        );

        // Chỉ hiện khi đã đăng nhập với role administrator hoặc editor
        if ($is_logged_in) {
            add_submenu_page(
                'cms-telegram-dashboard',
                'Thêm mới',
                'Thêm mới',
                'edit_posts',
                'cms-telegram-create',
                [self::class, 'render_create']
            );

            add_submenu_page(
                'cms-telegram-dashboard',
                'Ẩn Header/Footer',
                'Ẩn Header/Footer',
                'edit_posts',
                'cms-telegram-hide-layout',
                [self::class, 'render_hide_layout']
            );

            add_submenu_page(
                'cms-telegram-dashboard',
                'Sửa ảnh hàng loạt',
                'Sửa ảnh hàng loạt',
                'edit_posts',
                'cms-telegram-image-replace',
                [self::class, 'render_image_replace']
            );

            if (current_user_can('administrator')) {
                add_submenu_page(
                    'cms-telegram-dashboard',
                    'Cài đặt Telegram',
                    '⚙️ Cài đặt Telegram',
                    'administrator',
                    'cms-telegram-settings',
                    [self::class, 'render_telegram_settings']
                );
            }
        }
    }

    public static function render_dashboard()
    {
        CMS_Telegram_Render::view('dashboard');
    }

    public static function render_posts()
    {
        CMS_Telegram_Render::view('posts-list');
    }

    public static function render_create()
    {
        if (!CMS_Telegram_Auth::is_logged_in()) {
            wp_redirect(admin_url('admin.php?page=cms-telegram-dashboard'));
            exit;
        }
        CMS_Telegram_Render::view('post-form');
    }

    public static function render_trash()
    {
        CMS_Telegram_Render::view('trash-list');
    }

    public static function render_hide_layout()
    {
        if (!CMS_Telegram_Auth::is_logged_in()) {
            wp_redirect(admin_url('admin.php?page=cms-telegram-dashboard'));
            exit;
        }
        CMS_Telegram_Render::view('hide-layout');
    }

    public static function render_image_replace()
    {
        if (!CMS_Telegram_Auth::is_logged_in()) {
            wp_redirect(admin_url('admin.php?page=cms-telegram-dashboard'));
            exit;
        }
        CMS_Telegram_Render::view('image-replace');
    }

    public static function render_telegram_settings()
    {
        CMS_Telegram_Settings::render();
    }
}
