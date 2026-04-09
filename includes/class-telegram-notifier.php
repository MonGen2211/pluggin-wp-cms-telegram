<?php

if (!defined('ABSPATH')) {
    exit;
}

class CMS_Telegram_Notifier
{
    /**
     * Đọc file .env trong thư mục plugin và trả về mảng key => value.
     */
    private static function load_env(): array
    {
        static $cache = null;
        if ($cache !== null) return $cache;

        $env_file = CMS_TELEGRAM_PATH . '.env';
        $cache    = [];

        if (!is_readable($env_file)) return $cache;

        foreach (file($env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) continue;
            if (!str_contains($line, '=')) continue;
            [$key, $value] = explode('=', $line, 2);
            $cache[trim($key)] = trim($value);
        }

        return $cache;
    }

    /**
     * Lấy giá trị từ .env trước, nếu không có thì lấy từ WP options.
     */
    private static function get(string $env_key, string $option_key): string
    {
        $env = self::load_env();
        return $env[$env_key] ?? get_option($option_key, '');
    }

    /**
     * Gửi tin nhắn đến Telegram.
     */
    public static function send(string $message): bool
    {
        $token   = self::get('CMS_TG_BOT_TOKEN', 'cms_tg_bot_token');
        $chat_id = self::get('CMS_TG_CHAT_ID', 'cms_tg_chat_id');

        if (empty($token) || empty($chat_id)) {
            return false;
        }

        $url  = "https://api.telegram.org/bot{$token}/sendMessage";
        $body = [
            'chat_id'    => $chat_id,
            'text'       => $message,
            'parse_mode' => 'HTML',
        ];

        $response = wp_remote_post($url, [
            'timeout' => 10,
            'body'    => $body,
        ]);

        if (is_wp_error($response)) {
            error_log('[CMS Telegram] Gửi thất bại: ' . $response->get_error_message());
            return false;
        }

        $code = wp_remote_retrieve_response_code($response);
        return $code === 200;
    }

    public static function is_enabled(string $key): bool
    {
        return (bool) get_option($key, 1);
    }
}
