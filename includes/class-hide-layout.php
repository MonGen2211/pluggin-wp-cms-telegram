<?php

if (!defined('ABSPATH')) {
    exit;
}

class CMS_Telegram_Hide_Layout {

    public static function init(): void {
        add_action('wp_head', [self::class, 'maybe_hide_layout'], 99);
        add_action('admin_post_cms_tg_save_hide_layout', [self::class, 'save_settings']);
    }

    public static function get_settings(): array {
        return [
            'post_ids'      => array_map('intval', (array) get_option('cms_tg_hide_post_ids', [])),
            'hide_elements' => (array) get_option('cms_tg_hide_elements', []),
        ];
    }

    public static function save_settings(): void {
        check_admin_referer('cms_tg_hide_layout_nonce');

        $post_ids = isset($_POST['hide_posts'])
            ? array_map('intval', $_POST['hide_posts'])
            : [];

        $hide_elements = isset($_POST['hide_elements'])
            ? array_values(array_intersect($_POST['hide_elements'], ['header', 'footer']))
            : [];

        update_option('cms_tg_hide_post_ids', $post_ids);
        update_option('cms_tg_hide_elements', $hide_elements);

        wp_redirect(add_query_arg('saved', '1', wp_get_referer()));
        exit;
    }

    public static function maybe_hide_layout(): void {
        if (!is_singular()) {
            return;
        }

        $settings = self::get_settings();
        $current_id = (int) get_queried_object_id();

        // DEBUG - xóa sau khi fix
        if (current_user_can('administrator')) {
            echo '<script>console.log("CMS Debug:", ' . json_encode([
                'current_id' => $current_id,
                'post_ids'   => $settings['post_ids'],
                'elements'   => $settings['hide_elements'],
                'match'      => in_array($current_id, $settings['post_ids'], true),
            ]) . ');</script>';
        }

        if (!in_array($current_id, $settings['post_ids'], true)) {
            return;
        }

        $css_rules = [];

        if (in_array('header', $settings['hide_elements'], true)) {
            $css_rules[] = '.wp-site-blocks header { display: none !important; }';
        }

        if (in_array('footer', $settings['hide_elements'], true)) {
            $css_rules[] = '.wp-site-blocks footer { display: none !important; }';
        }

        if (empty($css_rules)) {
            return;
        }

        echo '<style id="cms-tg-hide-layout">' . implode("\n", $css_rules) . '</style>';
    }
}