<?php

if (! defined('ABSPATH')) {
    exit;
}

class CMS_Telegram_WP_Post_Sync
{
    public static function init()
    {
        add_action('wp_after_insert_post', [self::class, 'sync_post_to_table'], 10, 4);
        add_action('wp_trash_post', [self::class, 'sync_trash_to_table']);
        add_action('untrash_post', [self::class, 'sync_untrash_to_table']);
        add_action('before_delete_post', [self::class, 'sync_delete_to_table']);
    }

    public static function sync_post_to_table($post_id, $post, $update, $fire_after_hooks)
    {

			    if (! $fire_after_hooks) {
        return;
    }
        if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
            return;
        }

        if ($post->post_type !== 'post') {
            return;
        }

        if (in_array($post->post_status, ['auto-draft', 'inherit', 'trash'], true)) {
            return;
        }

        $status = 'draft';
        if ($post->post_status === 'publish') {
            $status = 'published';
        } elseif ($post->post_status === 'draft') {
            $status = 'draft';
        }

        if (!class_exists('PostRepository')) {
            require_once CMS_TELEGRAM_PATH . 'includes/models/Post.php';
            require_once CMS_TELEGRAM_PATH . 'includes/repositories/PostRepository.php';
        }

        $repo = new PostRepository();
        $data = [
            'id'          => $post_id,
            'title'       => $post->post_title,
            'website_url' => get_permalink($post_id),
            'category_id' => self::get_mapped_category_id($post_id),
            'status'      => $status,
            'created_at'  => $post->post_date,
            'updated_at'  => $post->post_modified,
        ];

        $existing = $repo->find($post_id);

        if ($existing) {
            $postModel = new Post($data);
            $repo->update($postModel);
        } else {
            $repo->create($data);

            $new_post_model = $repo->find($post_id);
            if ($new_post_model) {
                do_action('cms_tg_post_created', $new_post_model);
            }
        }
    }

private static function get_mapped_category_id(int $post_id): ?int
{
    global $wpdb;

    $wp_categories = get_the_category($post_id);

    if (empty($wp_categories)) {
        return null;
    }

    $default_cat_id = (int) get_option('default_category');
    $fallback_id    = null;

    foreach ($wp_categories as $cat) {
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}categories WHERE name = %s LIMIT 1",
                $cat->name
            )
        );

        // Không tồn tại → insert mới vào bảng
        if (! $row) {
            $wpdb->insert(
                "{$wpdb->prefix}categories",
                [
                    'name' => $cat->name,
                    'slug' => $cat->slug,
                ],
                ['%s', '%s']
            );
            $row = (object) ['id' => $wpdb->insert_id];
        }

        // Nếu là default category thì giữ làm fallback, tiếp tục tìm cái khác
        if ((int) $cat->term_id === $default_cat_id) {
            $fallback_id = (int) $row->id;
            continue;
        }

        // Tìm thấy category không phải default → dùng luôn
        return (int) $row->id;
    }

    return $fallback_id;
}

    public static function sync_trash_to_table($post_id)
    {
        if (get_post_type($post_id) !== 'post') {
            return;
        }

        if (!class_exists('PostRepository')) {
            require_once CMS_TELEGRAM_PATH . 'includes/models/Post.php';
            require_once CMS_TELEGRAM_PATH . 'includes/repositories/PostRepository.php';
        }

        $repo = new PostRepository();
        $post_model = $repo->find($post_id);

        if ($post_model) {
            $repo->trash($post_model);
            do_action('cms_tg_post_trashed', $post_model);
        }
    }

    public static function sync_untrash_to_table($post_id)
    {
        if (get_post_type($post_id) !== 'post') {
            return;
        }

        if (!class_exists('PostRepository')) {
            require_once CMS_TELEGRAM_PATH . 'includes/models/Post.php';
            require_once CMS_TELEGRAM_PATH . 'includes/repositories/PostRepository.php';
        }

        $repo = new PostRepository();
        $post_model = $repo->find($post_id);

        if ($post_model) {
            // Trigger Telegram Notification for Restored Post
            do_action('cms_tg_post_restored', $post_model);
        }
    }

    public static function sync_delete_to_table($post_id)
    {
        if (get_post_type($post_id) !== 'post') {
            return;
        }

        if (!class_exists('PostRepository')) {
            require_once CMS_TELEGRAM_PATH . 'includes/models/Post.php';
            require_once CMS_TELEGRAM_PATH . 'includes/repositories/PostRepository.php';
        }

        $repo = new PostRepository();
        $post_model = $repo->find($post_id);

        if ($post_model) {
            do_action('cms_tg_post_force_deleted', $post_model);
            $repo->forceDelete($post_model);
        }
    }
}