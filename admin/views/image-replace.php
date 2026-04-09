<?php

if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;

$websites  = CMS_Telegram_Image_Replace::get_websites();
$done      = isset($_GET['done']);
$error     = $_GET['error'] ?? '';
$has_more  = !empty($_GET['has_more']);

$replaced    = intval($_GET['replaced'] ?? 0);
$skipped     = intval($_GET['skipped'] ?? 0);
$found       = intval($_GET['found'] ?? 0);
$last_id     = intval($_GET['last_id'] ?? 0);
$wp_replaced = intval($_GET['wp_replaced'] ?? 0);

$new_url = urldecode($_GET['new_url'] ?? '');
$website = urldecode($_GET['website'] ?? '');

$repo  = new PostRepository();
$posts = $repo->getAll();

cms_tg_admin_header('Sửa ảnh hàng loạt', 'Upload ảnh lên WP site khác rồi thay URL trong bài viết.');
?>

<div class="wrap cms-tg-page">

    <?php if ($error === 'missing_url'): ?>
        <div class="notice notice-error"><p>⚠️ Vui lòng nhập URL ảnh mới.</p></div>
    <?php endif; ?>

    <?php if ($error === 'missing_posts'): ?>
        <div class="notice notice-error"><p>⚠️ Vui lòng chọn ít nhất 1 bài viết.</p></div>
    <?php endif; ?>

    <?php if ($done): ?>
        <div class="cms-tg-card" style="margin-bottom:20px;">
            <h3><?php echo $has_more ? '⚙️ Đã xử lý batch' : '✅ Hoàn tất'; ?></h3>
            <div style="display:flex; gap:20px; flex-wrap:wrap;">
                <div>Đã thay ảnh cho: <strong style="color:#2563eb;"><?php echo $wp_replaced; ?></strong> bài viết thật trên WP</div>
            </div>
            <?php if ($has_more): ?>
                <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                    <?php wp_nonce_field('cms_tg_image_replace_nonce'); ?>
                    <input type="hidden" name="action"  value="cms_tg_image_replace">
                    <input type="hidden" name="new_url" value="<?php echo esc_attr($new_url); ?>">
                    <input type="hidden" name="website" value="<?php echo esc_attr($website); ?>">
                    <input type="hidden" name="last_id" value="<?php echo esc_attr($last_id); ?>">
                    <?php foreach ($_POST['post_ids'] ?? [] as $id): ?>
                        <input type="hidden" name="post_ids[]" value="<?php echo intval($id); ?>">
                    <?php endforeach; ?>
                    <button class="button button-primary" style="margin-top:10px;">▶ Chạy tiếp</button>
                </form>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- ── FORM THAY URL ──────────────────────────────────────────────────── -->
    <div class="cms-tg-card">
        <div class="cms-tg-card-header">
            <h2>🔁 Cấu hình Thay Ảnh (Cột 1: Ảnh Mới -> Cột 2: Trang Web)</h2>
            <p>Chọn file ảnh hoặc nhập URL ảnh mới ở Cột 1 và dán Link bài viết (URL) cần thay ảnh ở Cột 2. Khi chạy, công cụ sẽ upload ảnh và tự động thay ảnh đại diện + ảnh trong nội dung bài viết đó.</p>
        </div>

        <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" style="padding:16px;" id="replace-form" enctype="multipart/form-data">
            <?php wp_nonce_field('cms_tg_image_replace_nonce'); ?>
            <input type="hidden" name="action"  value="cms_tg_image_replace">
            <input type="hidden" name="last_id" value="0">

            <div style="background:#f9fafb; padding:15px; border:1px solid #e5e7eb; border-radius:6px; margin-bottom:20px;">
                <div id="mapping-rows">
                    <div class="mapping-row" style="display:flex; gap:10px; margin-bottom:15px; align-items:flex-start;">
                        <div style="flex:1;">
                            <label style="display:block; font-weight:600; margin-bottom:5px;">Cột 1: Ảnh Mới (Upload File hoặc URL)</label>
                            <input type="file" name="new_image_files[]" accept="image/*" style="width:100%; margin-bottom:5px; padding:5px; border:1px solid #ddd; background:#fff; border-radius:4px;">
                            <input type="text" name="new_image_urls[]" placeholder="Hoặc dán URL ảnh nếu không có file" style="width:100%;">
                        </div>
                        <div style="flex:1;">
                            <label style="display:block; font-weight:600; margin-bottom:5px;">Cột 2: Trang Web (Post URL)</label>
                            <input type="text" name="target_post_urls[]" placeholder="Dán link bài viết (VD: https://domain/ten-bai-viet/)" required style="width:100%;">
                        </div>
                        <button type="button" class="button btn-remove-row" style="color:#ef4444; margin-top:28px;" title="Xóa hàng này">✖</button>
                    </div>
                </div>
                
                <button type="button" id="btn-add-row" class="button">
                    ➕ Thêm hàng
                </button>
            </div>

            <button type="submit" id="btn-submit" class="button button-primary" style="margin-top:15px;">
                🔄 Chạy thay thế
            </button>
        </form>
    </div>

</div>

<script>
    // Khai báo biến dùng cho REST API
    const WP_NONCE = "<?php echo esc_js(wp_create_nonce('wp_rest')); ?>";
    // Mặc định gọi vào API của chính website hiện hành; nếu xavia.cloud là web khác cần truyền chéo thì đổi biến SITE_URL
    const SITE_URL = "<?php echo esc_url(site_url('/')); ?>"; 
    
    (function () {

    const replaceForm = document.getElementById('replace-form');
    const btnAddRow   = document.getElementById('btn-add-row');
    const mappingRows = document.getElementById('mapping-rows');

    // Thêm hàng mới
    if (btnAddRow) {
        btnAddRow.addEventListener('click', function() {
            const row = document.createElement('div');
            row.className = 'mapping-row';
            row.style.cssText = 'display:flex; gap:10px; margin-bottom:15px; align-items:flex-start;';
            row.innerHTML = `
                <div style="flex:1;">
                    <label style="display:block; font-weight:600; margin-bottom:5px;">Cột 1: Ảnh Mới (Upload File hoặc URL)</label>
                    <input type="file" name="new_image_files[]" accept="image/*" style="width:100%; margin-bottom:5px; padding:5px; border:1px solid #ddd; background:#fff; border-radius:4px;">
                    <input type="text" name="new_image_urls[]" placeholder="Hoặc dán URL ảnh nếu không có file" style="width:100%;">
                </div>
                <div style="flex:1;">
                    <label style="display:block; font-weight:600; margin-bottom:5px;">Cột 2: Trang Web (Post URL)</label>
                    <input type="text" name="target_post_urls[]" placeholder="Dán link bài viết (VD: https://domain/ten-bai-viet/)" required style="width:100%;">
                </div>
                <button type="button" class="button btn-remove-row" style="color:#ef4444; margin-top:28px;" title="Xóa hàng này">✖</button>
            `;
            mappingRows.appendChild(row);
        });
    }

    // Xóa hàng
    if (mappingRows) {
        mappingRows.addEventListener('click', function(e) {
            if (e.target.classList.contains('btn-remove-row')) {
                const rows = mappingRows.querySelectorAll('.mapping-row');
                if (rows.length > 1) {
                    e.target.closest('.mapping-row').remove();
                } else {
                    alert('Phải có ít nhất 1 hàng cấu hình.');
                }
            }
        });
    }

    replaceForm.addEventListener('submit', async function(e) {
        e.preventDefault();

        const btn = document.getElementById('btn-submit');
        
        const rows = mappingRows.querySelectorAll('.mapping-row');
        let valid = true;
        
        for (let row of rows) {
            const fileInput = row.querySelector('input[type="file"]');
            const urlInput = row.querySelector('input[name="new_image_urls[]"]');
            
            if (!fileInput.files.length && !urlInput.value.trim()) {
                valid = false;
                break;
            }
        }
        
        if (!valid) {
            alert('Vui lòng chọn File ảnh hoặc nhập URL ảnh mới ở Cột 1 cho tất cả các hàng.');
            return;
        }

        // Bắt đầu Upload JS
        btn.disabled = true;
        let hasError = false;

        for (let row of rows) {
            const fileInput = row.querySelector('input[type="file"]');
            const urlInput = row.querySelector('input[name="new_image_urls[]"]');
            const targetUrl = row.querySelector('input[name="target_post_urls[]"]').value;

            if (fileInput.files.length > 0) {
                const file = fileInput.files[0];
                btn.innerHTML = '🔄 Đang Upload ảnh... (' + file.name + ')';

                try {
                    // Gọi wp/v2/media bằng binary buffer (không dùng FormData đối với media upload chuẩn WP)
                    const response = await fetch(SITE_URL + 'wp-json/wp/v2/media', {
                        method: 'POST',
                        body: file,
                        headers: {
                            'Content-Disposition': 'attachment; filename="' + encodeURIComponent(file.name) + '"',
                            'X-WP-Nonce': WP_NONCE,
                            'Content-Type': file.type || 'image/jpeg'
                        }
                    });

                    const data = await response.json();

                    if (response.ok) {
                        const secureUrl = data.url_secure || data.source_url || (data.guid && data.guid.raw);
                        if (secureUrl) {
                            alert("Thành công! URL lấy về là:\n" + secureUrl);
                            
                            // Đã có URL chuẩn, gắn vào urlInput và xoá File
                            urlInput.value = secureUrl;
                            fileInput.value = ''; // Cần xoá value để form PHP nhận diện đây là dạng URL
                        } else {
                            alert('Upload xong nhưng không đọc được url_secure từ API cho file ' + file.name);
                            hasError = true; break;
                        }
                    } else {
                        console.error("API response error: ", data);
                        alert('Lỗi Upload API: ' + (data.message || 'Lỗi không xác định'));
                        hasError = true; break;
                    }
                } catch (err) {
                    console.error("Fetch error: ", err);
                    alert('Lỗi kết nối Upload (Mạng/CORS): ' + err.message);
                    hasError = true; break;
                }
            }
        }

        if (hasError) {
            btn.disabled = false;
            btn.innerHTML = '🔄 Chạy thay thế';
            return;
        }

        btn.innerHTML = '🔄 Đang gắn ảnh vào bài viết...';
        // HTML form gốc chỉ có duy nhất các URL + Target URL để đẩy lên. 
        replaceForm.submit();
    });

})();

</script>