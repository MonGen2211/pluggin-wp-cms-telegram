<?php

if (!defined('ABSPATH')) {
    exit;
}

$settings = CMS_Telegram_Hide_Layout::get_settings();

$repo = new PostRepository();
$posts = $repo->getAll();

$saved = isset($_GET['saved']);

$categories = (new CategoryRepository())->getAll();
$category_map = [];
foreach ($categories as $cat) {
    if ($cat->getId()) {
        $category_map[$cat->getId()] = $cat->getName();
    }
}

$groupedPosts = [];
if (!empty($posts)) {
    foreach ($posts as $post) {
        $catId = $post->getCategoryId() ? $post->getCategoryId() : 'Chưa phân loại';
        $groupedPosts[$catId][] = $post;
    }
}
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

        <div style="max-height: 500px; overflow: auto; padding: 12px; border: 1px solid #ddd; background: #fff; border-radius: 4px;">
            <?php if (!empty($groupedPosts)): ?>
                <?php foreach ($groupedPosts as $catId => $catPosts): ?>
                    <?php
                        if ($catId === 'Chưa phân loại') {
                            $catName = 'Chưa phân loại';
                        } else {
                            $catName = isset($category_map[$catId]) ? $category_map[$catId] : 'ID Mất quyền (' . $catId . ')';
                        }
                    ?>
                    <div class="cms-tg-category-group" style="margin-bottom: 20px;">
                        <div style="background: #f0f0f1; padding: 8px 12px; font-weight: bold; margin-bottom: 8px; border-radius: 4px; display: flex; align-items: center; justify-content: space-between;">
                            <span>Danh mục: <?php echo esc_html($catName); ?> (<?php echo count($catPosts); ?> bài viết)</span>
                            <label style="font-weight: normal; font-size: 13px; cursor: pointer;">
                                <input type="checkbox" class="cat-select-all" data-category="<?php echo esc_attr($catId); ?>"> Chọn tất cả
                            </label>
                        </div>
                        <div class="cms-tg-category-items" style="padding-left: 10px;">
                            <?php foreach ($catPosts as $post): ?>
                                <label style="display: block; margin-bottom: 8px;">
                                    <input
                                        type="checkbox"
                                        class="post-item-checkbox"
                                        data-category="<?php echo esc_attr($catId); ?>"
                                        name="hide_posts[]"
                                        value="<?php echo esc_attr($post->getId()); ?>"
                                        <?php checked(in_array($post->getId(), $settings['post_ids'], true)); ?>
                                    >
                                    <?php echo esc_html($post->getTitle() ?: '(Không có tiêu đề)'); ?>
                                    <small style="color: #666;">- ID: <?php echo esc_html($post->getId()); ?></small>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p>Chưa có bài viết nào.</p>
            <?php endif; ?>
        </div>
        
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const selectAllCheckboxes = document.querySelectorAll('.cat-select-all');
                
                selectAllCheckboxes.forEach(function(selectAllCheckbox) {
                    const catId = selectAllCheckbox.getAttribute('data-category');
                    // Avoid CSS syntax errors by using attribute selector
                    const checkboxesForCat = document.querySelectorAll('.post-item-checkbox[data-category="' + catId.replace(/"/g, '\\"') + '"]');
                    
                    // Initial state for select all
                    const allChecked = Array.from(checkboxesForCat).every(cb => cb.checked);
                    const someChecked = Array.from(checkboxesForCat).some(cb => cb.checked);
                    if (checkboxesForCat.length > 0) {
                        selectAllCheckbox.checked = allChecked;
                        selectAllCheckbox.indeterminate = !allChecked && someChecked;
                    }
                    
                    // When select all is changed
                    selectAllCheckbox.addEventListener('change', function() {
                        const isChecked = this.checked;
                        checkboxesForCat.forEach(function(cb) {
                            cb.checked = isChecked;
                        });
                    });
                    
                    // When individual checkboxes are changed
                    checkboxesForCat.forEach(function(cb) {
                        cb.addEventListener('change', function() {
                            const allChecked = Array.from(checkboxesForCat).every(c => c.checked);
                            const someChecked = Array.from(checkboxesForCat).some(c => c.checked);
                            selectAllCheckbox.checked = allChecked;
                            selectAllCheckbox.indeterminate = !allChecked && someChecked;
                        });
                    });
                });
            });
        </script>

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