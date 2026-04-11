<?php

if (! defined('ABSPATH')) {
	exit;
}

$is_logged_in = CMS_Telegram_Auth::is_logged_in();

$repository = new PostRepository();
$items      = $repository->getAll();

$categories = (new CategoryRepository())->getAll();
$category_map = [];
foreach ($categories as $cat) {
    $category_map[$cat->getId()] = $cat->getName();
}

cms_tg_admin_header('Danh sách bài viết', 'Quản lý dữ liệu đang hoạt động');
?>

<div class="wrap cms-tg-page">

	<div class="cms-tg-card cms-tg-toolbar-card">
		<div class="cms-tg-toolbar">
			<input type="text" class="regular-text" placeholder="Tìm theo tiêu đề hoặc keyword..." disabled>
			<select disabled>
				<option>Tất cả trạng thái</option>
				<option>Draft</option>
				<option>Published</option>
			</select>

			<?php if ($is_logged_in): ?>
				<button id="btn-bulk-title" class="button button-secondary" disabled>
					✏️ Sửa tiêu đề (<span id="selected-count-title">0</span>)
				</button>
				<button id="btn-bulk-keyword" class="button button-secondary" disabled>
					🔑 Lấy keyword (<span id="selected-count-keyword">0</span>)
				</button>
				<button id="btn-ga4-stats" class="button button-secondary">
					📊 GA4 Stats
				</button>
				<button id="btn-gsc-stats" class="button button-secondary">
					🔍 GSC Stats
				</button>
				<a class="button button-primary" href="<?php echo esc_url(admin_url('admin.php?page=cms-telegram-create')); ?>">
					+ Thêm mới
				</a>
			<?php endif; ?>
		</div>
	</div>

	<div class="cms-tg-card" style="margin-top: 18px;">
		<table class="wp-list-table widefat fixed striped cms-tg-table">
			<thead>
				<tr>
					<th width="40"><input type="checkbox" id="check-all"></th>
					<th width="60">ID</th>
					<th>Tiêu đề</th>
					<th>Keyword</th>
					<th>Website URL</th>
					<th width="120">Danh mục</th>
					<th width="120">Trạng thái</th>
					<th width="180">Created at</th>
					<th width="180">Thao tác</th>
				</tr>
			</thead>
			<tbody>
				<?php if (empty($items)): ?>
					<tr>
						<td colspan="8" style="text-align:center;">Không có dữ liệu.</td>
					</tr>
				<?php else: ?>
					<?php foreach ($items as $item): ?>
						<tr data-id="<?php echo esc_attr($item->getId()); ?>">
							<td>
								<input
									type="checkbox"
									class="row-check"
									data-id="<?php echo esc_attr($item->getId()); ?>"
									data-title="<?php echo esc_attr($item->getTitle()); ?>"
									data-keyword="<?php echo esc_attr($item->getKeyword()); ?>"
									data-website-url="<?php echo esc_attr($item->getWebsiteUrl()); ?>">
							</td>
							<td><?php echo esc_html($item->getId()); ?></td>
							<td><?php echo esc_html($item->getTitle()); ?></td>
							<td><?php echo esc_html($item->getKeyword()); ?></td>
							<td>
								<?php if ($item->getWebsiteUrl()): ?>
									<a href="<?php echo esc_url($item->getWebsiteUrl()); ?>" target="_blank" rel="noopener">
										<?php echo esc_html($item->getWebsiteUrl()); ?>
									</a>
								<?php else: ?>
									<span style="color:#9ca3af;">-</span>
								<?php endif; ?>
							</td>
							<td><?php 
								$cat_id = $item->getCategoryId();
								echo esc_html($cat_id && isset($category_map[$cat_id]) ? $category_map[$cat_id] : '-'); 
							?></td>
							<td>
								<span class="cms-tg-badge <?php echo strtolower(esc_attr($item->getStatus())); ?>">
									<?php echo esc_html($item->getStatus()); ?>
								</span>
							</td>
							<td><?php echo esc_html($item->getCreatedAt()); ?></td>
							<td>
								<div class="cms-tg-row-actions">
									<a href="<?php echo esc_url(admin_url('admin.php?page=cms-telegram-create&view=' . $item->getId())); ?>">Xem</a>
									<?php if ($is_logged_in): ?>
										<a href="<?php echo esc_url(admin_url('admin.php?page=cms-telegram-create&edit=' . $item->getId())); ?>">Sửa</a>
										<a class="is-danger" href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=cms-telegram-posts&cms_tg_action=trash&id=' . $item->getId()), 'cms_tg_row_action')); ?>">Xóa mềm</a>
									<?php endif; ?>
								</div>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>
	</div>

</div>

<div id="cms-tg-bulk-title-modal" class="cms-tg-modal" style="display:none;">
	<div class="cms-tg-modal-backdrop"></div>
	<div class="cms-tg-modal-dialog">
		<div class="cms-tg-card">
			<div class="cms-tg-card-header">
				<h2>Sửa tiêu đề hàng loạt</h2>
				<p>Tối đa 100 bài / lần.</p>
			</div>

			<div class="cms-tg-field" style="margin-bottom:16px;">
				<label for="bulk-title-mode">Kiểu cập nhật</label>
				<select id="bulk-title-mode">
					<option value="prepend">Thêm vào đầu tiêu đề</option>
					<option value="append">Thêm vào cuối tiêu đề</option>
					<option value="replace">Thay toàn bộ tiêu đề</option>
				</select>
			</div>

			<div class="cms-tg-field" style="margin-bottom:16px;">
				<label for="bulk-title-value">Nội dung</label>
				<input id="bulk-title-value" type="text" placeholder="Nhập nội dung cần cập nhật">
			</div>

			<div class="cms-tg-field" style="margin-bottom:16px;">
				<label for="bulk-title-preview">Preview</label>
				<input id="bulk-title-preview" type="text" readonly>
			</div>

			<div class="cms-tg-side-actions" style="display:flex; gap:10px;">
				<button type="button" id="bulk-title-submit" class="button button-primary">Cập nhật</button>
				<button type="button" id="bulk-title-close" class="button">Đóng</button>
			</div>
		</div>
	</div>
</div>

<div id="cms-tg-ga4-modal" class="cms-tg-modal" style="display:none;">
    <div class="cms-tg-modal-backdrop"></div>
    <div class="cms-tg-modal-dialog">
        <div class="cms-tg-card">
            <div class="cms-tg-card-header">
                <h2>📊 GA4 Analytics</h2>
                <p>Dữ liệu 28 ngày gần nhất</p>
            </div>

            <div id="ga4-loading" style="text-align:center; padding:20px;">
                ⏳ Đang tải dữ liệu...
            </div>

            <div id="ga4-result" style="display:none;">
                <table class="wp-list-table widefat fixed striped" style="margin-bottom:16px;">
                    <tr>
                        <th>👥 Tổng Users</th>
                        <td><strong id="ga4-total-users">-</strong></td>
                    </tr>
                    <tr>
                        <th>⏱ Tổng thời lượng</th>
                        <td><strong id="ga4-duration">-</strong></td>
                    </tr>
                </table>
            </div>

            <div id="ga4-error" style="display:none; color:red; padding:10px 0;"></div>

            <div style="display:flex; gap:10px; margin-top:8px;">
                <button type="button" id="ga4-refresh" class="button button-primary">🔄 Làm mới</button>
                <button type="button" id="ga4-close" class="button">Đóng</button>
            </div>
        </div>
    </div>
</div>

<div id="cms-tg-gsc-modal" class="cms-tg-modal" style="display:none;">
    <div class="cms-tg-modal-backdrop"></div>
    <div class="cms-tg-modal-dialog">
        <div class="cms-tg-card">
            <div class="cms-tg-card-header">
                <h2>🔍 Google Search Console</h2>
                <p>Dữ liệu 28 ngày gần nhất</p>
            </div>

            <div id="gsc-loading" style="text-align:center; padding:20px;">
                ⏳ Đang tải dữ liệu...
            </div>

            <div id="gsc-result" style="display:none;">
                <table class="wp-list-table widefat fixed striped" style="margin-bottom:16px;">
                    <tr>
                        <th>👁 Tổng hiển thị</th>
                        <td><strong id="gsc-impressions">-</strong></td>
                    </tr>
                    <tr>
                        <th>🖱 Tổng lượt nhấp</th>
                        <td><strong id="gsc-clicks">-</strong></td>
                    </tr>
                    <tr>
                </table>
            </div>

            <div id="gsc-error" style="display:none; color:red; padding:10px 0;"></div>

            <div style="display:flex; gap:10px; margin-top:8px;">
                <button type="button" id="gsc-refresh" class="button button-primary">🔄 Làm mới</button>
                <button type="button" id="gsc-close" class="button">Đóng</button>
            </div>
        </div>
    </div>
</div>

<script>
	window.CMS_TG_BULK_TITLE = {
		ajaxUrl: "<?php echo esc_url(admin_url('admin-ajax.php')); ?>",
		nonce: "<?php echo esc_js(wp_create_nonce('cms_tg_bulk_title_action')); ?>"
	};

	window.CMS_TG_BULK_KEYWORD = {
		ajaxUrl: "<?php echo esc_url(admin_url('admin-ajax.php')); ?>",
		nonce: "<?php echo esc_js(wp_create_nonce('cms_tg_bulk_keyword_action')); ?>",
		gasUrl: "https://script.google.com/macros/s/AKfycbyr4EShIhaR4fh5G3UAimy6a8022bDidYjmuL1cajzZUFFoJphGPpqgFvSi8DvPWXS0/exec"
	};
    window.CMS_TG_GA4 = {
        url: "https://script.google.com/macros/s/AKfycbwyNKy1B3EZmoyHKpYx2hxtfq4dudb9rO_acvPeA4yhMC6vqRO4Q-3LJl2ONUMKyMwOyg/exec"
    };
		window.CMS_TG_GSC = {
				url: "https://script.google.com/macros/s/AKfycbwfhlv440h239p7whPetTJVlk9V51lLAJIJjWBAsOfNgRg9PwqNhoGyntaFXawULbTJwQ/exec"
		};
		window.CMS_TG_ANALYTICS = {
		    ajaxUrl: "<?php echo esc_url(admin_url('admin-ajax.php')); ?>",
		    nonce: "<?php echo esc_js(wp_create_nonce('cms_tg_notify_analytics_action')); ?>"
		};
</script>

<script>
	(function() {
		const GAS_URL = 'https://script.google.com/macros/s/AKfycbyQ162802590_001_1712771243566/exec';

        async function notifyAnalytics(type, data) {
            try {
                await fetch(window.CMS_TG_ANALYTICS.ajaxUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                    body: new URLSearchParams({
                        action: 'cms_tg_notify_analytics',
                        nonce: window.CMS_TG_ANALYTICS.nonce,
                        type: type,
                        stats: JSON.stringify(data)
                    })
                });
            } catch (e) {
                console.error('Lỗi gửi thông báo analytics', e);
            }
        }

		const checkAll = document.getElementById('check-all');
		const btnWrite = document.getElementById('btn-write-selected');
		const btnBulkKeyword = document.getElementById('btn-bulk-keyword');
		const btnBulkTitle = document.getElementById('btn-bulk-title');
		const countSpan = document.getElementById('selected-count');
		const countSpanTitle = document.getElementById('selected-count-title');
    const countSpanKeyword = document.getElementById('selected-count-keyword');
		
		const modal = document.getElementById('cms-tg-bulk-title-modal');
		const modalClose = document.getElementById('bulk-title-close');
		const modalSubmit = document.getElementById('bulk-title-submit');
		const modeInput = document.getElementById('bulk-title-mode');
		const valueInput = document.getElementById('bulk-title-value');
		const previewInput = document.getElementById('bulk-title-preview');

		function getCheckedRows() {
			return [...document.querySelectorAll('.row-check:checked')];
		}

		function updateCount() {

			const checked = getCheckedRows().length;

			if (countSpan) countSpan.textContent = checked;
			if (countSpanTitle) countSpanTitle.textContent = checked;

			if (btnWrite) btnWrite.disabled = checked === 0;
			if (btnBulkTitle) btnBulkTitle.disabled = checked === 0;

			if (countSpanKeyword) countSpanKeyword.textContent = checked;
			if (btnBulkKeyword) btnBulkKeyword.disabled = checked === 0;

			const total = document.querySelectorAll('.row-check').length;
			if (checkAll) {
				checkAll.checked = checked === total && total > 0;
				checkAll.indeterminate = checked > 0 && checked < total;
			}
		}

		function openModal() {
			if (modal) modal.style.display = 'block';
			updatePreview();
			valueInput?.focus();
		}

		function closeModal() {
			if (modal) modal.style.display = 'none';
			if (valueInput) valueInput.value = '';
			if (previewInput) previewInput.value = '';
			if (modeInput) modeInput.value = 'prepend';
		}

		function updatePreview() {
			const checked = getCheckedRows();
			const first = checked[0];
			const sampleTitle = first ? (first.dataset.title || '') : '';
			const value = valueInput ? valueInput.value.trim() : '';
			const mode = modeInput ? modeInput.value : 'prepend';

			let preview = sampleTitle;

			if (mode === 'prepend') {
				preview = value ? `${value} ${sampleTitle}`.trim() : sampleTitle;
			} else if (mode === 'append') {
				preview = value ? `${sampleTitle} ${value}`.trim() : sampleTitle;
			} else if (mode === 'replace') {
				preview = value;
			}

			if (previewInput) {
				previewInput.value = preview;
			}
		}

		checkAll?.addEventListener('change', function() {
			document.querySelectorAll('.row-check').forEach(cb => cb.checked = this.checked);
			updateCount();
		});

		document.querySelectorAll('.row-check').forEach(cb => {
			cb.addEventListener('change', updateCount);
		});

		modeInput?.addEventListener('change', updatePreview);
		valueInput?.addEventListener('input', updatePreview);

		btnBulkTitle?.addEventListener('click', function() {
			const checked = getCheckedRows();

			if (!checked.length) {
				alert('Vui lòng chọn bài viết.');
				return;
			}

			if (checked.length > 100) {
				alert('Chỉ được sửa tối đa 100 bài mỗi lần.');
				return;
			}

			openModal();
		});

		modalClose?.addEventListener('click', closeModal);

		modal?.querySelector('.cms-tg-modal-backdrop')?.addEventListener('click', closeModal);

		modalSubmit?.addEventListener('click', async function() {
			const checked = getCheckedRows();

			if (!checked.length) {
				alert('Vui lòng chọn bài viết.');
				return;
			}

			if (checked.length > 100) {
				alert('Chỉ được sửa tối đa 100 bài mỗi lần.');
				return;
			}

			const mode = modeInput?.value || 'prepend';
			const value = valueInput?.value.trim() || '';

			if (!value) {
				alert('Vui lòng nhập nội dung cần cập nhật.');
				valueInput?.focus();
				return;
			}

			modalSubmit.disabled = true;
			modalSubmit.textContent = 'Đang cập nhật...';

			const ids = checked.map(cb => cb.dataset.id);

			try {
				if (!window.CMS_TG_BULK_TITLE || !window.CMS_TG_BULK_TITLE.ajaxUrl) {
					alert('Thiếu cấu hình AJAX.');
					return;
				}
				const response = await fetch(window.CMS_TG_BULK_TITLE.ajaxUrl, {
					method: 'POST',
					headers: {
						'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
					},
					body: new URLSearchParams({
						action: 'cms_tg_bulk_update_titles',
						nonce: window.CMS_TG_BULK_TITLE.nonce,
						ids: JSON.stringify(ids),
						mode: mode,
						value: value
					})
				});

				const data = await response.json();

				if (!data.success) {
					alert(data?.data?.message || 'Có lỗi xảy ra khi cập nhật tiêu đề.');
					return;
				}

				checked.forEach(cb => {
					const row = cb.closest('tr');
					const titleCell = row?.children?.[2];
					const oldTitle = cb.dataset.title || '';

					let newTitle = oldTitle;
					if (mode === 'prepend') {
						newTitle = `${value} ${oldTitle}`.trim();
					} else if (mode === 'append') {
						newTitle = `${oldTitle} ${value}`.trim();
					} else if (mode === 'replace') {
						newTitle = value;
					}

					cb.dataset.title = newTitle;

					if (titleCell) {
						titleCell.textContent = newTitle;
					}
				});

				alert(data?.data?.message || 'Cập nhật thành công.');
				closeModal();
			} catch (error) {
				console.error(error);
				alert('Lỗi kết nối khi cập nhật tiêu đề.');
			} finally {
				modalSubmit.disabled = false;
				modalSubmit.textContent = 'Cập nhật';
			}
		});

		btnWrite?.addEventListener('click', async function() {
			const checked = [...document.querySelectorAll('.row-check:checked')];
			if (!checked.length) return;

			btnWrite.disabled = true;
			btnWrite.textContent = '⏳ Đang viết...';

			const rows = checked.map(cb => ({
				id: cb.dataset.id,
				title: cb.dataset.title,
				keyword: cb.dataset.keyword,
				website_url: cb.dataset.websiteUrl,
			}));

			for (const row of rows) {
				const tr = document.querySelector(`tr[data-id="${row.id}"]`);
				const badge = tr?.querySelector('.cms-tg-badge');

				if (badge) {
					badge.className = 'cms-tg-badge writing';
					badge.textContent = 'Đang viết...';
				}

				try {
					const res = await fetch(GAS_URL, {
						method: 'POST',
						headers: {
							'Content-Type': 'text/plain'
						},
						body: JSON.stringify({
							action: 'autoWriteOnly',
							row
						}),
					});

					const data = await res.json();

					if (data.success && data.link_docs) {
						if (badge) {
							badge.className = 'cms-tg-badge cho-dang';
							badge.textContent = 'Chờ đăng';
						}

						const actionsDiv = tr?.querySelector('.cms-tg-row-actions');
						if (actionsDiv) {
							const existing = actionsDiv.querySelector('.link-docs');
							if (existing) existing.remove();

							const a = document.createElement('a');
							a.href = data.link_docs;
							a.target = '_blank';
							a.textContent = 'Docs';
							a.className = 'link-docs';
							actionsDiv.prepend(a);
						}
					} else {
						if (badge) {
							badge.className = 'cms-tg-badge error';
							badge.textContent = 'Lỗi';
						}
					}
				} catch (err) {
					if (badge) {
						badge.className = 'cms-tg-badge error';
						badge.textContent = 'Lỗi kết nối';
					}
					console.error('Lỗi row ' + row.id, err);
				}
			}

			btnWrite.textContent = '✍️ Viết bài đã chọn (0)';
			btnWrite.disabled = true;
			if (countSpan) countSpan.textContent = '0';
			if (countSpanTitle) countSpanTitle.textContent = '0';
			document.querySelectorAll('.row-check').forEach(cb => cb.checked = false);
			if (checkAll) {
				checkAll.checked = false;
				checkAll.indeterminate = false;
			}
			if (btnBulkTitle) btnBulkTitle.disabled = true;
		});
		btnBulkKeyword?.addEventListener('click', async function() {
			const checked = getCheckedRows();

			if (!checked.length) {
				alert('Vui lòng chọn bài viết.');
				return;
			}

			if (checked.length > 100) {
				alert('Chỉ được xử lý tối đa 100 bài mỗi lần.');
				return;
			}

			if (!window.CMS_TG_BULK_KEYWORD || !window.CMS_TG_BULK_KEYWORD.gasUrl) {
				alert('Thiếu cấu hình GAS.');
				return;
			}

			btnBulkKeyword.disabled = true;
			btnBulkKeyword.textContent = '⏳ Đang lấy keyword...';

			try {
				const gasPayload = {
					action: 'bulk_get_keywords',
					items: checked.map(cb => ({
						id: cb.dataset.id,
						website_url: cb.dataset.websiteUrl || ''
					}))
				};

				const gasResponse = await fetch(window.CMS_TG_BULK_KEYWORD.gasUrl, {
					method: 'POST',
					headers: {
						'Content-Type': 'text/plain;charset=utf-8'
					},
					body: JSON.stringify(gasPayload)
				});

				const gasData = await gasResponse.json();

				if (!gasData.success) {
					alert(gasData.message || 'Không lấy được keyword từ GAS.');
					return;
				}

				const validItems = (gasData.items || []).map(item => ({
					id: item.id,
					keyword: item.keyword || ''
				}));

				const wpResponse = await fetch(window.CMS_TG_BULK_KEYWORD.ajaxUrl, {
					method: 'POST',
					headers: {
						'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
					},
					body: new URLSearchParams({
						action: 'cms_tg_bulk_update_keywords',
						nonce: window.CMS_TG_BULK_KEYWORD.nonce,
						items: JSON.stringify(validItems)
					})
				});

				const wpData = await wpResponse.json();

				if (!wpData.success) {
					alert(wpData?.data?.message || 'Không cập nhật được keyword vào WordPress.');
					return;
				}

				checked.forEach(cb => {
					const row = cb.closest('tr');
					const keywordCell = row?.children?.[3];
					const matched = validItems.find(x => String(x.id) === String(cb.dataset.id));

					if (matched) {
						cb.dataset.keyword = matched.keyword || '';
						if (keywordCell) {
							keywordCell.textContent = matched.keyword || '';
						}
					}
				});

				alert(wpData?.data?.message || 'Đã cập nhật keyword thành công.');
			} catch (error) {
				console.error(error);
				alert('Lỗi kết nối khi lấy keyword.');
			} finally {
				btnBulkKeyword.innerHTML = '🔑 Lấy keyword (<span id="selected-count-keyword">' + checked.length + '</span>)';
				updateCount();
			}
		});

		// Tính năng bấm "Lấy keywords" thủ công trên từng dòng
		document.querySelectorAll('.btn-single-keyword').forEach(btn => {
			btn.addEventListener('click', async function(e) {
				e.preventDefault();
				const id = this.dataset.id;
				const wpLink = this.dataset.websiteUrl;

				if (!wpLink) {
					alert('Bài này chưa có Website URL (chưa xuất bản link gốc) nên không lấy được keyword!');
					return;
				}

				if (!window.CMS_TG_BULK_KEYWORD || !window.CMS_TG_BULK_KEYWORD.gasUrl) {
					alert('Thiếu cấu hình link GAS.');
					return;
				}

				const orgText = this.textContent;
				this.textContent = '⏳ Lọc...';
				this.style.pointerEvents = 'none';

				try {
					// Vẫn xài action bulk_get_keywords của GAS nhưng chỉ gửi mảng 1 item
					const gasPayload = {
						action: 'bulk_get_keywords', 
						items: [{ id: id, website_url: wpLink }]
					};

					const gasResponse = await fetch(window.CMS_TG_BULK_KEYWORD.gasUrl, {
						method: 'POST',
						headers: { 'Content-Type': 'text/plain;charset=utf-8' },
						body: JSON.stringify(gasPayload)
					});
					const gasData = await gasResponse.json();

					if (!gasData.success) {
						alert(gasData.message || 'Lỗi GAS không lấy được keyword.');
						return;
					}

					const validItems = (gasData.items || []).map(item => ({
						id: item.id,
						keyword: item.keyword || ''
					}));

					// Gửi về WP để Save vào CSDL
					const wpResponse = await fetch(window.CMS_TG_BULK_KEYWORD.ajaxUrl, {
						method: 'POST',
						headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
						body: new URLSearchParams({
							action: 'cms_tg_bulk_update_keywords',
							nonce: window.CMS_TG_BULK_KEYWORD.nonce,
							items: JSON.stringify(validItems)
						})
					});

					const wpData = await wpResponse.json();
					if (wpData.success) {
						// Hiện kết quả ra bảng
						const row = this.closest('tr');
						const keywordCell = row?.children?.[3]; // Cột keyword nằm ở i=3 (ID, Title, Keyword)
						const matched = validItems[0];
						
						if (matched && keywordCell) {
							keywordCell.textContent = matched.keyword || '';
							const cbCheck = row.querySelector('.row-check');
							if (cbCheck) cbCheck.dataset.keyword = matched.keyword || '';
						}
					} else {
						alert(wpData?.data?.message || 'Lỗi lưu keyword vào WordPress.');
					}
				} catch (err) {
					console.error(err);
					alert('Lỗi Network kết nối đến GAS.');
				} finally {
					this.textContent = orgText;
					this.style.pointerEvents = 'auto';
				}
			});
		});

		// ── GA4 Stats ─────────────────────────────────────────────
		const btnGa4      = document.getElementById('btn-ga4-stats');
		const ga4Modal    = document.getElementById('cms-tg-ga4-modal');
		const ga4Close    = document.getElementById('ga4-close');
		const ga4Refresh  = document.getElementById('ga4-refresh');
		const ga4Loading  = document.getElementById('ga4-loading');
		const ga4Result   = document.getElementById('ga4-result');
		const ga4Error    = document.getElementById('ga4-error');

		async function fetchGa4Stats() {
				ga4Loading.style.display = 'block';
				ga4Result.style.display  = 'none';
				ga4Error.style.display   = 'none';

				try {
						const url = window.CMS_TG_GA4.url + '?type=summary&startDate=28daysAgo&endDate=today';
						const res  = await fetch(url);
						const data = await res.json();

						if (data.status !== 'ok') throw new Error(data.message || 'Lỗi từ GA4');

						document.getElementById('ga4-total-users').textContent = data.totalUsers.toLocaleString();
						document.getElementById('ga4-duration').textContent    = data.totalDurationFormatted;

						ga4Loading.style.display = 'none';
						ga4Result.style.display  = 'block';

						notifyAnalytics('ga4', data);

				} catch (err) {
						ga4Loading.style.display = 'none';
						ga4Error.style.display   = 'block';
						ga4Error.textContent     = '❌ Lỗi: ' + err.message;
						console.error(err);
				}
		}

		btnGa4?.addEventListener('click', function() {
    ga4Modal.style.display = 'block';
    fetchGa4Stats();
		});

		ga4Close?.addEventListener('click', function() {
				ga4Modal.style.display = 'none';
		});

		ga4Refresh?.addEventListener('click', fetchGa4Stats);

		ga4Modal?.querySelector('.cms-tg-modal-backdrop')?.addEventListener('click', function() {
				ga4Modal.style.display = 'none';
		});

		const btnGsc     = document.getElementById('btn-gsc-stats');
		const gscModal   = document.getElementById('cms-tg-gsc-modal');
		const gscClose   = document.getElementById('gsc-close');
		const gscRefresh = document.getElementById('gsc-refresh');
		const gscLoading = document.getElementById('gsc-loading');
		const gscResult  = document.getElementById('gsc-result');
		const gscError   = document.getElementById('gsc-error');

		async function fetchGscStats() {
				gscLoading.style.display = 'block';
				gscResult.style.display  = 'none';
				gscError.style.display   = 'none';

				try {
						const url = window.CMS_TG_GSC.url + '?type=summary&startDate=28daysAgo&endDate=today';
						const res  = await fetch(url);
						const data = await res.json();
						if (data.status !== 'ok') throw new Error(data.message || 'Lỗi GSC');
						const d = data;
						document.getElementById('gsc-impressions').textContent = d.impressions.toLocaleString();
						document.getElementById('gsc-clicks').textContent      = d.clicks.toLocaleString();

						gscLoading.style.display = 'none';
						gscResult.style.display  = 'block';

						notifyAnalytics('gsc', d);

				} catch (err) {
						gscLoading.style.display = 'none';
						gscError.style.display   = 'block';
						gscError.textContent     = '❌ Lỗi: ' + err.message;
				}
		}

		btnGsc?.addEventListener('click', function() {
				gscModal.style.display = 'block';
				fetchGscStats();
		});

		gscClose?.addEventListener('click', () => gscModal.style.display = 'none');
		gscRefresh?.addEventListener('click', fetchGscStats);
		gscModal?.querySelector('.cms-tg-modal-backdrop')?.addEventListener('click', () => gscModal.style.display = 'none');

		updateCount();
	})();
</script>