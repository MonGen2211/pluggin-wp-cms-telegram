<?php

if (!defined('ABSPATH')) {
    exit;
}

class CMS_Telegram_Events
{
    public static function init()
    {
        // 1. Bài viết mới được tạo trong bảng wp_cms_tg_posts
        //    Hook vào các AJAX action của plugin (create/store)
        add_action('cms_tg_post_created', [self::class, 'on_post_created'], 10, 1);

        // 2. Bài viết chuyển vào thùng rác (soft delete)
        add_action('cms_tg_post_trashed', [self::class, 'on_post_trashed'], 10, 1);

        // 3. Xóa vĩnh viễn
        add_action('cms_tg_post_force_deleted', [self::class, 'on_post_force_deleted'], 10, 1);

        // 4. Đăng nhập WordPress
        add_action('wp_login', [self::class, 'on_user_login'], 10, 2);

        // 5. Plugin được cập nhật
        add_action('upgrader_process_complete', [self::class, 'on_plugin_updated'], 10, 2);

        // 6. Bulk actions thành công
        add_action('cms_tg_bulk_action_done', [self::class, 'on_bulk_action'], 10, 3);

        // 7. CPU spike – WP Cron mỗi 5 phút
        add_filter('cron_schedules', [self::class, 'add_cron_schedule']);
        if (!wp_next_scheduled('cms_tg_cpu_check')) {
            wp_schedule_event(time(), 'every_5_minutes', 'cms_tg_cpu_check');
        }
        add_action('cms_tg_cpu_check', [self::class, 'check_cpu_spike']);
    }

    // ─── Cron schedule ───────────────────────────────────────────────────────

    public static function add_cron_schedule($schedules)
    {
        $schedules['every_5_minutes'] = [
            'interval' => 300,
            'display'  => 'Every 5 Minutes',
        ];
        return $schedules;
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private static function now(): string
    {
        return wp_date('d/m/Y H:i:s', null, new DateTimeZone('Asia/Ho_Chi_Minh'));
    }

    private static function site(): string
    {
        return get_bloginfo('name') ?: get_site_url();
    }

    // ─── 1. Bài viết mới ─────────────────────────────────────────────────────

    public static function on_post_created($post)
    {
        if (!CMS_Telegram_Notifier::is_enabled('notify_new_post')) return;

        $title  = is_object($post) ? $post->getTitle() : ($post['title'] ?? '?');
        $status = is_object($post) ? $post->getStatus() : ($post['status'] ?? '?');
        $id     = is_object($post) ? $post->getId()    : ($post['id'] ?? '?');

        $msg = "📝 <b>Bài viết mới được tạo</b>\n"
             . "🏠 Site: <b>" . self::site() . "</b>\n"
             . "🆔 ID: <code>{$id}</code>\n"
             . "📌 Tiêu đề: <b>" . htmlspecialchars($title, ENT_QUOTES) . "</b>\n"
             . "📊 Trạng thái: <code>{$status}</code>\n"
             . "🕐 Thời gian: " . self::now();

        CMS_Telegram_Notifier::send($msg);
    }

    // ─── 2. Bài viết vào thùng rác ───────────────────────────────────────────

    public static function on_post_trashed($post)
    {
        if (!CMS_Telegram_Notifier::is_enabled('notify_trash_post')) return;

        $title = is_object($post) ? $post->getTitle() : ($post['title'] ?? '?');
        $id    = is_object($post) ? $post->getId()    : ($post['id'] ?? '?');

        $msg = "🗑️ <b>Bài viết chuyển vào thùng rác</b>\n"
             . "🏠 Site: <b>" . self::site() . "</b>\n"
             . "🆔 ID: <code>{$id}</code>\n"
             . "📌 Tiêu đề: <b>" . htmlspecialchars($title, ENT_QUOTES) . "</b>\n"
             . "🕐 Thời gian: " . self::now();

        CMS_Telegram_Notifier::send($msg);
    }

    // ─── 3. Xóa vĩnh viễn ────────────────────────────────────────────────────

    public static function on_post_force_deleted($post)
    {
        if (!CMS_Telegram_Notifier::is_enabled('notify_delete_post')) return;

        $title = is_object($post) ? $post->getTitle() : ($post['title'] ?? '?');
        $id    = is_object($post) ? $post->getId()    : ($post['id'] ?? '?');

        $msg = "❌ <b>Bài viết bị xóa vĩnh viễn</b>\n"
             . "🏠 Site: <b>" . self::site() . "</b>\n"
             . "🆔 ID: <code>{$id}</code>\n"
             . "📌 Tiêu đề: <b>" . htmlspecialchars($title, ENT_QUOTES) . "</b>\n"
             . "🕐 Thời gian: " . self::now();

        CMS_Telegram_Notifier::send($msg);
    }

    // ─── 4. Đăng nhập ────────────────────────────────────────────────────────

    public static function on_user_login($user_login, $user)
    {
        if (!CMS_Telegram_Notifier::is_enabled('notify_login')) return;

        // Lấy số người dùng đang có session hoạt động (kể cả người vừa đăng nhập)
        $threshold = (int) get_option('cms_tg_login_threshold', 2);

        // Đếm tất cả user có session_tokens trong usermeta
        $users_with_sessions = get_users([
            'meta_key'   => 'session_tokens',
            'meta_value' => '',
            'compare'    => '!=',
            'fields'     => ['ID', 'user_login'],
            'number'     => -1,
        ]);

        $active_count = 0;
        $active_list  = [];

        foreach ($users_with_sessions as $u) {
            $sessions = get_user_meta($u->ID, 'session_tokens', true);
            if (!is_array($sessions) || empty($sessions)) continue;

            // Lọc session chưa hết hạn
            $valid = array_filter($sessions, fn($s) => ($s['expiration'] ?? 0) > time());
            if (!empty($valid)) {
                $active_count++;
                $roles          = get_userdata($u->ID)->roles ?? [];
                $active_list[]  = '• <code>' . esc_html($u->user_login) . '</code> (' . implode(', ', $roles) . ')';
            }
        }

        // Chỉ gửi cảnh báo khi vượt ngưỡng
        if ($active_count <= $threshold) return;

        $ip       = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $list_str = implode("\n", $active_list);

        $msg = "⚠️ <b>Cảnh báo: Có {$active_count} người đang đăng nhập!</b>\n"
             . "🏠 Site: <b>" . self::site() . "</b>\n"
             . "👥 Ngưỡng cho phép: <b>{$threshold}</b> người\n"
             . "📋 Danh sách:\n{$list_str}\n"
             . "🌐 IP lần đăng nhập này: <code>{$ip}</code>\n"
             . "🕐 Thời gian: " . self::now();

        CMS_Telegram_Notifier::send($msg);
    }

    // ─── 5. Plugin được cập nhật ─────────────────────────────────────────────

    public static function on_plugin_updated($upgrader, $options)
    {
        if (!CMS_Telegram_Notifier::is_enabled('notify_plugin_update')) return;

        if (($options['action'] ?? '') !== 'update' || ($options['type'] ?? '') !== 'plugin') {
            return;
        }

        $plugins = $options['plugins'] ?? [];
        if (empty($plugins)) return;

        $names = [];
        foreach ($plugins as $plugin_file) {
            $plugin_data = get_plugin_data(WP_PLUGIN_DIR . '/' . $plugin_file, false, false);
            $names[]     = $plugin_data['Name'] ?? $plugin_file;
        }

        $list = implode("\n  • ", $names);

        $msg = "🔄 <b>Plugin được cập nhật</b>\n"
             . "🏠 Site: <b>" . self::site() . "</b>\n"
             . "📦 Plugin:\n  • {$list}\n"
             . "🕐 Thời gian: " . self::now();

        CMS_Telegram_Notifier::send($msg);
    }

    // ─── 6. Bulk action ──────────────────────────────────────────────────────

    /**
     * @param string $action   Tên bulk action (vd: 'trash', 'update_title')
     * @param int    $count    Số lượng bài
     * @param string $detail   Mô tả thêm (tuỳ chọn)
     */
    public static function on_bulk_action(string $action, int $count, string $detail = '')
    {
        if (!CMS_Telegram_Notifier::is_enabled('notify_bulk_action')) return;

        $user = wp_get_current_user();

        $msg = "⚡ <b>Bulk action thành công</b>\n"
             . "🏠 Site: <b>" . self::site() . "</b>\n"
             . "🔧 Thao tác: <code>" . esc_html($action) . "</code>\n"
             . "📊 Số lượng: <b>{$count}</b> bài\n"
             . ($detail ? "📝 Chi tiết: {$detail}\n" : '')
             . "👤 Thực hiện bởi: <code>" . esc_html($user->user_login ?? 'unknown') . "</code>\n"
             . "🕐 Thời gian: " . self::now();

        CMS_Telegram_Notifier::send($msg);
    }

    // ─── 7. CPU spike ────────────────────────────────────────────────────────

    public static function check_cpu_spike()
    {
        if (!CMS_Telegram_Notifier::is_enabled('notify_cpu_spike')) return;

        $threshold = floatval(get_option('cms_tg_cpu_threshold', 1.5));

        if (!function_exists('sys_getloadavg')) return;

        $load   = sys_getloadavg();
        $load1  = round($load[0], 2);
        $load5  = round($load[1], 2);
        $load15 = round($load[2], 2);

        if ($load1 < $threshold) return;

        // Tránh gửi spam: chỉ gửi lại sau 10 phút kể từ lần cuối
        $last_sent = (int) get_transient('cms_tg_cpu_spike_sent');
        if ($last_sent) return;

        set_transient('cms_tg_cpu_spike_sent', 1, 10 * MINUTE_IN_SECONDS);

        $cpu_pct = self::get_cpu_percent();
        $cpu_str = $cpu_pct !== null ? "<b>{$cpu_pct}%</b>" : 'N/A (Windows)';

        $msg = "🚨 <b>CPU tăng đột biến!</b>\n"
             . "🏠 Site: <b>" . self::site() . "</b>\n"
             . "�️ CPU sử dụng: {$cpu_str}\n"
             . "�📊 Load average:\n"
             . "  • 1 phút: <b>{$load1}</b> (ngưỡng: {$threshold})\n"
             . "  • 5 phút: <b>{$load5}</b>\n"
             . "  • 15 phút: <b>{$load15}</b>\n"
             . "🕐 Thời gian: " . self::now();

        CMS_Telegram_Notifier::send($msg);
    }

    /**
     * Tính CPU% bằng cách đọc /proc/stat 2 lần cách nhau 200ms.
     * Trả về float (0–100) hoặc null nếu không hỗ trợ (Windows).
     */
    public static function get_cpu_percent(): ?float
    {
        if (!is_readable('/proc/stat')) return null;

        $read = function () {
            $line = fgets(fopen('/proc/stat', 'r'));
            // cpu  user nice system idle iowait irq softirq
            $parts = preg_split('/\s+/', trim($line));
            return [
                'idle'  => (int) ($parts[4] ?? 0),
                'total' => array_sum(array_slice($parts, 1)),
            ];
        };

        $a = $read();
        usleep(200000); // 200ms
        $b = $read();

        $diff_idle  = $b['idle']  - $a['idle'];
        $diff_total = $b['total'] - $a['total'];

        if ($diff_total === 0) return 0.0;

        return round((1 - $diff_idle / $diff_total) * 100, 1);
    }

    /**
     * Trả về mảng thông tin CPU để hiển thị trên settings page.
     */
    public static function get_cpu_info(): array
    {
        $info = [
            'load1'   => null,
            'load5'   => null,
            'load15'  => null,
            'cpu_pct' => null,
            'cores'   => null,
        ];

        if (function_exists('sys_getloadavg')) {
            $load = sys_getloadavg();
            $info['load1']  = round($load[0], 2);
            $info['load5']  = round($load[1], 2);
            $info['load15'] = round($load[2], 2);
        }

        $info['cpu_pct'] = self::get_cpu_percent();

        // Đọc số cores từ /proc/cpuinfo
        if (is_readable('/proc/cpuinfo')) {
            $cpuinfo = file_get_contents('/proc/cpuinfo');
            $info['cores'] = substr_count($cpuinfo, 'processor');
        }

        return $info;
    }

    /**
     * Huỷ cron khi plugin bị deactivate.
     */
    public static function deactivate()
    {
        $timestamp = wp_next_scheduled('cms_tg_cpu_check');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'cms_tg_cpu_check');
        }
    }
}
