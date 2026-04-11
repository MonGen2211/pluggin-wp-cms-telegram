<?php

if (!defined('ABSPATH')) {
    exit;
}

class CMS_Telegram_Auth
{
    public static function init() {}

    public static function is_logged_in()
    {
            return current_user_can('administrator') || current_user_can('editor');
    }
}