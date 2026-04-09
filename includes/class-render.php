<?php

if (! defined('ABSPATH')) {
    exit;
}

class CMS_Telegram_Render
{
    public static function view($view, $data = [])
    {
        $file = CMS_TELEGRAM_PATH . 'admin/views/' . $view . '.php';

        if (! file_exists($file)) {
            echo '<div class="wrap"><h1>View not found: ' . esc_html($view) . '</h1></div>';
            return;
        }

        if (! empty($data)) {
            extract($data, EXTR_SKIP);
        }

        include $file;
    }
}
