<?php

if (!defined('ABSPATH')) {
    exit;
}

$settings = CMS_Telegram_Hide_Layout::get_settings();

$repo = new PostRepository();
$posts = $repo->getAll();

$saved = isset($_GET['saved']);
?>

<div class="wrap">
    <h1>Ẩn Header / Footer theo bài viết</h1>

    <?php if ($saved): ?>
        <div class="notice notice-success is-dismissible">
            <p>Đã lưu cài đặt thành công!</p>
        </div>
    <?php endif; ?>

    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <?php wp_nonce_field('cms_tg_hide_layout_nonce'); ?>
        <input type="hidden" name="action" value="cms_tg_save_hide_layout">

        <h2>Chọn bài viết</h2>

        <div style="max-height: 320px; overflow: auto; padding: 12px; border: 1px solid #ddd; background: #fff;">
            <?php if (!empty($posts)): ?>
                <?php foreach ($posts as $post): ?>
                    <label style="display: block; margin-bottom: 8px;">
                        <input
                            type="checkbox"
                            name="hide_posts[]"
                            value="<?php echo esc_attr($post->getId()); ?>"
                            <?php checked(in_array($post->getId(), $settings['post_ids'], true)); ?>
                        >
                        <?php echo esc_html($post->getTitle() ?: '(Không có tiêu đề)'); ?>
                        <small style="color: #666;">- ID: <?php echo esc_html($post->getId()); ?></small>
                    </label>
                <?php endforeach; ?>
            <?php else: ?>
                <p>Chưa có bài viết nào.</p>
            <?php endif; ?>
        </div>

        <h2 style="margin-top: 24px;">Ẩn gì?</h2>

        <p>
            <label style="margin-right: 20px;">
                <input type="checkbox" name="hide_elements[]" value="header"
                    <?php checked(in_array('header', $settings['hide_elements'], true)); ?>>
                Header
            </label>

            <label>
                <input type="checkbox" name="hide_elements[]" value="footer"
                    <?php checked(in_array('footer', $settings['hide_elements'], true)); ?>>
                Footer
            </label>
        </p>

        <p class="submit">
            <button type="submit" class="button button-primary">Lưu</button>
        </p>
    </form>
</div>