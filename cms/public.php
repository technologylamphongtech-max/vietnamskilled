<?php
declare(strict_types=1);
require __DIR__ . '/common.php';

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$pages = sitePages();
$data = state();
$posts = publicPosts($data);

function meta(array $data, string $path, string $title, string $description, array $overrides = [], bool $article = false): string {
    $defaults = $data['seo'];
    $settings = $overrides + ($data['pages'][$path] ?? []);
    $brand = $defaults['brand'] ?? BRAND;
    $name = ($settings['title'] ?? '') ?: $title;
    $description = ($settings['description'] ?? '') ?: ($description ?: ($defaults['description'] ?? ''));
    $canonical = ($settings['canonical'] ?? '') ?: SITE . $path;
    $image = ($settings['image'] ?? '') ?: ($defaults['image'] ?? '');
    $robots = !empty($settings['noindex']) ? 'noindex, follow' : 'index, follow, max-image-preview:large';
    $tags = '<title>' . h($name) . '</title>' . "\n"
        . '<meta name="description" content="' . h($description) . '">' . "\n"
        . '<link rel="canonical" href="' . h($canonical) . '">' . "\n"
        . '<meta name="robots" content="' . h($robots) . '">' . "\n";
    foreach (['og:site_name' => $brand, 'og:type' => $article ? 'article' : 'website', 'og:title' => $name,
        'og:description' => $description, 'og:url' => $canonical, 'og:locale' => 'vi_VN', 'og:image' => $image] as $key => $value) {
        if ($value !== '') $tags .= '<meta property="' . h($key) . '" content="' . h($value) . '">' . "\n";
    }
    foreach (['twitter:card' => $image ? 'summary_large_image' : 'summary', 'twitter:title' => $name,
        'twitter:description' => $description, 'twitter:image' => $image] as $key => $value) {
        if ($value !== '') $tags .= '<meta name="' . h($key) . '" content="' . h($value) . '">' . "\n";
    }
    if (!empty($defaults['verification'])) {
        $tags .= '<meta name="google-site-verification" content="' . h($defaults['verification']) . '">' . "\n";
    }
    return $tags;
}

function schema(array $values): string {
    return '<script type="application/ld+json">' . json_encode(
        ['@context' => 'https://schema.org'] + $values,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_THROW_ON_ERROR
    ) . "</script>\n";
}

function displayPage(string $head, string $body): void {
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!doctype html><html lang="vi"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<link rel="icon" href="/logo.png"><link rel="stylesheet" href="/_header.css?v=24">'
        . '<link rel="stylesheet" href="/_footer.css?v=1"><link rel="stylesheet" href="/cms-assets/site.css?v=1">'
        . $head . '</head><body><div id="site-header"></div>' . $body
        . '<div id="site-footer"></div><script src="/_header.js?v=16"></script></body></html>';
    exit;
}

function postCards(array $posts, bool $home = false): string {
    if (!$posts) return '<p class="news-empty">Chưa có bài viết được xuất bản.</p>';
    $cards = '';
    foreach ($posts as $post) {
        $date = siteDate($post['publish_at'], 'd/m/Y');
        $label = $post['categories'][0] ?? 'Tin tức';
        $image = $post['image'] ?? '';
        $visual = $image ? '<img src="' . h($image) . '" alt="' . h($post['image_alt'] ?? '') . '" loading="lazy">' : '<span aria-hidden="true">VS</span>';
        $cards .= '<a class="news-card" href="' . h(postUrl($post)) . '"><div class="news-th nt1">'
            . $visual . '<span class="news-cat">' . h($label) . '</span></div><div class="news-con">'
            . '<div class="news-title">' . h($post['title']) . '</div><div class="news-ex">' . h(excerpt($post))
            . '</div><div class="news-mt">' . h($date) . '</div></div></a>';
    }
    return $cards;
}

if ($path === '/robots.txt') {
    header('Content-Type: text/plain; charset=UTF-8');
    echo "User-agent: *\nAllow: /\nDisallow: /admin/\nSitemap: " . SITE . "/sitemap.xml\n";
    exit;
}

if ($path === '/sitemap.xml') {
    header('Content-Type: application/xml; charset=UTF-8');
    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
    $add = function (string $url, ?int $lastmod = null): void {
        echo '<url><loc>' . htmlspecialchars($url, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</loc>';
        if ($lastmod) echo '<lastmod>' . gmdate('Y-m-d', $lastmod) . '</lastmod>';
        echo "</url>\n";
    };
    foreach ($pages as $url => [$file]) {
        $seo = $data['pages'][$url] ?? [];
        if (empty($seo['noindex']) && (empty($seo['canonical']) || $seo['canonical'] === SITE . $url)) {
            $add(SITE . $url, $file && is_file(WEBROOT . '/' . $file) ? filemtime(WEBROOT . '/' . $file) : null);
        }
    }
    foreach ($posts as $post) {
        $url = SITE . postUrl($post);
        if (empty($post['seo']['noindex']) && (empty($post['seo']['canonical']) || $post['seo']['canonical'] === $url)) {
            $add($url, max($post['updated'], $post['publish_at']));
        }
    }
    echo '</urlset>';
    exit;
}

if (preg_match('~^/tin-tuc/media/([a-f0-9]{32}\.(?:jpg|png|webp|gif|avif))$~D', $path, $match)) {
    $file = SITE_DATA . '/media/' . $match[1];
    if (!is_file($file)) { http_response_code(404); exit; }
    $types = ['jpg' => 'jpeg', 'png' => 'png', 'webp' => 'webp', 'gif' => 'gif', 'avif' => 'avif'];
    header('Content-Type: image/' . $types[pathinfo($file, PATHINFO_EXTENSION)]);
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: public, max-age=31536000, immutable');
    header('Content-Length: ' . filesize($file));
    readfile($file);
    exit;
}

if ($path === '/tin-tuc/') {
    $category = textField($_GET['chuyen-muc'] ?? '', 60);
    $tag = textField($_GET['the'] ?? '', 60);
    $search = textField($_GET['q'] ?? '', 120);
    $categories = [];
    foreach ($posts as $post) foreach ($post['categories'] as $term) $categories[$term] = true;
    $posts = array_values(array_filter($posts, function ($post) use ($category, $tag, $search) {
        return (!$category || in_array($category, $post['categories'], true))
            && (!$tag || in_array($tag, $post['tags'], true))
            && (!$search || mb_stripos($post['title'] . ' ' . excerpt($post) . ' ' . strip_tags($post['body']), $search) !== false);
    }));
    $count = max(1, (int)ceil(count($posts) / 12));
    $page = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 1;
    if ($page > $count) {
        http_response_code(404);
        displayPage('<title>Không tìm thấy trang</title><meta name="robots" content="noindex">', '<main class="vs-news"><h1>Không tìm thấy trang</h1><p><a href="/tin-tuc/">Về Tin tức &amp; Cập nhật</a></p></main>');
    }
    $body = '<main class="vs-news"><div class="news-kicker">VietnamSkilled</div><h1>Tin tức &amp; Cập nhật</h1>'
        . '<p class="news-intro">' . h($pages['/tin-tuc/'][2]) . '</p><form class="news-search" action="/tin-tuc/">'
        . '<label for="news-query">Tìm bài viết</label><input id="news-query" name="q" value="' . h($search) . '" placeholder="Nhập từ khóa…"><button>Tìm</button></form>';
    if ($categories) {
        $body .= '<nav class="news-terms" aria-label="Chuyên mục"><a href="/tin-tuc/">Tất cả bài viết</a>';
        foreach ($categories as $term => $_) $body .= '<a href="/tin-tuc/?chuyen-muc=' . rawurlencode((string)$term) . '">' . h($term) . '</a>';
        $body .= '</nav>';
    }
    if ($search || $category || $tag) $body .= '<p>Kết quả cho <strong>' . h($search ?: ($category ?: $tag)) . '</strong> · <a href="/tin-tuc/">Xóa bộ lọc</a></p>';
    $body .= '<div class="news-grid">' . postCards(array_slice($posts, ($page - 1) * 12, 12)) . '</div>';
    if ($count > 1) {
        $params = array_filter(['q' => $search, 'chuyen-muc' => $category, 'the' => $tag]);
        $body .= '<nav class="news-terms" aria-label="Trang kết quả">';
        foreach ([$page - 1 => 'Trang trước', $page + 1 => 'Trang sau'] as $num => $label) {
            if ($num < 1 || $num > $count) continue;
            $body .= '<a href="/tin-tuc/?' . h(http_build_query($params + ['page' => $num])) . '">' . h($label) . '</a>';
        }
        $body .= '</nav>';
    }
    $seo = $search || $category || $tag ? ['noindex' => true] : ($page > 1 ? ['canonical' => SITE . '/tin-tuc/?page=' . $page] : []);
    displayPage(meta($data, $path, 'Tin tức & Cập nhật | VietnamSkilled', $pages[$path][2], $seo), $body . '</main>');
}

if (preg_match('~^/tin-tuc/([a-z0-9-]{1,110})(/?)$~D', $path, $match)) {
    $id = $data['redirects'][$match[1]] ?? '';
    foreach ($data['posts'] as $candidate) {
        if ($candidate['slug'] === $match[1]) { $id = $candidate['id']; break; }
    }
    $post = $data['posts'][$id] ?? null;
    $preview = isset($_GET['preview']);
    if ($preview) {
        cmsSession();
        $preview = !empty($_SESSION['vs_auth']);
        session_write_close();
        header('Cache-Control: private, no-store');
    }
    if ($post && (isPublic($post) || ($preview && $post['status'] !== 'trash'))) {
        if (!$preview && postUrl($post) !== $path) redirectTo(postUrl($post), 301);
        $brand = $data['seo']['brand'] ?? BRAND;
        $settings = $post['seo'];
        if (empty($settings['image'])) $settings['image'] = absoluteUrl($post['image']);
        if ($preview) $settings['noindex'] = true;
        $head = meta($data, postUrl($post), $post['title'] . ' | ' . $brand, excerpt($post), $settings, true);
        if (!$preview) {
            $article = ['@type' => 'BlogPosting', 'headline' => $post['title'], 'description' => excerpt($post),
                'datePublished' => gmdate(DATE_ATOM, $post['publish_at']), 'dateModified' => gmdate(DATE_ATOM, max($post['updated'], $post['publish_at'])),
                'mainEntityOfPage' => ($settings['canonical'] ?? '') ?: SITE . postUrl($post),
                'author' => ['@type' => 'Organization', 'name' => $brand, 'url' => SITE],
                'publisher' => ['@type' => 'Organization', 'name' => $brand]];
            if (!empty($settings['image'])) $article['image'] = [$settings['image']];
            $head .= schema($article);
        }
        $body = '<main class="vs-news vs-article"><p><a href="/tin-tuc/">← Tin tức &amp; Cập nhật</a></p>'
            . ($preview ? '<p class="preview-notice">Bản xem trước dành cho quản trị viên</p>' : '')
            . '<article><h1>' . h($post['title']) . '</h1><p class="news-meta"><time datetime="' . gmdate(DATE_ATOM, $post['publish_at'])
            . '">' . siteDate($post['publish_at'], 'd/m/Y') . '</time></p>';
        if ($post['excerpt']) $body .= '<p class="news-intro">' . h($post['excerpt']) . '</p>';
        if ($post['image']) $body .= '<img class="article-cover" src="' . h($post['image']) . '" alt="' . h($post['image_alt']) . '">';
        $body .= '<div class="article-body">' . $post['body'] . '</div><nav class="news-terms" aria-label="Chuyên mục và thẻ">';
        foreach (['categories' => 'chuyen-muc', 'tags' => 'the'] as $key => $filter) {
            foreach ($post[$key] as $term) $body .= '<a href="/tin-tuc/?' . $filter . '=' . rawurlencode($term) . '">' . h($term) . '</a>';
        }
        displayPage($head, $body . '</nav></article></main>');
    }
}

if (isset($pages[$path]) && $pages[$path][0]) {
    [$file, $label, $description] = $pages[$path];
    $html = file_get_contents(WEBROOT . '/' . $file);
    if ($html === false) { http_response_code(500); exit; }
    $brand = $data['seo']['brand'] ?? BRAND;
    $title = ($path === '/' && !empty($data['seo']['title'])) ? $data['seo']['title'] : $label . ' | ' . $brand;
    if ($path === '/' && !empty($data['seo']['description'])) $description = $data['seo']['description'];
    $head = meta($data, $path, $title, $description);
    if ($path === '/') {
        $head .= schema(['@type' => 'WebSite', 'name' => $brand, 'url' => SITE . '/']);
        $head .= '<link rel="stylesheet" href="/cms-assets/home.css?v=1">' . "\n";
        $html = preg_replace('~(<div class="news-grid">).*?(</div>\s*</div>\s*</section>\s*<!-- P14:)~s',
            '$1' . str_replace(['\\', '$'], ['\\\\', '\$'], postCards(array_slice($posts, 0, 3), true)) . '$2', $html, 1);
        $html = str_replace('class="view-all" href="#" data-i18n="news_view_all"', 'class="view-all" href="/tin-tuc/" data-i18n="news_view_all"', $html);
    }
    $html = preg_replace_callback('~<head\b([^>]*)>(.*?)</head>~is', function ($match) use ($head) {
        $existing = preg_replace('~<title\b[^>]*>.*?</title>|<meta\b(?=[^>]*\b(?:name|property)\s*=\s*["\x27](?:description|robots|google-site-verification|og:[^"\x27]+|twitter:[^"\x27]+)["\x27])[^>]*>|<link\b(?=[^>]*\brel\s*=\s*["\x27]canonical["\x27])[^>]*>~is', '', $match[2]);
        return '<head' . $match[1] . '>' . $existing . "\n" . $head . '</head>';
    }, $html, 1);
    header('Content-Type: text/html; charset=UTF-8');
    header('Cache-Control: no-cache');
    echo $html;
    exit;
}
http_response_code(404);
displayPage('<title>Không tìm thấy bài viết | VietnamSkilled</title><meta name="robots" content="noindex">', '<main class="vs-news"><h1>Không tìm thấy bài viết</h1><p><a href="/tin-tuc/">Về Tin tức &amp; Cập nhật</a></p></main>');

