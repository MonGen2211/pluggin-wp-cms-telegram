<?php

if (!defined('ABSPATH')) {
    exit;
}

class CMS_Telegram_Login_Notify
{
    public static function init()
    {
        add_action('wp_login', [self::class, 'notify_login_success'], 10, 2);
    }

    public static function notify_login_success($user_login, $user)
    {
        if (!class_exists('CMS_Telegram_Notifier')) {
            return;
        }

        $site_name = get_bloginfo('name');
        $site_url  = home_url();
        $time      = current_time('d/m/Y H:i:s');

        $message = "✅ <b>LOGIN THÀNH CÔNG</b>\n"
            . "Website: {$site_name}\n"
            . "URL: {$site_url}\n"
            . "User: {$user_login}\n"
            . "Email: {$user->user_email}\n"
            . "Thời gian: {$time}";

        CMS_Telegram_Notifier::send($message);
    }
}