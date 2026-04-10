<?php

if (! defined('ABSPATH')) {
    exit;
}

class PostRepository
{
    private $table;

    public function __construct()
    {
        global $wpdb;
        $this->table = $wpdb->prefix . 'cms_tg_posts';
    }

    public function getAll()
    {
        global $wpdb;

        $rows = $wpdb->get_results(
            "SELECT * FROM {$this->table}
             WHERE deleted_at IS NULL
             ORDER BY id DESC",
            ARRAY_A
        );

        return array_map(fn($row) => new Post($row), $rows);
    }

    public function getTrash()
    {
        global $wpdb;

        $rows = $wpdb->get_results(
            "SELECT * FROM {$this->table}
             WHERE deleted_at IS NOT NULL
             ORDER BY deleted_at DESC, id DESC",
            ARRAY_A
        );

        return array_map(fn($row) => new Post($row), $rows);
    }

    public function find($id)
    {
        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$this->table} WHERE id = %d", $id),
            ARRAY_A
        );

        return $row ? new Post($row) : null;
    }

    public function countByStatus($status)
    {
        global $wpdb;

        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table}
                 WHERE status = %s AND deleted_at IS NULL",
                $status
            )
        );
    }

    public function countAll()
    {
        global $wpdb;

        return (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->table} WHERE deleted_at IS NULL"
        );
    }

    public function countTrash()
    {
        global $wpdb;

        return (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->table} WHERE deleted_at IS NOT NULL"
        );
    }

    public function create($data)
    {
        global $wpdb;

        $insert_data = [
            'title'       => sanitize_text_field($data['title'] ?? ''),
            'keyword'     => sanitize_text_field($data['keyword'] ?? ''),
            'website_url' => esc_url_raw($data['website_url'] ?? ''),
            'category_id' => absint($data['category_id'] ?? 0) ?: null,
            'status'      => in_array(($data['status'] ?? 'draft'), ['draft', 'published'], true)
                ? $data['status']
                : 'draft',
            'created_at'  => current_time('mysql'),
            'updated_at'  => current_time('mysql'),
            'deleted_at'  => null,
        ];
        
        $format = [
            '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s'
        ];

        if (!empty($data['id'])) {
            $insert_data['id'] = absint($data['id']);
            $format[] = '%d';
        }

        $inserted = $wpdb->insert(
            $this->table,
            $insert_data,
            $format
        );

        if ($inserted === false) {
            return false;
        }

        return !empty($data['id']) ? absint($data['id']) : (int) $wpdb->insert_id;
    }

    public function store($data)
    {
        return $this->create($data);
    }

    public function update($post)
    {
        global $wpdb;

        $wpdb->update(
            $this->table,
            [
                'title'       => sanitize_text_field($post->getTitle()),
                'keyword'     => sanitize_text_field($post->getKeyword()),
                'website_url' => esc_url_raw($post->getWebsiteUrl()),
                'category_id' => absint($post->getCategoryId()) ?: null,
                'status'      => sanitize_text_field($post->getStatus()),
                'updated_at'  => current_time('mysql'),
            ],
            ['id' => $post->getId()],
            ['%s', '%s', '%s', '%d', '%s', '%s'],
            ['%d']
        );

        return true;
    }

    public function updateId($old_id, $new_id)
    {
        global $wpdb;
        
        // Kiểm tra xem ID mới đã tôn tại chưa
        $exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$this->table} WHERE id = %d", $new_id));
        if ($exists) {
            return false;
        }

        $wpdb->update(
            $this->table,
            ['id' => absint($new_id)],
            ['id' => absint($old_id)],
            ['%d'],
            ['%d']
        );
        return true;
    }

    public function trash($post)
    {
        global $wpdb;

        $wpdb->update(
            $this->table,
            [
                'deleted_at' => current_time('mysql'),
            ],
            ['id' => $post->getId()],
            ['%s'],
            ['%d']
        );

        return true;
    }

    public function restore($post)
    {
        global $wpdb;

        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$this->table} SET deleted_at = NULL WHERE id = %d",
                $post->getId()
            )
        );

        return true;
    }

    public function forceDelete($post)
    {
        global $wpdb;

        $wpdb->delete(
            $this->table,
            ['id' => $post->getId()],
            ['%d']
        );

        return true;
    }

    public function bulkUpdateTitles(array $ids, string $mode, string $value)
    {
        global $wpdb;

        $ids = array_filter(array_map('absint', $ids));
        $ids = array_values(array_unique($ids));

        if (empty($ids)) {
            return [
                'success' => false,
                'message' => 'Không có bài viết nào được chọn.',
            ];
        }

        if (count($ids) > 100) {
            return [
                'success' => false,
                'message' => 'Chỉ được sửa tối đa 100 bài mỗi lần.',
            ];
        }

        $allowed_modes = ['prepend', 'append', 'replace'];
        if (! in_array($mode, $allowed_modes, true)) {
            return [
                'success' => false,
                'message' => 'Kiểu cập nhật không hợp lệ.',
            ];
        }

        $value = sanitize_text_field($value);

        if ($value === '') {
            return [
                'success' => false,
                'message' => 'Nội dung tiêu đề không được để trống.',
            ];
        }

        $updated_count = 0;

        foreach ($ids as $id) {
            $row = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT id, title FROM {$this->table} WHERE id = %d AND deleted_at IS NULL",
                    $id
                ),
                ARRAY_A
            );

            if (! $row) {
                continue;
            }

            $old_title = (string) $row['title'];
            $new_title = $old_title;

            switch ($mode) {
                case 'prepend':
                    $new_title = trim($value . ' ' . $old_title);
                    break;

                case 'append':
                    $new_title = trim($old_title . ' ' . $value);
                    break;

                case 'replace':
                    $new_title = $value;
                    break;
            }

            $result = $wpdb->update(
                $this->table,
                [
                    'title' => $new_title,
                ],
                ['id' => $id],
                ['%s'],
                ['%d']
            );

            if ($result !== false) {
                $updated_count++;
            }
        }

        return [
            'success' => true,
            'message' => "Đã cập nhật {$updated_count} bài viết.",
            'updated_count' => $updated_count,
        ];
    }

    public function bulkUpdateKeywords(array $items)
    {
        global $wpdb;

        if (empty($items)) {
            return [
                'success' => false,
                'message' => 'Không có dữ liệu để cập nhật keyword.',
            ];
        }

        if (count($items) > 100) {
            return [
                'success' => false,
                'message' => 'Chỉ được cập nhật tối đa 100 bài mỗi lần.',
            ];
        }

        $updated_count = 0;

        foreach ($items as $item) {
            $id      = absint($item['id'] ?? 0);
            $keyword = sanitize_text_field($item['keyword'] ?? '');

            if ($id <= 0) {
                continue;
            }

            $result = $wpdb->update(
                $this->table,
                [
                    'keyword' => $keyword,
                ],
                ['id' => $id],
                ['%s'],
                ['%d']
            );

            if ($result !== false) {
                $updated_count++;
            }
        }

        return [
            'success' => true,
            'message' => "Đã cập nhật keyword cho {$updated_count} bài viết.",
            'updated_count' => $updated_count,
        ];
    }
}
