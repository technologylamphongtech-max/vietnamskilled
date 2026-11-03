<?php
declare(strict_types=1);

$pages = sitePages();
$id = textField($_GET['id'] ?? ($input['id'] ?? ''), 40);
$post = $data['posts'][$id] ?? null;
$editing = !$seoPage && ($post || isset($_GET['new']) || ($input['action'] ?? '') === 'save');
$title = $seoPage ? 'Cấu hình SEO' : ($editing ? ($post ? 'Sửa bài viết' : 'Viết bài mới') : 'Tin tức & Cập nhật');
$activePage = textField($_GET['page'] ?? ($input['page'] ?? 'global'), 200);
if ($activePage !== 'global' && !isset($pages[$activePage])) $activePage = 'global';
?>
<!doctype html>
<html lang="vi"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow"><title><?=h($title)?> | VietnamSkilled</title>
<link rel="stylesheet" href="/cms-assets/admin.css?v=1">
<?php if ($editing && ($post['status'] ?? '') !== 'trash'): ?><script src="/cms-assets/tinymce/tinymce.min.js"></script><?php endif; ?>
<script defer src="/cms-assets/admin.js?v=1"></script></head>
<body>
<header class="adminbar"><a class="brand" href="/admin/">VietnamSkilled</a><a href="/" target="_blank" rel="noopener">Xem website ↗</a><a href="/admin/?out=1">Đăng xuất</a></header>
<div class="admin-layout">
<nav class="sidebar" aria-label="Quản trị">
  <a href="/admin/">Nội dung trang</a>
  <a href="<?=NEWS_ADMIN?>" <?=!$seoPage ? 'aria-current="page"' : ''?>>Tin tức &amp; Cập nhật</a>
  <a href="<?=NEWS_ADMIN?>?new=1">+ Viết bài mới</a>
  <a href="<?=SEO_ADMIN?>" <?=$seoPage ? 'aria-current="page"' : ''?>>Cấu hình SEO</a>
</nav>
<main>
<div class="heading"><h1><?=h($title)?></h1>
<?php if (!$seoPage): ?><a class="button secondary" href="<?=$editing ? NEWS_ADMIN : NEWS_ADMIN . '?new=1'?>"><?=$editing ? '← Danh sách bài' : '+ Viết bài mới'?></a><?php endif; ?>
</div>
<?php if ($flash): ?><p class="notice" role="status"><?=h($flash)?></p><?php endif; ?>
<?php if ($error): ?><p class="notice error" role="alert"><?=h($error)?></p><?php endif; ?>

<?php if ($seoPage):
    $settings = $activePage === 'global' ? $data['seo'] : ($data['pages'][$activePage] ?? []);
    if ($input) {
        $settings = ['title'=>$input['seo_title'] ?? '', 'description'=>$input['seo_description'] ?? '', 'image'=>$input['seo_image'] ?? '',
            'canonical'=>$input['canonical'] ?? '', 'noindex'=>!empty($input['noindex']), 'brand'=>$input['brand'] ?? BRAND,
            'verification'=>$input['verification'] ?? ''];
    }
?>
<section class="card seo-panel">
<form method="get" action="<?=SEO_ADMIN?>" class="filters">
  <label>Trang cần chỉnh<select name="page"><option value="global">Toàn website</option>
  <?php foreach ($pages as $url => [$source, $label]): ?><option value="<?=h($url)?>" <?=$url === $activePage ? 'selected' : ''?>><?=h($label . ' · ' . $url)?></option><?php endforeach; ?>
  </select></label><button class="secondary">Mở cấu hình</button>
</form>
<form method="post" action="<?=h(SEO_ADMIN . '?page=' . rawurlencode($activePage))?>">
<?php hidden('csrf', $csrf); hidden('action', 'seo'); hidden('page', $activePage); ?>
<?php if ($activePage === 'global'): ?>
  <h2>Mặc định toàn website</h2>
  <label>Tên website<input name="brand" value="<?=h($settings['brand'] ?? BRAND)?>" maxlength="80"></label>
  <label>Tiêu đề SEO trang chủ<input name="seo_title" value="<?=h($settings['title'] ?? '')?>" maxlength="180"></label>
  <label>Mô tả SEO trang chủ<textarea name="seo_description" rows="3" maxlength="400"><?=h($settings['description'] ?? '')?></textarea></label>
  <label>Ảnh mặc định khi chia sẻ<input name="seo_image" value="<?=h($settings['image'] ?? '')?>" placeholder="https://... hoặc /images/..."></label>
  <label>Mã xác minh Google Search Console<input name="verification" value="<?=h($settings['verification'] ?? '')?>" maxlength="150" placeholder="Giá trị content của thẻ xác minh"></label>
<?php else: ?>
  <h2><?=h($pages[$activePage][1])?></h2>
  <p>Để trống trường để dùng giá trị mặc định. <a href="<?=h($activePage)?>" target="_blank" rel="noopener">Mở trang ↗</a></p>
  <?php seoInputs($settings); ?>
<?php endif; ?>
  <button>Lưu cấu hình SEO</button>
</form>
<p class="subtle"><a href="/sitemap.xml" target="_blank" rel="noopener">Sitemap XML ↗</a> · <a href="/robots.txt" target="_blank" rel="noopener">Robots.txt ↗</a>. SEO bài viết được chỉnh trong từng bài.</p>
</section>

<?php elseif ($editing && ($post['status'] ?? '') !== 'trash'):
    $values = $post ?? ['id'=>'','version'=>0,'title'=>'','slug'=>'','body'=>'','excerpt'=>'','categories'=>[],'tags'=>[],
        'image'=>'','image_alt'=>'','status'=>'draft','publish_at'=>0,'seo'=>[],'revisions'=>[]];
    $form = $values;
    $form['categories'] = implode(', ', $values['categories']);
    $form['tags'] = implode(', ', $values['tags']);
    $form['publish_at'] = $post && $post['status'] !== 'draft' ? siteDate($post['publish_at'], 'Y-m-d\TH:i') : '';
    if ($post && isPublic($post)) $form['status'] = 'published';
    if (($input['action'] ?? '') === 'save') {
        foreach (['title', 'slug', 'body', 'excerpt', 'categories', 'tags', 'image', 'image_alt', 'status', 'publish_at'] as $key) {
            if (isset($input[$key]) && is_string($input[$key])) $form[$key] = $input[$key];
        }
        $form['seo'] = ['title'=>$input['seo_title'] ?? '', 'description'=>$input['seo_description'] ?? '',
            'canonical'=>$input['canonical'] ?? '', 'image'=>$input['seo_image'] ?? '', 'noindex'=>!empty($input['noindex'])];
    }
?>
<form class="edit-grid" id="post-form" method="post" action="<?=h($post ? editorUrl($id) : NEWS_ADMIN . '?new=1')?>">
<?php hidden('csrf', $csrf); hidden('action', 'save'); hidden('id', $values['id']); hidden('version', $values['version']); ?>
<section class="card">
  <label for="post-title">Tiêu đề bài viết</label><input id="post-title" name="title" maxlength="240" required value="<?=h($form['title'])?>" placeholder="Nhập tiêu đề">
  <label>Đường dẫn bài viết<div class="input-prefix"><span>/tin-tuc/</span><input name="slug" value="<?=h($form['slug'])?>" placeholder="Tự tạo từ tiêu đề nếu để trống"></div></label>
  <label for="post-body">Nội dung</label><textarea id="post-body" name="body" rows="20"><?=h($form['body'])?></textarea>
  <label>Tóm tắt<textarea name="excerpt" rows="3" maxlength="500" placeholder="Tóm tắt hiển thị trong danh sách bài"><?=h($form['excerpt'])?></textarea></label>
  <details class="seo-details" open><summary>SEO bài viết</summary><?php seoInputs($form['seo']); ?></details>
</section>
<aside>
  <section class="card"><h2>Xuất bản</h2>
    <label>Trạng thái<select id="post-status" name="status">
    <?php foreach (['draft'=>'Bản nháp','published'=>'Xuất bản','scheduled'=>'Lên lịch'] as $value => $label): ?><option value="<?=$value?>" <?=$form['status'] === $value ? 'selected' : ''?>><?=$label?></option><?php endforeach; ?>
    </select></label>
    <label>Ngày giờ đăng (giờ Việt Nam)<input type="datetime-local" name="publish_at" value="<?=h($form['publish_at'])?>"></label>
    <p class="subtle">Để trống khi muốn đăng ngay.</p>
    <div class="actions"><button id="post-save">Lưu bài viết</button>
    <?php if ($post): ?><a class="button secondary" href="<?=h(postUrl($post) . '?preview=1')?>" target="_blank" rel="noopener">Xem trước bản đã lưu ↗</a>
      <?php if (isPublic($post)): ?><a href="<?=h(postUrl($post))?>" target="_blank" rel="noopener">Mở bài công khai ↗</a><?php endif; ?>
    <?php endif; ?></div>
  </section>
  <section class="card"><h2>Phân loại</h2>
    <label>Chuyên mục<input name="categories" value="<?=h($form['categories'])?>" placeholder="Du học nghề, Học bổng"></label>
    <label>Thẻ<input name="tags" value="<?=h($form['tags'])?>" placeholder="Tiếng Đức, Visa"></label>
    <p class="subtle">Phân cách các mục bằng dấu phẩy.</p>
  </section>
  <section class="card"><h2>Ảnh đại diện</h2>
    <img class="cover-preview" id="cover-preview" alt="" <?= $form['image'] && validUrl($form['image']) ? 'src="' . h($form['image']) . '"' : 'hidden' ?>>
    <label>Đường dẫn ảnh<input id="cover-url" name="image" value="<?=h($form['image'])?>" placeholder="Tải ảnh lên hoặc dán URL"></label>
    <label>Mô tả ảnh (alt)<input name="image_alt" value="<?=h($form['image_alt'])?>" maxlength="250"></label>
    <label class="file-label">Tải ảnh lên<input id="cover-file" type="file" accept="image/jpeg,image/png,image/webp,image/gif,image/avif"></label>
    <p class="subtle" id="cover-status" aria-live="polite">JPG, PNG, WebP, GIF, AVIF · tối đa 8 MB.</p>
  </section>
</aside>
</form>
<?php if ($post): ?>
<section class="card manage">
<h2>Lịch sử chỉnh sửa</h2>
<?php if (!$post['revisions']): ?><p>Chưa có phiên bản trước.</p><?php endif; ?>
<?php foreach ($post['revisions'] as $index => $version): ?>
<details><summary><?=h(siteDate($version['updated'], 'd/m/Y H:i') . ' · ' . $version['title'])?></summary>
<p><?=h(excerpt($version))?></p>
<?php actionForm($post, 'revision', 'Khôi phục nội dung', $csrf, 'Khôi phục nội dung của phiên bản này?', $index); ?></details>
<?php endforeach; ?>
<div class="actions"><?php actionForm($post, 'trash', 'Chuyển vào thùng rác', $csrf, 'Chuyển bài viết vào thùng rác?'); ?></div>
</section>
<?php endif; ?>

<?php else:
    $query = textField($_GET['q'] ?? '', 120);
    $filter = textField($_GET['status'] ?? '', 16);
    $matches = array_values(array_filter($data['posts'], function ($post) use ($query, $filter) {
        $status = isPublic($post) ? 'published' : $post['status'];
        return ($filter ? $status === $filter : $status !== 'trash') && (!$query || mb_stripos($post['title'] . ' ' . $post['slug'], $query) !== false);
    }));
    usort($matches, function ($a, $b) { return $b['updated'] <=> $a['updated']; });
?>
<section class="card">
<form action="<?=NEWS_ADMIN?>" method="get" class="filters">
  <label>Tìm bài viết<input name="q" value="<?=h($query)?>"></label>
  <label>Trạng thái<select name="status"><?php foreach ([''=>'Tất cả', 'published'=>'Đã xuất bản','draft'=>'Bản nháp','scheduled'=>'Đã lên lịch','trash'=>'Thùng rác'] as $value => $label): ?><option value="<?=$value?>" <?=$filter === $value ? 'selected' : ''?>><?=$label?></option><?php endforeach; ?></select></label>
  <button class="secondary">Lọc bài</button>
</form>
<div class="table-wrap"><table><thead><tr><th>Tiêu đề</th><th>Trạng thái</th><th>Cập nhật</th><th>Thao tác</th></tr></thead><tbody>
<?php if (!$matches): ?><tr><td colspan="4">Chưa có bài viết phù hợp.</td></tr><?php endif; ?>
<?php foreach ($matches as $entry): ?>
<tr><td><strong><?=h($entry['title'])?></strong><small><?=h(postUrl($entry))?></small></td><td><?=h(statusLabel($entry))?></td>
<td><?=h(siteDate($entry['updated'], 'd/m/Y H:i'))?></td><td>
<?php if ($entry['status'] === 'trash'): ?>
  <?php actionForm($entry, 'restore', 'Khôi phục', $csrf); actionForm($entry, 'delete', 'Xóa vĩnh viễn', $csrf, 'Xóa vĩnh viễn bài viết này?'); ?>
<?php else: ?>
  <a href="<?=h(editorUrl($entry['id']))?>">Sửa</a> · <a href="<?=h(postUrl($entry) . '?preview=1')?>" target="_blank" rel="noopener">Xem trước</a>
<?php endif; ?>
</td></tr>
<?php endforeach; ?>
</tbody></table></div></section>
<?php endif; ?>
</main></div></body></html>

