<?php
declare(strict_types=1);

const SITE = 'https://vietnamskilled.org';
const BRAND = 'VietnamSkilled';
const WEBROOT = '/var/www/vietnamskilled.org/public_html';
const SITE_DATA = '/var/www/vietnamskilled.org/cms-data';
const ZONE = 'Asia/Ho_Chi_Minh';

function h($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function textField($value, int $length): string {
    return mb_substr(trim(is_string($value) ? $value : ''), 0, $length);
}

function slug(string $value): string {
    $value = strtr(mb_strtolower($value), ['đ' => 'd']);
    $plain = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
    return trim(substr(preg_replace('/[^a-z0-9]+/', '-', strtolower($plain ?: $value)), 0, 110), '-');
}

function sitePages(): array {
    return [
        '/' => ['index.html', 'Trang chủ', 'Tư vấn du học nghề, đào tạo tiếng Đức, công nhận bằng cấp và hỗ trợ hồ sơ cùng VietnamSkilled.'],
        '/ve-chung-toi/' => ['ve-chung-toi/index.html', 'Về chúng tôi', 'Tìm hiểu về đội ngũ và sứ mệnh của VietnamSkilled.'],
        '/dich-vu/' => ['dich-vu/index.html', 'Dịch vụ', 'Các dịch vụ tư vấn du học nghề, việc làm và định cư tại Đức.'],
        '/dich-vu/du-hoc-nghe/' => ['dich-vu/du-hoc-nghe/index.html', 'Du học nghề Đức', 'Tìm hiểu chương trình du học nghề tại Đức và lộ trình chuẩn bị hồ sơ.'],
        '/dich-vu/cong-nhan-bang-cap/' => ['dich-vu/cong-nhan-bang-cap/index.html', 'Công nhận bằng cấp', 'Tư vấn công nhận bằng cấp và cơ hội làm việc tại Đức.'],
        '/dich-vu/khoa-luyen-phong-van/' => ['dich-vu/khoa-luyen-phong-van/index.html', 'Khóa luyện phỏng vấn', 'Chuẩn bị cho phỏng vấn tuyển dụng cùng VietnamSkilled.'],
        '/dich-vu/tuyen-dung-doi-tac/' => ['dich-vu/tuyen-dung-doi-tac/index.html', 'Tuyển dụng đối tác', 'Kết nối doanh nghiệp Đức với ứng viên Việt Nam.'],
        '/chuong-trinh-va-thi-truong/' => ['chuong-trinh-va-thi-truong/index.html', 'Chương trình & Thị trường', 'Các chương trình du học và việc làm quốc tế của VietnamSkilled.'],
        '/hoc-bong/' => ['hoc-bong/index.html', 'Học bổng', 'Thông tin học bổng du học Đức của VietnamSkilled.'],
        '/tin-tuc/' => [null, 'Tin tức & Cập nhật', 'Tin tức, hướng dẫn và cập nhật về du học nghề, việc làm và cuộc sống tại Đức.'],
        '/lien-he/' => ['lien-he/index.html', 'Liên hệ', 'Liên hệ với VietnamSkilled để được tư vấn lộ trình phù hợp.'],
        '/faq.html' => ['faq.html', 'Câu hỏi thường gặp', 'Giải đáp câu hỏi thường gặp về dịch vụ và hồ sơ tại VietnamSkilled.'],
        '/datenschutzerklaerung.html' => ['datenschutzerklaerung.html', 'Chính sách bảo mật', 'Chính sách bảo mật của VietnamSkilled.'],
        '/impressum.html' => ['impressum.html', 'Thông tin pháp lý', 'Thông tin pháp lý và liên hệ của VietnamSkilled.'],
        '/kontakt.html' => ['kontakt.html', 'Liên hệ', 'Thông tin liên hệ và tư vấn của VietnamSkilled.'],
        '/leistungen.html' => ['leistungen.html', 'Dịch vụ', 'Các dịch vụ của VietnamSkilled.'],
        '/plattform.html' => ['plattform.html', 'Nền tảng', 'Thông tin về nền tảng và chương trình quốc tế của VietnamSkilled.'],
        '/reference.html' => ['reference.html', 'Kinh nghiệm', 'Các kinh nghiệm và bài viết tham khảo của VietnamSkilled.'],
        '/talente.html' => ['talente.html', 'Ứng viên', 'Thông tin dành cho ứng viên VietnamSkilled.'],
        '/uber-uns.html' => ['uber-uns.html', 'Về chúng tôi', 'Giới thiệu VietnamSkilled.'],
        '/wissenswertes.html' => ['wissenswertes.html', 'Kiến thức hữu ích', 'Kiến thức về du học nghề và nhập cư tại Đức.'],
    ];
}

function state(): array {
    $file = SITE_DATA . '/content.json';
    $defaults = ['posts' => [], 'redirects' => [], 'seo' => [], 'pages' => []];
    if (!is_file($file)) return $defaults;
    $data = json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($data)) throw new RuntimeException('Dữ liệu nội dung không hợp lệ.');
    return $data + $defaults;
}

function changeState(callable $edit) {
    $lock = fopen(SITE_DATA . '/content.lock', 'c');
    if (!$lock || !flock($lock, LOCK_EX)) throw new RuntimeException('Không khóa được dữ liệu.');
    $temp = null;
    try {
        $data = state();
        $result = $edit($data);
        $temp = tempnam(SITE_DATA, 'save-');
        if ($temp === false) throw new RuntimeException('Không tạo được bản lưu.');
        $json = json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        if (file_put_contents($temp, $json) !== strlen($json) || !rename($temp, SITE_DATA . '/content.json')) {
            throw new RuntimeException('Không lưu được dữ liệu.');
        }
        return $result;
    } finally {
        if ($temp && is_file($temp)) unlink($temp);
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}

function isPublic(array $post): bool {
    return in_array($post['status'], ['published', 'scheduled'], true) && $post['publish_at'] <= time();
}

function publicPosts(array $data): array {
    $posts = array_values(array_filter($data['posts'], 'isPublic'));
    usort($posts, function ($a, $b) {
        return $b['publish_at'] <=> $a['publish_at'] ?: strcmp($b['id'], $a['id']);
    });
    return $posts;
}

function postUrl(array $post): string {
    return '/tin-tuc/' . $post['slug'] . '/';
}

function excerpt(array $post): string {
    if (!empty($post['excerpt'])) return $post['excerpt'];
    $plain = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags(str_replace(['</p>', '</li>', '</h2>'], ' ', $post['body'])), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    return mb_strlen($plain) > 165 ? mb_substr($plain, 0, 162) . '…' : $plain;
}

function siteDate(int $date, string $format): string {
    return (new DateTimeImmutable('@' . $date))->setTimezone(new DateTimeZone(ZONE))->format($format);
}

function validUrl($value, bool $localOnly = false): string {
    $url = trim(is_string($value) ? $value : '');
    if ($url === '' || preg_match('/[\x00-\x20\x7f\\\\]/', $url)) return '';
    if (substr($url, 0, 1) === '/' && substr($url, 0, 2) !== '//') return $url;
    if (!$localOnly && substr($url, 0, 1) === '#') return $url;
    if (!filter_var($url, FILTER_VALIDATE_URL)) return '';
    $scheme = strtolower((string)parse_url($url, PHP_URL_SCHEME));
    $host = strtolower((string)parse_url($url, PHP_URL_HOST));
    if (!in_array($scheme, ['https', 'http'], true) || !$host || parse_url($url, PHP_URL_USER)) return '';
    if ($localOnly && ($host !== 'vietnamskilled.org' || $scheme !== 'https')) return '';
    return $url;
}

function absoluteUrl($value, bool $localOnly = false): string {
    $url = validUrl($value, $localOnly);
    return $url !== '' && $url[0] === '/' ? SITE . $url : ($url !== '' && $url[0] !== '#' ? $url : '');
}

function cleanBody(string $html): string {
    $allowed = array_fill_keys(explode(' ', 'p br h2 h3 h4 h5 h6 strong b em i u s del blockquote ul ol li a img figure figcaption table thead tbody tfoot tr td th hr pre code sub sup div span'), true);
    $attrs = ['a' => ['href', 'title'], 'img' => ['src', 'alt', 'title', 'width', 'height'], 'td' => ['rowspan', 'colspan'], 'th' => ['rowspan', 'colspan', 'scope'], 'ol' => ['start']];
    $dom = new DOMDocument();
    $dom->loadHTML('<!doctype html><html><head><meta charset="UTF-8"></head><body>' . $html . '</body></html>', LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
    $body = $dom->getElementsByTagName('body')->item(0);
    $clean = function (DOMNode $parent) use (&$clean, $allowed, $attrs): void {
        foreach (iterator_to_array($parent->childNodes) as $node) {
            if ($node instanceof DOMText) continue;
            if (!$node instanceof DOMElement || !isset($allowed[$node->tagName])) {
                if ($node instanceof DOMElement && !in_array($node->tagName, ['script', 'style', 'iframe', 'object', 'svg', 'math', 'form', 'template'], true)) {
                    $clean($node);
                    while ($node->firstChild) $parent->insertBefore($node->firstChild, $node);
                }
                $parent->removeChild($node);
                continue;
            }
            $tag = $node->tagName;
            foreach (iterator_to_array($node->attributes) as $attr) {
                $name = $attr->name;
                if (!in_array($name, $attrs[$tag] ?? [], true) ||
                    (in_array($name, ['href', 'src'], true) && !validUrl($attr->value)) ||
                    (in_array($name, ['width', 'height', 'rowspan', 'colspan', 'start'], true) && !preg_match('/^[1-9][0-9]{0,3}$/D', $attr->value))) {
                    $node->removeAttribute($name);
                }
            }
            if ($tag === 'img') {
                if (!$node->hasAttribute('src')) { $parent->removeChild($node); continue; }
                $node->setAttribute('loading', 'lazy');
            }
            $clean($node);
        }
    };
    if (!$body) return '';
    $clean($body);
    $output = '';
    foreach ($body->childNodes as $node) $output .= $dom->saveHTML($node);
    return trim($output);
}

function cmsSession(): void {
    if (session_status() === PHP_SESSION_NONE) session_start();
}

function redirectTo(string $path, int $status = 303): void {
    header('Location: ' . $path, true, $status);
    exit;
}

function listTerms($input): array {
    $terms = preg_split('/\s*,\s*/u', textField($input, 800), -1, PREG_SPLIT_NO_EMPTY);
    return array_values(array_unique(array_slice(array_map(function ($term) {
        return textField($term, 60);
    }, $terms ?: []), 0, 20)));
}

function seoFields(array $input): array {
    $title = textField($input['seo_title'] ?? '', 180);
    $description = textField($input['seo_description'] ?? '', 400);
    $canonical = absoluteUrl($input['canonical'] ?? '', true);
    $image = absoluteUrl($input['seo_image'] ?? '');
    if (!empty($input['canonical']) && !$canonical) throw new DomainException('Canonical phải là URL HTTPS trên vietnamskilled.org.');
    if (!empty($input['seo_image']) && !$image) throw new DomainException('Đường dẫn ảnh chia sẻ không hợp lệ.');
    return compact('title', 'description', 'canonical', 'image') + ['noindex' => !empty($input['noindex'])];
}

