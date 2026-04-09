<?php

    if (! defined('ABSPATH')) {
    exit;
    }

    $is_logged_in = CMS_Telegram_Auth::is_logged_in();
    $trash_items  = (new PostRepository())->getTrash();

    cms_tg_admin_header('Thùng rác', 'Danh sách dữ liệu đã xóa mềm');
?>

<div class="wrap cms-tg-page">

    <div class="cms-tg-card">
        <table class="wp-list-table widefat fixed striped cms-tg-table">
            <thead>
                <tr>
                    <th width="60">ID</th>
                    <th>Tiêu đề</th>
                    <th>Keyword</th>
                    <th>Website URL</th>
                    <th width="180">Deleted at</th>
                    <th width="200">Thao tác</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($trash_items)): ?>
                    <tr>
                        <td colspan="6" style="text-align:center;">Thùng rác trống.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($trash_items as $item): ?>
                        <tr>
                            <td><?php echo esc_html($item->getId()); ?></td>
                            <td><?php echo esc_html($item->getTitle()); ?></td>
                            <td><?php echo esc_html($item->getKeyword()); ?></td>
                            <td>
                                <?php if ($item->getWebsiteUrl()): ?>
                                    <a href="<?php echo esc_url($item->getWebsiteUrl()); ?>" target="_blank" rel="noopener">
                                        <?php echo esc_html($item->getWebsiteUrl()); ?>
                                    </a>
                                <?php else: ?>
                                    <span style="color:#9ca3af;">-</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html($item->getDeletedAt()); ?></td>
                            <td>
                                <div class="cms-tg-row-actions">
                                    <?php if ($is_logged_in): ?>
                                        <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=cms-telegram-trash&cms_tg_action=restore&id=' . $item->getId()), 'cms_tg_row_action')); ?>">Khôi phục</a>
                                        <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=cms-telegram-trash&cms_tg_action=delete&id=' . $item->getId()), 'cms_tg_row_action')); ?>" class="is-danger">Xóa vĩnh viễn</a>
                                    <?php else: ?>
                                        <span style="color:#9ca3af; font-size:13px;">Không có quyền</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

</div>