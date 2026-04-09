<?php

    if (! defined('ABSPATH')) {
    exit;
    }

    $repo            = new PostRepository();
    $total_posts     = $repo->countAll();
    $draft_posts     = $repo->countByStatus('draft');
    $published_posts = $repo->countByStatus('published');
    $trash_posts     = $repo->countTrash();
    $is_logged_in    = CMS_Telegram_Auth::is_logged_in();

    cms_tg_admin_header('CMS Manager Dashboard', 'Tổng quan hệ thống quản lý bài viết');
?>

<div class="wrap cms-tg-page">

    <div class="cms-tg-grid cms-tg-grid-4">
        <div class="cms-tg-card cms-tg-stat-card">
            <span class="cms-tg-stat-label">Tổng bài viết</span>
            <strong class="cms-tg-stat-value"><?php echo esc_html($total_posts); ?></strong>
        </div>
        <div class="cms-tg-card cms-tg-stat-card">
            <span class="cms-tg-stat-label">Bản nháp</span>
            <strong class="cms-tg-stat-value"><?php echo esc_html($draft_posts); ?></strong>
        </div>
        <div class="cms-tg-card cms-tg-stat-card">
            <span class="cms-tg-stat-label">Đã xuất bản</span>
            <strong class="cms-tg-stat-value"><?php echo esc_html($published_posts); ?></strong>
        </div>
        <div class="cms-tg-card cms-tg-stat-card">
            <span class="cms-tg-stat-label">Thùng rác</span>
            <strong class="cms-tg-stat-value"><?php echo esc_html($trash_posts); ?></strong>
        </div>
    </div>

    <div class="cms-tg-grid cms-tg-grid-2" style="margin-top: 18px;">
        <div class="cms-tg-card">
            <div class="cms-tg-card-header">
                <h2>Thao tác nhanh</h2>
            </div>
            <div class="cms-tg-action-list">
                <?php if ($is_logged_in): ?>
                    <a class="button button-primary" href="<?php echo esc_url(admin_url('admin.php?page=cms-telegram-create')); ?>">
                        Thêm bài viết mới
                    </a>
                <?php endif; ?>

                <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=cms-telegram-posts')); ?>">
                    Xem danh sách bài
                </a>

                <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=cms-telegram-trash')); ?>">
                    Mở thùng rác
                </a>
            </div>
        </div>

        <div class="cms-tg-card">
            <div class="cms-tg-card-header">
                <h2>Ghi chú</h2>
            </div>
            <p class="cms-tg-muted">
                Dữ liệu đang lấy trực tiếp từ bảng <code>wp_cms_tg_posts</code> với soft delete qua cột <code>deleted_at</code>.
            </p>
        </div>
    </div>

</div>