<?php

if (!defined('ABSPATH')) {
    exit;
}

class CMS_Telegram_Image_Replace
{
    const BATCH_SIZE = 100;

    public static function init()
    {
        add_action('admin_post_cms_tg_image_replace',      [self::class, 'handle_replace']);
    }

    public static function handle_replace()
    {
        if (!CMS_Telegram_Auth::is_logged_in()) {
            wp_die('Không có quyền.');
        }

        check_admin_referer('cms_tg_image_replace_nonce');

        $files         = $_FILES['new_image_files'] ?? [];
        $urls          = array_map('trim', (array) ($_POST['new_image_urls'] ?? []));
        $target_urls   = array_map('sanitize_text_field', (array) ($_POST['target_post_urls'] ?? [])); // Sửa lỗi esc_url_raw chặn ID

        $mapping = [];
        $count = max(count($urls), count($target_urls));
        for ($i = 0; $i < $count; $i++) {
            $target_url = $target_urls[$i] ?? '';
            if (empty($target_url)) continue;

            $post_id = 0;
            if (is_numeric($target_url)) {
                $post_id = (int) $target_url;
            } else {
                $post_id = url_to_postid($target_url);

                // FALLBACK: Nếu url_to_postid thất bại do dán link web thật (xavia.cloud) vào localhost, ta tự lấy slug tìm trong DB
                if (!$post_id) {
                    $path = parse_url($target_url, PHP_URL_PATH);
                    if ($path) {
                        $path_clean = trim($path, '/');
                        $parts = explode('/', $path_clean);
                        $slug = end($parts);
                        if ($slug) {
                            global $wpdb;
                            $found_id = $wpdb->get_var($wpdb->prepare("SELECT ID FROM {$wpdb->posts} WHERE post_name = %s LIMIT 1", $slug));
                            if ($found_id) {
                                $post_id = (int) $found_id;
                            }
                        }
                    }
                }
            }

            if (!$post_id || !get_post($post_id)) {
                $mapping[] = [
                    'post_id' => 0,
                    'debug_msg' => 'Không tìm thấy ID bài viết cho link: ' . $target_url,
                    'source' => '', // Sẽ bị báo lỗi
                ];
                continue; // Bỏ qua nếu ko tìm thấy bài
            }

            $source = '';
            $is_file = false;
            $name = '';

            if (!empty($files['tmp_name'][$i]) && $files['error'][$i] === UPLOAD_ERR_OK) {
                $source = $files['tmp_name'][$i];
                $name = $files['name'][$i];
                $is_file = true;
            } else if (!empty($urls[$i])) {
                $source = $urls[$i];
                $is_file = false;
            }

            if (empty($source)) continue;

            $mapping[] = [
                'post_id' => $post_id,
                'source'  => $source,
                'is_file' => $is_file,
                'name'    => $name
            ];
        }

        if (empty($mapping)) {
            self::redirect_with_error('missing_url');
        }

        $wp_result = self::process_replacements($mapping);

        wp_cache_flush();

        // Gửi Telegram notification
        if (class_exists('CMS_Telegram_Notifier')) {
            $msg = sprintf(
                "🔄 <b>Đổi URL ảnh hàng loạt (Upload -> Target Post)</b>\n" .
                "🔹 Đã chạy kiểm tra và thay trên: <b>%d</b> bài\n" .
                " Debug: %s",
                $wp_result['replaced'],
                $wp_result['debug']
            );
            CMS_Telegram_Notifier::send($msg);
        }

        wp_redirect(add_query_arg([
            'page'        => 'cms-telegram-image-replace',
            'done'        => 1,
            'wp_replaced' => $wp_result['replaced'],
        ], admin_url('admin.php')));

        exit;
    }


    private static function is_external_url($url)
    {
        $site_host = parse_url(home_url(), PHP_URL_HOST);
        $url_host  = parse_url($url, PHP_URL_HOST);

        return $url_host && $url_host !== $site_host;
    }

    /**
     * Upload ảnh external -> WP media
     */
    private static function sideload_image_to_wp($image_url, $post_id = 0)
    {
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $tmp = download_url($image_url);

        if (is_wp_error($tmp)) {
            error_log('[CMS_TG] Download failed: ' . $image_url . ' | ' . $tmp->get_error_message());
            return 0;
        }

        $file_array = [
            'name'     => basename(parse_url($image_url, PHP_URL_PATH)),
            'tmp_name' => $tmp,
        ];

        $attachment_id = media_handle_sideload($file_array, $post_id);

        if (is_wp_error($attachment_id)) {
            @unlink($tmp);
            error_log('[CMS_TG] Upload failed: ' . $image_url . ' | ' . $attachment_id->get_error_message());
            return 0;
        }

        return (int) $attachment_id;
    }

    /**
     * Tìm attachment ID từ URL internal của WordPress
     */
    private static function get_attachment_id_from_url($url)
    {
        if (empty($url)) {
            return 0;
        }

        $attachment_id = attachment_url_to_postid($url);

        if (!empty($attachment_id)) {
            return (int) $attachment_id;
        }

        $upload_dir = wp_get_upload_dir();

        if (empty($upload_dir['baseurl']) || empty($upload_dir['basedir'])) {
            return 0;
        }

        if (strpos($url, $upload_dir['baseurl']) === false) {
            return 0;
        }

        $file_path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $url);
        $file_path = preg_replace('/-\d+x\d+(?=\.(jpg|jpeg|png|gif|webp|avif|svg)$)/i', '', $file_path);

        $relative_path = str_replace(trailingslashit($upload_dir['basedir']), '', $file_path);

        global $wpdb;

        $attachment_id = $wpdb->get_var($wpdb->prepare(
            "SELECT post_id
             FROM {$wpdb->postmeta}
             WHERE meta_key = '_wp_attached_file'
               AND meta_value = %s
             LIMIT 1",
            $relative_path
        ));

        return $attachment_id ? (int) $attachment_id : 0;
    }

    /**
     * Upload ảnh lên API Xavia.cloud WP REST API.
     */
    private static function upload_to_api($source, $is_file = false, $filename = '')
    {
        $custom_uploaded = apply_filters('cms_tg_custom_upload_api', '', $source, $is_file, $filename);
        if (!empty($custom_uploaded)) {
            return $custom_uploaded;
        }

        $api_url = 'https://xavia.cloud/wp-json/wp/v2/media';
        
        $file_path = $source;
        if (!$is_file) {
            // Nếu URL báo đã nằm trên xavia.cloud thì dùng luôn, không tải về up ngược lại nữa (Tránh duplicate khi JS đã upload)
            if (strpos($source, 'xavia.cloud') !== false) {
                return $source;
            }

            require_once ABSPATH . 'wp-admin/includes/file.php';
            $tmp = download_url($source);
            if (is_wp_error($tmp)) {
                error_log('[CMS_TG] API Upload Error: ' . $tmp->get_error_message());
                return ['error' => $tmp->get_error_message()];
            }
            $file_path = $tmp;
            if (empty($filename)) {
                $filename = basename(parse_url($source, PHP_URL_PATH));
            }
        }

        if (empty($filename)) {
            $filename = basename($file_path);
            if (empty($filename)) $filename = 'upload_' . time() . '.jpg';
        }

        $file_content = file_get_contents($file_path);
        $mime_type = function_exists('mime_content_type') ? mime_content_type($file_path) : 'image/jpeg';
        if (empty($mime_type) || $mime_type === 'application/octet-stream') $mime_type = 'image/jpeg';

        $args = [
            'headers' => [
                'Content-Type'        => $mime_type,
                'Content-Disposition' => 'attachment; filename="' . sanitize_file_name($filename) . '"',
                'X-WP-Nonce'          => wp_create_nonce('wp_rest')
            ],
            'cookies' => $_COOKIE,
            'body'    => $file_content,
            'timeout' => 60
        ];

        // Lấy token auth nếu có setting lưu sẵn trong plugin, còn không mặc định site có allow hoặc tự add_filter hook HTTP
        $args = apply_filters('cms_tg_xavia_api_args', $args);

        $response = wp_remote_post($api_url, $args);
        
        if (!$is_file && file_exists($file_path)) {
            @unlink($file_path);
        }

        if (is_wp_error($response)) {
            return ['error' => $response->get_error_message()];
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        // Lấy về url_secure theo mong muốn user (bên trong wp/v2/media response)
        if (!empty($data['url_secure'])) {
            return $data['url_secure'];
        } elseif (!empty($data['source_url'])) { // Default feature WP media
            return $data['source_url'];
        } elseif (!empty($data['guid']['raw'])) {
            return $data['guid']['raw'];
        }

        return ['error' => 'API response: ' . $body];
    }

    /**
     * Xử lý thay thế ảnh 
     */
    private static function process_replacements(array $mapping)
    {
        $replaced = 0;
        $debug_log = [];

        foreach ($mapping as $item) {
            $post_id = $item['post_id'];
            if (!$post_id) {
                if (!empty($item['debug_msg'])) $debug_log[] = $item['debug_msg'];
                continue;
            }
            $post = get_post($post_id);
            if (!$post) {
                $debug_log[] = "ID {$post_id}: Ko thấy bài";
                continue;
            }

            // 1. Upload ảnh qua API
            $new_url = self::upload_to_api($item['source'], $item['is_file'], $item['name']);
            if (is_array($new_url) && isset($new_url['error'])) {
                $err = mb_substr($new_url['error'], 0, 100);
                $debug_log[] = "ID {$post_id}: Upload lỗi ($err)";
                continue;
            } else if (empty($new_url)) {
                $debug_log[] = "ID {$post_id}: Upload trả về rỗng";
                continue;
            }

            $current_replaced = false;

            // 2. Chỉ Thay ảnh bìa NẾU bài viết HIỆN TẠI CÓ ẢNH BÌA (Tránh việc bài nội dung lại tự gán ảnh bia)
            $current_thumb_id = get_post_thumbnail_id($post_id);
            if ($current_thumb_id) {
                $attachment_id = self::sideload_image_to_wp($new_url, $post_id);
                if ($attachment_id) {
                    update_post_meta($post_id, '_thumbnail_id', $attachment_id);
                    $current_replaced = true;
                    $debug_log[] = "ID {$post_id}: Đã đổi ảnh bìa";
                }
            } else {
                $debug_log[] = "ID {$post_id}: Bỏ qua ảnh bìa (bài chưa có)";
            }

            // 3. Thay TOÀN BỘ ảnh thẻ <img> trong bài viết bằng $new_url mới
            $new_content = $post->post_content;
            
            // Regex match src, data-src, data-lazy-src with or without quotes
            $pattern_src = '/(<img[^>]+(?:data-)?[-a-z]*src\s*=\s*)([\'"]?)([^\s\'">]+)(\2)/i';
            $replaced_content = preg_replace($pattern_src, '$1$2' . esc_url($new_url) . '$4', $new_content);

            if ($replaced_content !== $post->post_content) {
                // Xóa bỏ srcset và sizes
                $cleaned_content = preg_replace('/(<img[^>]+)\s+srcset\s*=\s*[\'"][^\'"]+[\'"]/i', '$1', $replaced_content);
                $cleaned_content = preg_replace('/(<img[^>]+)\s+sizes\s*=\s*[\'"][^\'"]+[\'"]/i', '$1', $cleaned_content);
                // Xóa class wp-image-xxx để WP KHÔNG TỰ ĐỘNG sinh ra srcset của ảnh cũ khi hiển thị ra frontend
                $cleaned_content = preg_replace('/\bwp-image-\d+\b/i', '', $cleaned_content);
                // Xóa ID bám trong Gutenberg comment để triệt để
                $cleaned_content = preg_replace('/<!-- wp:image {"id":\d+([^>]*)-->/i', '<!-- wp:image {"linkDestination":"none"$1-->', $cleaned_content);
                
                wp_update_post([
                    'ID'           => $post_id,
                    'post_content' => $cleaned_content,
                ]);
                $current_replaced = true;
                $debug_log[] = "ID {$post_id}: Đổi img content OK";
            }

            clean_post_cache($post_id);

            if ($current_replaced) {
                $replaced++;
            } else {
                $debug_log[] = "ID {$post_id}: Không tìm thấy thẻ img nào hợp lệ để đổi";
            }
        }

        return [
            'replaced' => $replaced,
            'debug' => implode(' | ', array_slice($debug_log, 0, 15)) // Hiển thị nguyên nhân rõ nhất
        ];
    }



    private static function redirect_with_error($error)
    {
        wp_redirect(add_query_arg([
            'page'  => 'cms-telegram-image-replace',
            'error' => $error,
        ], admin_url('admin.php')));
        exit;
    }

    public static function get_websites()
    {
        global $wpdb;

        $table = $wpdb->prefix . 'cms_tg_posts';

        return $wpdb->get_col("
            SELECT DISTINCT website
            FROM {$table}
            WHERE website != ''
        ");
    }
}