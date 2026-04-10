<?php

if (! defined('ABSPATH')) {
    exit;
}

class Category
{
    public $id;
    public $name;
    public $slug;
    public $description;
    public $status;
    public $created_at;
    public $updated_at;

    public function __construct($row)
    {
        $this->id          = $row['id'] ?? null;
        $this->name        = $row['name'] ?? '';
        $this->slug        = $row['slug'] ?? '';
        $this->description = $row['description'] ?? '';
        $this->status      = $row['status'] ?? 'active';
        $this->created_at  = $row['created_at'] ?? null;
        $this->updated_at  = $row['updated_at'] ?? null;
    }

    public function getId() { return $this->id; }
    public function getName() { return $this->name; }
    public function getSlug() { return $this->slug; }
    public function getDescription() { return $this->description; }
    public function getStatus() { return $this->status; }
    public function getCreatedAt() { return $this->created_at; }
    public function getUpdatedAt() { return $this->updated_at; }

    public function setName($v) { $this->name = $v; return $this; }
    public function setSlug($v) { $this->slug = $v; return $this; }
    public function setDescription($v) { $this->description = $v; return $this; }
    public function setStatus($v) { $this->status = $v; return $this; }
}
