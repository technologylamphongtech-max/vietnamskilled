<?php
declare(strict_types=1);
require __DIR__ . '/common.php';

const NEWS_ADMIN = '/admin/tin-tuc/';
const SEO_ADMIN = '/admin/seo/';
$seoPage = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) === SEO_ADMIN;
cmsSession();
header('X-Robots-Tag: noindex, nofollow');
header('X-Frame-Options: SAMEORIGIN');
header('Cache-Control: no-store, private');
header('Content-Type: text/html; charset=UTF-8');

if (empty($_SESSION['vs_auth'])) redirectTo('/admin/', 302);
if (empty($_SESSION['vs_cms_csrf'])) $_SESSION['vs_cms_csrf'] = bin2hex(random_bytes(32));
$csrf = $_SESSION['vs_cms_csrf'];
$flash = $_SESSION['vs_cms_flash'] ?? '';
unset($_SESSION['vs_cms_flash']);
$error = '';
$input = null;

function notifySave(string $notice, string $url): void {
    $_SESSION['vs_cms_flash'] = $notice;
    redirectTo($url);
}

function editorUrl(string $id): string {
    return NEWS_ADMIN . '?id=' . rawurlencode($id);
}

function checkVersion(array $post): void {
    if ((string)$post['version'] !== ($_POST['version'] ?? null)) {
        throw new DomainException('Bài này đã được thay đổi. Hãy mở lại phiên bản mới nhất trước khi lưu.');
    }
}

function addRevision(array $post): array {
    $versions = $post['revisions'] ?? [];
    unset($post['revisions']);
    array_unshift($versions, $post);
    return array_slice($versions, 0, 15);
}

function reserveSlug(array &$data, string $id, string $slug): void {
    if ($slug === 'media') throw new DomainException('Đường dẫn này dành cho ảnh.');
    foreach ($data['posts'] as $post) {
        if ($post['id'] !== $id && $post['slug'] === $slug) throw new DomainException('Đường dẫn này đã được dùng.');
    }
    if (isset($data['redirects'][$slug]) && $data['redirects'][$slug] !== $id) {
        throw new DomainException('Đường dẫn này đang chuyển hướng tới một bài khác.');
    }
    unset($data['redirects'][$slug]);
}

function imageUpload(): void {
    header('Content-Type: application/json; charset=UTF-8');
    $file = $_FILES['file'] ?? [];
    try {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
            throw new DomainException('Không nhận được ảnh hoặc ảnh vượt giới hạn máy chủ.');
        }
        if ($file['size'] > 8 * 1024 * 1024) throw new DomainException('Ảnh phải nhỏ hơn 8 MB.');
        $info = @getimagesize($file['tmp_name']);
        $types = ['image/jpeg'=>'jpg', 'image/png'=>'png', 'image/webp'=>'webp', 'image/gif'=>'gif', 'image/avif'=>'avif'];
        $mime = $info['mime'] ?? '';
        if (!isset($types[$mime]) || $info[0] < 1 || $info[1] < 1 || $info[0] * $info[1] > 45000000) {
            throw new DomainException('Ảnh phải là JPG, PNG, WebP, GIF hoặc AVIF hợp lệ, tối đa 45 megapixel.');
        }
        $name = bin2hex(random_bytes(16)) . '.' . $types[$mime];
        $path = SITE_DATA . '/media/' . $name;
        if (!move_uploaded_file($file['tmp_name'], $path)) throw new RuntimeException('Không lưu được ảnh.');
        chmod($path, 0640);
        echo json_encode(['location' => '/tin-tuc/media/' . $name], JSON_UNESCAPED_SLASHES);
    } catch (Throwable $e) {
        http_response_code($e instanceof DomainException ? 422 : 500);
        echo json_encode(['error' => $e instanceof DomainException ? $e->getMessage() : 'Không lưu được ảnh.'], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($csrf, is_string($_POST['csrf'] ?? null) ? $_POST['csrf'] : '')) {
        http_response_code(403);
        exit('Phiên làm việc đã thay đổi. Hãy tải lại trang.');
    }
    $action = $_POST['action'] ?? '';
    if ($action === 'upload' && !$seoPage) imageUpload();
    try {
        if ($seoPage && $action === 'seo') {
            $page = textField($_POST['page'] ?? '', 200);
            if ($page === 'global') {
                $brand = textField($_POST['brand'] ?? '', 80) ?: BRAND;
                $title = textField($_POST['seo_title'] ?? '', 180);
                $description = textField($_POST['seo_description'] ?? '', 400);
                $image = absoluteUrl($_POST['seo_image'] ?? '');
                $verification = textField($_POST['verification'] ?? '', 150);
                if (!empty($_POST['seo_image']) && !$image) throw new DomainException('Đường dẫn ảnh chia sẻ không hợp lệ.');
                if ($verification && !preg_match('/^[A-Za-z0-9_-]+$/D', $verification)) throw new DomainException('Chỉ nhập giá trị content của mã xác minh.');
                changeState(function (&$data) use ($brand, $title, $description, $image, $verification) {
                    $data['seo'] = compact('brand', 'title', 'description', 'image', 'verification');
                });
            } elseif (isset(sitePages()[$page])) {
                $settings = seoFields($_POST);
                changeState(function (&$data) use ($page, $settings) { $data['pages'][$page] = $settings; });
            } else {
                throw new DomainException('Trang SEO không hợp lệ.');
            }
            notifySave('Đã lưu cấu hình SEO.', SEO_ADMIN . '?page=' . rawurlencode($page));
        }
        if (!$seoPage && $action === 'save') {
            $id = textField($_POST['id'] ?? '', 40);
            $title = textField($_POST['title'] ?? '', 240);
            if ($title === '') throw new DomainException('Hãy nhập tiêu đề bài viết.');
            $slug = slug(textField($_POST['slug'] ?? '', 240) ?: $title);
            if ($slug === '') throw new DomainException('Hãy nhập đường dẫn bài viết gồm chữ hoặc số.');
            $body = $_POST['body'] ?? '';
            if (!is_string($body) || strlen($body) > 180000) throw new DomainException('Nội dung vượt 180 KB. Hãy tải ảnh qua nút Chèn ảnh.');
            $body = cleanBody($body);
            $status = textField($_POST['status'] ?? '', 15);
            if (!in_array($status, ['draft', 'published', 'scheduled'], true)) throw new DomainException('Trạng thái không hợp lệ.');
            if ($status !== 'draft' && trim(strip_tags($body)) === '' && strpos($body, '<img ') === false) throw new DomainException('Hãy nhập nội dung trước khi đăng bài.');
            $publishInput = textField($_POST['publish_at'] ?? '', 40);
            $publish_at = time();
            if ($publishInput !== '') {
                $date = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $publishInput, new DateTimeZone(ZONE));
                if (!$date || $date->format('Y-m-d\TH:i') !== $publishInput) throw new DomainException('Ngày giờ xuất bản không hợp lệ.');
                $publish_at = $date->getTimestamp();
            }
            if ($status === 'scheduled' && ($publishInput === '' || $publish_at <= time())) throw new DomainException('Chọn thời điểm trong tương lai để lên lịch.');
            if ($status === 'published' && $publish_at > time()) $status = 'scheduled';
            $excerpt = textField($_POST['excerpt'] ?? '', 500);
            $categories = listTerms($_POST['categories'] ?? '');
            $tags = listTerms($_POST['tags'] ?? '');
            $image = validUrl($_POST['image'] ?? '');
            if (!empty($_POST['image']) && (!$image || $image[0] === '#')) throw new DomainException('Đường dẫn ảnh đại diện không hợp lệ.');
            $image_alt = textField($_POST['image_alt'] ?? '', 250);
            $seo = seoFields($_POST);
            $new = compact('title', 'slug', 'body', 'status', 'publish_at', 'excerpt', 'categories', 'tags', 'image', 'image_alt', 'seo');
            $id = changeState(function (&$data) use ($id, $new) {
                $old = $id ? ($data['posts'][$id] ?? null) : null;
                if ($id && (!$old || $old['status'] === 'trash')) throw new DomainException('Bài viết không còn mở để chỉnh sửa.');
                if ($old) checkVersion($old);
                $id = $id ?: bin2hex(random_bytes(12));
                reserveSlug($data, $id, $new['slug']);
                if ($old && $old['slug'] !== $new['slug']) $data['redirects'][$old['slug']] = $id;
                $data['posts'][$id] = $new + ['id'=>$id, 'created'=>$old['created'] ?? time(), 'updated'=>time(),
                    'version'=>($old['version'] ?? 0) + 1, 'revisions'=>$old ? addRevision($old) : []];
                return $id;
            });
            notifySave(['draft'=>'Đã lưu bản nháp.', 'scheduled'=>'Đã lên lịch xuất bản.', 'published'=>'Đã xuất bản bài viết.'][$status], editorUrl($id));
        }
        if (!$seoPage && in_array($action, ['trash', 'restore', 'delete', 'revision'], true)) {
            $id = textField($_POST['id'] ?? '', 40);
            changeState(function (&$data) use ($action, $id) {
                $post = $data['posts'][$id] ?? null;
                if (!$post) throw new DomainException('Không tìm thấy bài viết.');
                checkVersion($post);
                if ($action === 'delete' && $post['status'] === 'trash') {
                    unset($data['posts'][$id]);
                    $data['redirects'] = array_filter($data['redirects'], function ($target) use ($id) { return $target !== $id; });
                    return;
                }
                if ($action === 'trash' && $post['status'] !== 'trash') {
                    $post['status'] = 'trash';
                } elseif ($action === 'restore' && $post['status'] === 'trash') {
                    $post['status'] = 'draft';
                } elseif ($action === 'revision' && $post['status'] !== 'trash') {
                    $number = filter_var($_POST['revision'] ?? '', FILTER_VALIDATE_INT);
                    $revision = $number === false ? null : ($post['revisions'][$number] ?? null);
                    if (!$revision) throw new DomainException('Không tìm thấy phiên bản.');
                    $history = addRevision($post);
                    foreach (['title', 'body', 'excerpt', 'categories', 'tags', 'image', 'image_alt', 'seo'] as $key) $post[$key] = $revision[$key];
                    $post['revisions'] = $history;
                } else {
                    throw new DomainException('Trạng thái không cho phép thao tác này.');
                }
                $post['updated'] = time();
                $post['version']++;
                $data['posts'][$id] = $post;
            });
            $messages = ['trash'=>'Đã chuyển vào thùng rác.', 'restore'=>'Đã khôi phục thành bản nháp.',
                'delete'=>'Đã xóa bài viết.', 'revision'=>'Đã khôi phục nội dung phiên bản đã chọn.'];
            notifySave($messages[$action], in_array($action, ['revision', 'restore'], true) ? editorUrl($id) : NEWS_ADMIN);
        }
        throw new DomainException('Thao tác không hợp lệ.');
    } catch (DomainException $e) {
        http_response_code(422);
        $error = $e->getMessage();
        $input = $_POST;
    } catch (Throwable $e) {
        http_response_code(500);
        error_log('VietnamSkilled CMS: ' . $e->getMessage());
        $error = 'Lỗi khi lưu. Hãy thử lại.';
        $input = $_POST;
    }
}

$data = state();
function statusLabel(array $post): string {
    return isPublic($post) ? 'Đã xuất bản' : (['draft'=>'Bản nháp', 'scheduled'=>'Đã lên lịch', 'trash'=>'Thùng rác'][$post['status']] ?? 'Bản nháp');
}
function hidden(string $name, $value): void {
    echo '<input type="hidden" name="' . h($name) . '" value="' . h($value) . '">';
}
function seoInputs(array $seo): void { ?>
    <label>Tiêu đề SEO<input name="seo_title" maxlength="180" value="<?=h($seo['title'] ?? '')?>" placeholder="Để trống để dùng tiêu đề mặc định"></label>
    <label>Mô tả SEO<textarea name="seo_description" rows="3" maxlength="400" placeholder="Mô tả ngắn cho kết quả tìm kiếm"><?=h($seo['description'] ?? '')?></textarea></label>
    <label>Canonical<input name="canonical" value="<?=h($seo['canonical'] ?? '')?>" placeholder="Để trống để dùng URL hiện tại"></label>
    <label>Ảnh khi chia sẻ<input name="seo_image" value="<?=h($seo['image'] ?? '')?>" placeholder="/images/... hoặc https://..."></label>
    <label class="check"><input type="checkbox" name="noindex" value="1" <?=!empty($seo['noindex']) ? 'checked' : ''?>> Yêu cầu công cụ tìm kiếm không lập chỉ mục</label>
<?php }
function actionForm(array $post, string $action, string $label, string $csrf, string $confirm = '', ?int $revision = null): void { ?>
    <form class="inline" method="post" action="<?=h(NEWS_ADMIN)?>" <?= $confirm ? 'data-confirm="' . h($confirm) . '"' : '' ?>>
        <?php hidden('csrf', $csrf); hidden('action', $action); hidden('id', $post['id']); hidden('version', $post['version']); if ($revision !== null) hidden('revision', $revision); ?>
        <button type="submit" class="<?=in_array($action, ['trash', 'delete'], true) ? 'danger' : 'secondary'?>"><?=h($label)?></button>
    </form>
<?php }

require __DIR__ . '/admin-view.php';

