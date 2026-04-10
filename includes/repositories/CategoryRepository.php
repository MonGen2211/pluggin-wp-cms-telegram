<?php

if (! defined('ABSPATH')) {
    exit;
}

class CategoryRepository
{
    private $table;

    public function __construct()
    {
        global $wpdb;
        $this->table = $wpdb->prefix . 'categories';
    }

    public function getAll()
    {
        global $wpdb;
        $rows = $wpdb->get_results("SELECT * FROM {$this->table} ORDER BY id DESC", ARRAY_A);
        if (!$rows) return [];
        return array_map(fn($row) => new Category($row), $rows);
    }

    public function find($id)
    {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table} WHERE id = %d", $id), ARRAY_A);
        return $row ? new Category($row) : null;
    }

    public function create($data)
    {
        global $wpdb;
        $insert_data = [
            'name'        => sanitize_text_field($data['name'] ?? ''),
            'slug'        => sanitize_title($data['slug'] ?? ''),
            'description' => sanitize_textarea_field($data['description'] ?? ''),
            'status'      => sanitize_text_field($data['status'] ?? 'active'),
            'created_at'  => current_time('mysql'),
            'updated_at'  => current_time('mysql'),
        ];
        
        $inserted = $wpdb->insert($this->table, $insert_data, ['%s', '%s', '%s', '%s', '%s', '%s']);
        return $inserted !== false ? (int) $wpdb->insert_id : false;
    }

    public function update($category)
    {
        global $wpdb;
        $wpdb->update(
            $this->table,
            [
                'name'        => sanitize_text_field($category->getName()),
                'slug'        => sanitize_title($category->getSlug()),
                'description' => sanitize_textarea_field($category->getDescription()),
                'status'      => sanitize_text_field($category->getStatus()),
                'updated_at'  => current_time('mysql'),
            ],
            ['id' => $category->getId()],
            ['%s', '%s', '%s', '%s', '%s'],
            ['%d']
        );
        return true;
    }

    public function delete($id)
    {
        global $wpdb;
        $wpdb->delete($this->table, ['id' => $id], ['%d']);
        return true;
    }
}
