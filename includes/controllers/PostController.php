<?php

if (! defined('ABSPATH')) exit;

class PostController
{
	public static function register()
	{
		add_action('admin_init', [self::class, 'handleForm']);
		add_action('admin_init', [self::class, 'handleActions']);
		add_action('wp_ajax_cms_tg_bulk_update_titles', [self::class, 'handleBulkUpdateTitles']);
		add_action('wp_ajax_cms_tg_bulk_update_keywords', [self::class, 'handleBulkUpdateKeywords']);
	}

	public static function handleForm()
	{
		if (
			$_SERVER['REQUEST_METHOD'] !== 'POST' ||
			($_GET['page'] ?? '') !== 'cms-telegram-create'
		) {
			return;
		}

		check_admin_referer('cms_tg_post_action', 'cms_tg_nonce');

		$repo = new PostRepository();
		$id = absint($_POST['id'] ?? 0);

		$status = sanitize_text_field($_POST['status'] ?? 'draft');
		$allowed_statuses = ['draft', 'published'];

		if (! in_array($status, $allowed_statuses, true)) {
			$status = 'draft';
		}

		if ($id > 0) {
			$post = $repo->find($id);

			if (! $post) {
				wp_safe_redirect(admin_url('admin.php?page=cms-telegram-posts'));
				exit;
			}

			// Nếu user có nhập manual_id mới và khác với ID cũ -> Đổi ID
			$manual_id = absint($_POST['manual_id'] ?? 0);
			if ($manual_id > 0 && $manual_id !== $id) {
				if ($repo->updateId($id, $manual_id)) {
					// Cập nhật lại ID cho object $post để khúc update() ở dưới update đúng chỗ mới
					// Hàm set id không tồn tại trong Post object ? Do class Post không định nghĩa setId
					// Ta cứ update_id ở db, rồi lấy lại $post từ db
					$post = $repo->find($manual_id);
					$id = $manual_id;
				}
			}

			if ($post) {
				$post->setTitle($_POST['title'] ?? '')
					->setKeyword($_POST['keyword'] ?? '')
					->setWebsiteUrl($_POST['website_url'] ?? '')
					->setStatus($status);

				$repo->update($post);
			}
		} else {
			$manual_id = absint($_POST['manual_id'] ?? 0);
			$create_data = [
				'title'       => $_POST['title'] ?? '',
				'keyword'     => $_POST['keyword'] ?? '',
				'website_url' => $_POST['website_url'] ?? '',
				'status'      => $status,
			];
			if ($manual_id > 0) {
				$create_data['id'] = $manual_id;
			}
			$new_id = $repo->create($create_data);

			if ($new_id) {
				$new_post = $repo->find($new_id);
				do_action('cms_tg_post_created', $new_post);
			}
		}

		wp_safe_redirect(admin_url('admin.php?page=cms-telegram-posts'));
		exit;
	}

	public static function handleActions()
	{
		$action = sanitize_text_field($_GET['cms_tg_action'] ?? '');

		if ($action === '') {
			return;
		}

		if (! current_user_can('edit_posts')) {
			return;
		}

		check_admin_referer('cms_tg_row_action');

		$id = absint($_GET['id'] ?? 0);
		if ($id <= 0) {
			return;
		}

		$repo = new PostRepository();
		$post = $repo->find($id);

		if (! $post) {
			return;
		}

		if ($action === 'trash') {
			$repo->trash($post);
			do_action('cms_tg_post_trashed', $post);
			wp_safe_redirect(admin_url('admin.php?page=cms-telegram-posts'));
			exit;
		}

		if ($action === 'restore') {
			$repo->restore($post);
			wp_safe_redirect(admin_url('admin.php?page=cms-telegram-trash'));
			exit;
		}

		if ($action === 'delete') {
			do_action('cms_tg_post_force_deleted', $post);
			$repo->forceDelete($post);
			wp_safe_redirect(admin_url('admin.php?page=cms-telegram-trash'));
			exit;
		}
	}

	public static function handleBulkUpdateTitles()
	{
		if (!current_user_can('edit_posts')) {
			wp_send_json_error([
				'message' => 'Bạn không có quyền thực hiện thao tác này.',
			], 403);
		}

		check_ajax_referer('cms_tg_bulk_title_action', 'nonce');

		$ids   = json_decode(stripslashes($_POST['ids'] ?? '[]'), true);
		$mode  = sanitize_text_field($_POST['mode'] ?? '');
		$value = sanitize_text_field($_POST['value'] ?? '');

		if (!is_array($ids)) {
			wp_send_json_error([
				'message' => 'Danh sách bài viết không hợp lệ.',
			], 400);
		}

		$repo = new PostRepository();
		$result = $repo->bulkUpdateTitles($ids, $mode, $value);

		if (!$result['success']) {
			wp_send_json_error([
				'message' => $result['message'],
			], 400);
		}

		do_action('cms_tg_bulk_action_done', 'Cập nhật tiêu đề (' . $mode . ')', $result['updated_count'] ?? 0, 'Giá trị: ' . $value);
		wp_send_json_success([
			'message' => $result['message'],
			'updated_count' => $result['updated_count'] ?? 0,
		]);
	}

	public static function handleBulkUpdateKeywords()
	{
		if (!current_user_can('edit_posts')) {
			wp_send_json_error([
				'message' => 'Bạn không có quyền thực hiện thao tác này.',
			], 403);
		}

		check_ajax_referer('cms_tg_bulk_keyword_action', 'nonce');

		$items = json_decode(stripslashes($_POST['items'] ?? '[]'), true);

		if (!is_array($items)) {
			wp_send_json_error([
				'message' => 'Dữ liệu keyword không hợp lệ.',
			], 400);
		}

		if (count($items) > 100) {
			wp_send_json_error([
				'message' => 'Chỉ được cập nhật tối đa 100 bài mỗi lần.',
			], 400);
		}

		$repo = new PostRepository();
		$result = $repo->bulkUpdateKeywords($items);

		if (!$result['success']) {
			wp_send_json_error([
				'message' => $result['message'],
			], 400);
		}

		do_action('cms_tg_bulk_action_done', 'Cập nhật keyword', $result['updated_count'] ?? 0, '');
		wp_send_json_success([
			'message' => $result['message'],
			'updated_count' => $result['updated_count'] ?? 0,
		]);
	}
}
