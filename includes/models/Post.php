<?php

if (! defined('ABSPATH')) {
    exit;
}

class Post
{
    public $id;
    public $title;
    public $keyword;
    public $website_url;
    public $status;
    public $created_at;
    public $deleted_at;
    public $updated_at;

    public function __construct($row)
    {
        $this->id          = $row['id'] ?? null;
        $this->title       = $row['title'] ?? '';
        $this->keyword     = $row['keyword'] ?? null;
        $this->website_url = $row['website_url'] ?? null;
        $this->status      = $row['status'] ?? 'draft';
        $this->created_at  = $row['created_at'] ?? null;
        $this->deleted_at  = $row['deleted_at'] ?? null;
        $this->updated_at  = $row['updated_at'] ?? null;
    }

    public function getId()
    {
        return $this->id;
    }

    public function getTitle()
    {
        return $this->title;
    }

    public function getKeyword()
    {
        return $this->keyword;
    }

    public function getWebsiteUrl()
    {
        return $this->website_url;
    }

    public function getStatus()
    {
        return $this->status;
    }

    public function getCreatedAt()
    {
        return $this->created_at;
    }

    public function getDeletedAt()
    {
        return $this->deleted_at;
    }

    public function setTitle($v)
    {
        $this->title = $v;
        return $this;
    }

    public function setKeyword($v)
    {
        $this->keyword = $v;
        return $this;
    }

    public function setWebsiteUrl($v)
    {
        $this->website_url = $v;
        return $this;
    }

    public function setStatus($v)
    {
        $this->status = $v;
        return $this;
    }

    public function setDeletedAt($v)
    {
        $this->deleted_at = $v;
        return $this;
    }

    public function getUpdatedAt()
    {
        return $this->updated_at;
    }

    public function setUpdatedAt($v)
    {
        $this->updated_at = $v;
        return $this;
    }
}
