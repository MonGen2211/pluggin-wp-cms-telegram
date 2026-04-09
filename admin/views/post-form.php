<?php

if (! defined('ABSPATH')) {
    exit;
}

$edit_id = absint($_GET['edit'] ?? 0);
$view_id = absint($_GET['view'] ?? 0);
$raw_id  = absint($_GET['id'] ?? 0);

$is_view = $view_id > 0;
$id      = $edit_id ?: ($view_id ?: $raw_id);

$post = $id ? (new PostRepository())->find($id) : null;
?>


<div class="wrap cms-tg-page">
    <?php cms_tg_admin_header('Thêm / Sửa bài viết', 'Nhập thông tin theo đúng cấu trúc bảng hiện tại'); ?>

    <form method="post" action="">
        <?php wp_nonce_field('cms_tg_post_action', 'cms_tg_nonce'); ?>
        <?php if ($post): ?>
            <input type="hidden" name="id" value="<?php echo esc_attr($post->getId()); ?>">
        <?php endif; ?>

        <div class="cms-tg-editor-layout">
            <div class="cms-tg-editor-main">
                <div class="cms-tg-card">
                    <div class="cms-tg-card-header">
                        <h2>Thông tin bài viết</h2>
                        <p>Chỉ giữ các field đang có trong bảng dữ liệu.</p>
                    </div>

                    <div class="cms-tg-grid cms-tg-grid-2">
                        <div class="cms-tg-field">
                            <label for="manual_id">ID Bài viết <small>(tuỳ chọn)</small></label>
                            <input
                                id="manual_id"
                                type="number"
                                name="manual_id"
                                placeholder="Để trống để tự tăng"
                                value="<?php echo esc_attr($post?->getId()); ?>"
                                <?php echo $view_id ? 'readonly disabled' : ''; ?>
                            >
                        </div>

                        <div class="cms-tg-field">
                            <label for="title">Tiêu đề *</label>
                            <input
                                id="title"
                                type="text"
                                name="title"
                                placeholder="Nhập tiêu đề bài viết"
                                value="<?php echo esc_attr($post?->getTitle()); ?>"
                                <?php echo $is_view ? '' : 'required'; ?>
                                <?php echo $is_view ? 'disabled' : ''; ?>
                            >
                        </div>

                        <div class="cms-tg-field">
                            <label for="keyword">Keyword</label>
                            <input
                                id="keyword"
                                type="text"
                                name="keyword"
                                placeholder="Ví dụ: luật dân sự"
                                value="<?php echo esc_attr($post?->getKeyword()); ?>"
                                <?php echo $is_view ? 'disabled' : ''; ?>
                            >
                        </div>

                        <div class="cms-tg-field cms-tg-field-full">
                            <label for="website_url">Website URL</label>
                            <input
                                id="website_url"
                                type="url"
                                name="website_url"
                                placeholder="https://domain.com"
                                value="<?php echo esc_attr($post?->getWebsiteUrl()); ?>"
                                <?php echo $is_view ? 'disabled' : ''; ?>
                            >
                        </div>
                    </div>
                </div>

                <div class="cms-tg-card">
                    <div class="cms-tg-card-header">
                        <h2>Thông tin hệ thống</h2>
                        <p>Trạng thái và dữ liệu đọc từ DB.</p>
                    </div>

                    <div class="cms-tg-grid cms-tg-grid-2">
                        <div class="cms-tg-field">
                            <label for="status">Trạng thái</label>
                            <select id="status" name="status" <?php echo $is_view ? 'disabled' : ''; ?>>
                                <option value="draft" <?php selected($post?->getStatus(), 'draft'); ?>>Draft</option>
                                <option value="published" <?php selected($post?->getStatus(), 'published'); ?>>Published</option>
                            </select>
                        </div>

                        <div class="cms-tg-field"></div>

                        <div class="cms-tg-field">
                            <label>Created at</label>
                            <input
                                type="text"
                                value="<?php echo $post ? esc_attr($post->getCreatedAt()) : '-'; ?>"
                                readonly
                            >
                        </div>

                        <div class="cms-tg-field">
                            <label>Updated at</label>
                            <input
                                type="text"
                                value="<?php echo $post ? esc_attr($post->getUpdatedAt()) : '-'; ?>"
                                readonly
                            >
                        </div>
                    </div>
                </div>
            </div>

            <div class="cms-tg-editor-side">
                <div class="cms-tg-card cms-tg-sticky">
                    <div class="cms-tg-card-header">
                        <h2><?php echo $is_view ? 'Xem bài viết' : 'Xuất bản'; ?></h2>
                        <p>
                            <?php echo $is_view
                                ? 'Chế độ chỉ xem, các trường đã bị khóa.'
                                : 'Lưu dữ liệu theo cấu trúc bảng tối giản.'; ?>
                        </p>
                    </div>

                    <div class="cms-tg-side-actions">
                        <?php if (! $is_view): ?>
                            <button type="submit" class="button button-primary cms-tg-btn">Lưu dữ liệu</button>
                        <?php endif; ?>

                        <a class="button cms-tg-btn" href="<?php echo esc_url(admin_url('admin.php?page=cms-telegram-posts')); ?>">
                            Quay lại danh sách
                        </a>
                    </div>
                </div>

                <div class="cms-tg-card">
                    <div class="cms-tg-card-header">
                        <h2>Thông tin nhanh</h2>
                        <p>Khối phụ hiển thị trạng thái hiện tại.</p>
                    </div>

                    <div class="cms-tg-meta-list">
                        <div class="cms-tg-meta-item">
                            <span>Người thao tác</span>
                            <strong><?php echo esc_html(wp_get_current_user()->display_name); ?></strong>
                        </div>
                        <div class="cms-tg-meta-item">
                            <span>Trạng thái hiện tại</span>
                            <strong><?php echo $post ? esc_html($post->getStatus()) : 'draft'; ?></strong>
                        </div>
                        <div class="cms-tg-meta-item">
                            <span>Soft delete</span>
                            <strong><?php echo $post && $post->getDeletedAt() ? 'Đã xóa mềm' : 'Đang hoạt động'; ?></strong>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>