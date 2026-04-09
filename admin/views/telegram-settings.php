<?php

if (!defined('ABSPATH')) {
    exit;
}

$token     = get_option('cms_tg_bot_token', '');
$chat_id   = get_option('cms_tg_chat_id', '');
$threshold = get_option('cms_tg_cpu_threshold', 1.5);

$toggles = [
    'notify_new_post'      => '📝 Bài viết mới',
    'notify_trash_post'    => '🗑️ Chuyển vào thùng rác',
    'notify_delete_post'   => '❌ Xóa vĩnh viễn',
    'notify_login'         => '🔐 Đăng nhập',
    'notify_plugin_update' => '🔄 Cập nhật plugin',
    'notify_cpu_spike'     => '🚨 CPU tăng đột biến',
    'notify_bulk_action'   => '⚡ Bulk action',
];

cms_tg_admin_header('Cài đặt Telegram', 'Cấu hình bot token, chat ID và các loại thông báo.');
?>

<div class="wrap cms-tg-page">
    <div class="cms-tg-card" style="max-width: 720px;">
        <form method="post" action="options.php">
            <?php settings_fields('cms_tg_settings_group'); ?>

            <div class="cms-tg-card-header">
                <h2>🤖 Thông tin Bot Telegram</h2>
            </div>

            <table class="form-table" style="margin-top:0;">
                <tr>
                    <th scope="row">
                        <label for="cms_tg_bot_token">Bot Token</label>
                    </th>
                    <td>
                        <input
                            type="text"
                            id="cms_tg_bot_token"
                            name="cms_tg_bot_token"
                            value="<?php echo esc_attr($token); ?>"
                            class="regular-text"
                            placeholder="1234567890:AAXXXXXX..."
                            autocomplete="off"
                        />
                        <p class="description">Lấy token từ <a href="https://t.me/BotFather" target="_blank">@BotFather</a>.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="cms_tg_chat_id">Chat ID / Channel ID</label>
                    </th>
                    <td>
                        <input
                            type="text"
                            id="cms_tg_chat_id"
                            name="cms_tg_chat_id"
                            value="<?php echo esc_attr($chat_id); ?>"
                            class="regular-text"
                            placeholder="-100xxxxxxxxx hoặc @channel"
                        />
                        <p class="description">ID của group/channel nhận thông báo.</p>
                    </td>
                </tr>
            </table>

            <hr style="margin: 20px 0;">

            <div class="cms-tg-card-header">
                <h2>🔔 Loại thông báo</h2>
            </div>

            <table class="form-table" style="margin-top:0;">
                <?php foreach ($toggles as $key => $label) : ?>
                    <tr>
                        <th scope="row"><?php echo esc_html($label); ?></th>
                        <td>
                            <label>
                                <input
                                    type="checkbox"
                                    name="<?php echo esc_attr($key); ?>"
                                    value="1"
                                    <?php checked(1, (int) get_option($key, 1)); ?>
                                />
                                Bật thông báo
                            </label>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <tr>
                    <th scope="row">
                        <label for="cms_tg_cpu_threshold">Ngưỡng CPU Load (1 phút)</label>
                    </th>
                    <td>
                        <input
                            type="number"
                            id="cms_tg_cpu_threshold"
                            name="cms_tg_cpu_threshold"
                            value="<?php echo esc_attr(floatval($threshold)); ?>"
                            step="0.1"
                            min="0.1"
                            max="64"
                            style="width: 90px;"
                        />
                        <p class="description">
                            Thông báo khi load average 1 phút vượt ngưỡng này.
                            Mặc định: <code>1.5</code>. Kiểm tra bằng lệnh <code>uptime</code>.
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="cms_tg_login_threshold">🔐 Ngưỡng người đăng nhập đồng thời</label>
                    </th>
                    <td>
                        <input
                            type="number"
                            id="cms_tg_login_threshold"
                            name="cms_tg_login_threshold"
                            value="<?php echo esc_attr((int) get_option('cms_tg_login_threshold', 2)); ?>"
                            min="1"
                            max="100"
                            style="width: 90px;"
                        />
                        <p class="description">
                            Gửi cảnh báo khi có <strong>nhiều hơn</strong> số người này đang đăng nhập cùng lúc.
                            Mặc định: <code>2</code> (cảnh báo từ người thứ 3 trở lên).
                        </p>
                    </td>
                </tr>
            </table>

            <hr style="margin: 20px 0;">

            <?php submit_button('💾 Lưu cài đặt', 'primary', 'submit', false); ?>

            &nbsp;&nbsp;

            <?php
            // Test send button
            $test_nonce = wp_create_nonce('cms_tg_test_send');
            $test_url   = admin_url('admin.php?page=cms-telegram-settings&cms_tg_test=1&_wpnonce=' . $test_nonce);
            ?>
            <a href="<?php echo esc_url($test_url); ?>" class="button">
                📨 Gửi tin nhắn thử
            </a>
        </form>

        <?php
        // Handle test send
        if (isset($_GET['cms_tg_test']) && check_admin_referer('cms_tg_test_send')) {
            $result = CMS_Telegram_Notifier::send(
                "✅ <b>CMS Telegram hoạt động!</b>\n"
                . "🏠 Site: <b>" . get_bloginfo('name') . "</b>\n"
                . "🕐 Thời gian: " . wp_date('d/m/Y H:i:s', null, new DateTimeZone('Asia/Ho_Chi_Minh'))
            );
            if ($result) {
                echo '<div class="notice notice-success inline"><p>✅ Gửi thành công!</p></div>';
            } else {
                echo '<div class="notice notice-error inline"><p>❌ Gửi thất bại. Kiểm tra lại Bot Token và Chat ID.</p></div>';
            }
        }
        ?>
    </div>

    <?php
    // ─── CPU Status Widget ────────────────────────────────────────────────────
    $cpu = CMS_Telegram_Events::get_cpu_info();
    $threshold_val = floatval(get_option('cms_tg_cpu_threshold', 1.5));
    ?>

    <div class="cms-tg-card" style="max-width: 720px; margin-top: 18px;">
        <div class="cms-tg-card-header">
            <h2>🖥️ Trạng thái CPU hiện tại</h2>
        </div>

        <?php if ($cpu['cpu_pct'] === null && $cpu['load1'] === null) : ?>
            <p class="cms-tg-muted" style="padding: 12px 16px;">
                ⚠️ Không đọc được thông tin CPU. Server đang chạy Windows / PHP không hỗ trợ <code>sys_getloadavg()</code> hoặc <code>/proc/stat</code>.
            </p>
        <?php else : ?>
            <?php
            $pct   = $cpu['cpu_pct'];
            $bar_w = $pct !== null ? min(100, (int) $pct) : 0;
            $bar_color = $bar_w < 60 ? '#22c55e' : ($bar_w < 85 ? '#f59e0b' : '#ef4444');
            ?>
            <div style="padding: 16px;">

                <?php if ($pct !== null) : ?>
                    <div style="margin-bottom: 16px;">
                        <div style="display:flex; justify-content:space-between; margin-bottom:4px;">
                            <strong>CPU sử dụng</strong>
                            <strong style="color:<?php echo $bar_color; ?>"><?php echo $pct; ?>%</strong>
                        </div>
                        <div style="background:#e5e7eb; border-radius:8px; height:14px; overflow:hidden;">
                            <div style="width:<?php echo $bar_w; ?>%; background:<?php echo $bar_color; ?>; height:100%; border-radius:8px; transition:width .3s;"></div>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($cpu['load1'] !== null) : ?>
                    <table style="border-collapse:collapse; width:100%;">
                        <tr>
                            <td style="padding:4px 8px 4px 0; color:#6b7280;">Load avg 1 phút</td>
                            <td><strong><?php echo $cpu['load1']; ?></strong>
                                <?php if ($cpu['load1'] > $threshold_val) : ?>
                                    <span style="color:#ef4444; font-size:12px;">⚠️ vượt ngưỡng (<?php echo $threshold_val; ?>)</span>
                                <?php else: ?>
                                    <span style="color:#22c55e; font-size:12px;">✅ bình thường</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <td style="padding:4px 8px 4px 0; color:#6b7280;">Load avg 5 phút</td>
                            <td><strong><?php echo $cpu['load5']; ?></strong></td>
                        </tr>
                        <tr>
                            <td style="padding:4px 8px 4px 0; color:#6b7280;">Load avg 15 phút</td>
                            <td><strong><?php echo $cpu['load15']; ?></strong></td>
                        </tr>
                        <?php if ($cpu['cores']) : ?>
                        <tr>
                            <td style="padding:4px 8px 4px 0; color:#6b7280;">Số CPU cores</td>
                            <td><strong><?php echo $cpu['cores']; ?> cores</strong></td>
                        </tr>
                        <?php endif; ?>
                    </table>
                <?php endif; ?>

                <p class="cms-tg-muted" style="margin-top: 10px; font-size: 12px;">
                    Tải lại trang để cập nhật. Cảnh báo gửi tự động mỗi 5 phút qua WP Cron.
                </p>
            </div>
        <?php endif; ?>
    </div>

</div>
