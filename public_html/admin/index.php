<?php
// ================================================================
//  VietnamSkilled Admin Panel
//  Single-file PHP CMS — edit content only, no section add/remove
// ================================================================
session_start();
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Content-Type: text/html; charset=UTF-8');

$configFile = dirname(__DIR__, 2) . '/config.php';
$config = is_readable($configFile) ? require $configFile : [];
$adminPasswordHash = $config['admin_password_hash'] ?? '';
$ROOT = dirname(__DIR__) . '/';
$BCK  = dirname(dirname(__DIR__)) . '/vs_backups/';

// ── Auth ─────────────────────────────────────────────────────────
$auth = !empty($_SESSION['vs_auth']);
$err  = '';

if (!$auth && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['_a'] ?? '') === 'login') {
    if (is_string($_POST['pw'] ?? null) && $adminPasswordHash && password_verify($_POST['pw'], $adminPasswordHash)) {
        session_regenerate_id(true);
        $_SESSION['vs_auth'] = 1;
        $auth = true;
    } else {
        $err = 'Mật khẩu không đúng.';
    }
}
if (isset($_GET['out'])) { session_destroy(); header('Location: ./'); exit; }
if (!$auth) { renderLogin($err); exit; }

// ── Helpers ──────────────────────────────────────────────────────
function readF($path) { return file_exists($path) ? file_get_contents($path) : false; }

function saveF($path, $content, $bck) {
    if (!is_dir($bck)) @mkdir($bck, 0750, true);
    @copy($path, $bck . basename($path) . '.' . date('Ymd_His') . '.bak');
    return file_put_contents($path, $content) !== false;
}

// I18N: `key: 'value'` in JS
function getI18N($html, $key) {
    $p = '/\b' . preg_quote($key, '/') . ":\\s*'((?:[^'\\\\]|\\\\.)*)'(?:\\s*,|\\s*$)/m";
    return preg_match($p, $html, $m) ? stripcslashes($m[1]) : null;
}
function setI18N($html, $key, $val) {
    $esc = str_replace(['\\', "'"], ['\\\\', "\\'"], $val);
    $p   = '/\b' . preg_quote($key, '/') . ":\\s*'(?:[^'\\\\]|\\\\.)*'/";
    return preg_replace($p, $key . ": '" . $esc . "'", $html, 1);
}

// Markers: <!-- VS:key -->content<!-- /VS:key -->
function getM($html, $key) {
    $p = '/<!--\s*VS:' . preg_quote($key, '/') . '\s*-->(.*?)<!--\s*\/VS:' . preg_quote($key, '/') . '\s*-->/s';
    return preg_match($p, $html, $m) ? trim($m[1]) : null;
}
function setM($html, $key, $val) {
    $p = '/<!--\s*VS:' . preg_quote($key, '/') . '\s*-->.*?<!--\s*\/VS:' . preg_quote($key, '/') . '\s*-->/s';
    return preg_replace($p, '<!-- VS:' . $key . ' -->' . $val . '<!-- /VS:' . $key . ' -->', $html, 1);
}

// ── Page config ──────────────────────────────────────────────────
$PAGES = [
  'home' => [
    'title' => 'Trang chủ', 'icon' => '🏠',
    'file'  => 'index.html', 'mode' => 'i18n',
    'sections' => [
      'Hero' => [
        'hero_h1'   => ['label' => 'Tiêu đề lớn (H1)', 'ta' => true, 'html' => true],
        'hero_sub'  => ['label' => 'Mô tả dưới H1',    'ta' => true, 'html' => true],
        'trust_msg' => ['label' => 'Dòng tin tưởng'],
        'hot_pill1' => ['label' => 'Pill ngành hot 1', 'html' => true],
        'hot_pill2' => ['label' => 'Pill ngành hot 2', 'html' => true],
      ],
      'Sứ mệnh' => [
        'miss_text'  => ['label' => 'Đoạn sứ mệnh', 'ta' => true, 'html' => true],
        'miss_quote' => ['label' => 'Trích dẫn',    'ta' => true, 'html' => true],
      ],
      'Thống kê (nhãn)' => [
        'stat1' => ['label' => 'Chỉ số 1 — Nhãn'],
        'stat2' => ['label' => 'Chỉ số 2 — Nhãn'],
        'stat3' => ['label' => 'Chỉ số 3 — Nhãn'],
        'stat4' => ['label' => 'Chỉ số 4 — Nhãn'],
      ],
      'Dịch vụ' => [
        'svc_h2'     => ['label' => 'Tiêu đề mục'],
        'svc_sub'    => ['label' => 'Mô tả mục', 'ta' => true],
        'svc1_cat'   => ['label' => 'DV 1 — Danh mục'],
        'svc1_title' => ['label' => 'DV 1 — Tên'],
        'svc1_desc'  => ['label' => 'DV 1 — Mô tả', 'ta' => true],
        'svc2_cat'   => ['label' => 'DV 2 — Danh mục'],
        'svc2_title' => ['label' => 'DV 2 — Tên'],
        'svc2_desc'  => ['label' => 'DV 2 — Mô tả', 'ta' => true],
        'svc3_cat'   => ['label' => 'DV 3 — Danh mục'],
        'svc3_title' => ['label' => 'DV 3 — Tên'],
        'svc3_desc'  => ['label' => 'DV 3 — Mô tả', 'ta' => true],
        'svc4_cat'   => ['label' => 'DV 4 — Danh mục'],
        'svc4_title' => ['label' => 'DV 4 — Tên'],
        'svc4_desc'  => ['label' => 'DV 4 — Mô tả', 'ta' => true],
        'svc5_cat'   => ['label' => 'DV 5 — Danh mục'],
        'svc5_title' => ['label' => 'DV 5 — Tên'],
        'svc5_desc'  => ['label' => 'DV 5 — Mô tả', 'ta' => true],
        'svc6_cat'   => ['label' => 'DV 6 — Danh mục'],
        'svc6_title' => ['label' => 'DV 6 — Tên'],
        'svc6_desc'  => ['label' => 'DV 6 — Mô tả', 'ta' => true],
      ],
      'Quy trình 4 bước' => [
        'steps_h2'  => ['label' => 'Tiêu đề mục', 'ta' => true, 'html' => true],
        'steps_sub' => ['label' => 'Mô tả mục',   'ta' => true],
        'step1_t'   => ['label' => 'Bước 1 — Tiêu đề'],
        'step1_d'   => ['label' => 'Bước 1 — Mô tả', 'ta' => true],
        'step2_t'   => ['label' => 'Bước 2 — Tiêu đề'],
        'step2_d'   => ['label' => 'Bước 2 — Mô tả', 'ta' => true],
        'step3_t'   => ['label' => 'Bước 3 — Tiêu đề'],
        'step3_d'   => ['label' => 'Bước 3 — Mô tả', 'ta' => true],
        'step4_t'   => ['label' => 'Bước 4 — Tiêu đề'],
        'step4_d'   => ['label' => 'Bước 4 — Mô tả', 'ta' => true],
      ],
      'Học viên chia sẻ' => [
        'testi_h2'  => ['label' => 'Tiêu đề mục'],
        'testi_sub' => ['label' => 'Mô tả mục', 'ta' => true],
        'testi1_q'  => ['label' => 'Học viên 1 — Trích dẫn', 'ta' => true],
        'testi1_nm' => ['label' => 'Học viên 1 — Tên'],
        'testi1_rl' => ['label' => 'Học viên 1 — Vai trò/vị trí', 'html' => true],
        'testi2_q'  => ['label' => 'Học viên 2 — Trích dẫn', 'ta' => true],
        'testi2_nm' => ['label' => 'Học viên 2 — Tên'],
        'testi2_rl' => ['label' => 'Học viên 2 — Vai trò/vị trí', 'html' => true],
        'testi3_q'  => ['label' => 'Học viên 3 — Trích dẫn', 'ta' => true],
        'testi3_nm' => ['label' => 'Học viên 3 — Tên'],
        'testi3_rl' => ['label' => 'Học viên 3 — Vai trò/vị trí', 'html' => true],
        'testi4_q'  => ['label' => 'Học viên 4 — Trích dẫn', 'ta' => true],
        'testi4_nm' => ['label' => 'Học viên 4 — Tên'],
        'testi4_rl' => ['label' => 'Học viên 4 — Vai trò/vị trí', 'html' => true],
      ],
      'Đối tác' => [
        'partners_h2'  => ['label' => 'Tiêu đề mục', 'ta' => true],
        'partners_sub' => ['label' => 'Mô tả mục',   'ta' => true],
        'partner1_s'   => ['label' => 'Đối tác 1 — Tên'],
        'partner2_s'   => ['label' => 'Đối tác 2 — Tên'],
        'partner3_s'   => ['label' => 'Đối tác 3 — Tên'],
      ],
      'CTA' => [
        'cta_h2'    => ['label' => 'Tiêu đề CTA', 'ta' => true, 'html' => true],
        'cta_p'     => ['label' => 'Mô tả CTA'],
        'cta_trust1'=> ['label' => 'Trust 1'],
        'cta_trust2'=> ['label' => 'Trust 2'],
        'cta_trust3'=> ['label' => 'Trust 3'],
      ],
      'Học bổng' => [
        'schol_h3'   => ['label' => 'Tiêu đề'],
        'schol_sub'  => ['label' => 'Phụ đề'],
        'schol_desc' => ['label' => 'Mô tả', 'ta' => true, 'html' => true],
      ],
      'Tin tức' => [
        'news1_title' => ['label' => 'Bài 1 — Tiêu đề'],
        'news1_ex'    => ['label' => 'Bài 1 — Tóm tắt', 'ta' => true],
        'news1_mt'    => ['label' => 'Bài 1 — Ngày & thời gian đọc'],
        'news2_title' => ['label' => 'Bài 2 — Tiêu đề'],
        'news2_ex'    => ['label' => 'Bài 2 — Tóm tắt', 'ta' => true],
        'news2_mt'    => ['label' => 'Bài 2 — Ngày & thời gian đọc'],
        'news3_title' => ['label' => 'Bài 3 — Tiêu đề'],
        'news3_ex'    => ['label' => 'Bài 3 — Tóm tắt', 'ta' => true],
        'news3_mt'    => ['label' => 'Bài 3 — Ngày & thời gian đọc'],
      ],
      'Form đăng ký tư vấn' => [
        'form_h2'     => ['label' => 'Tiêu đề form', 'html' => true],
        'form_sub'    => ['label' => 'Mô tả form'],
        'form_notice' => ['label' => 'Ghi chú form ứng viên'],
        'm_title'     => ['label' => 'Modal — Tiêu đề'],
        'm_sub'       => ['label' => 'Modal — Mô tả', 'ta' => true],
      ],
      'Footer' => [
        'footer_hanoi'       => ['label' => 'VP Hà Nội — Tên'],
        'footer_hanoi_hrs'   => ['label' => 'VP Hà Nội — Giờ làm việc'],
        'footer_leipzig'     => ['label' => 'VP Leipzig — Tên'],
        'footer_leipzig_hrs' => ['label' => 'VP Leipzig — Giờ làm việc'],
        'footer_email_hrs'   => ['label' => 'Email — Thời gian phản hồi'],
        'footer_copy'        => ['label' => 'Copyright'],
      ],
    ],
  ],
  'services' => [
    'title' => 'Dịch vụ', 'icon' => '⚙️',
    'file'  => 'dich-vu/index.html', 'mode' => 'marker',
    'sections' => [
      'Hero' => [
        'dv_h1'       => ['label' => 'Tiêu đề H1', 'ta' => true, 'html' => true],
        'dv_hero_sub' => ['label' => 'Mô tả hero', 'ta' => true],
      ],
      'Đào tạo Tiếng Đức' => [
        'dv_s1_h2'   => ['label' => 'Tiêu đề mục', 'ta' => true, 'html' => true],
        'dv_s1_desc' => ['label' => 'Mô tả', 'ta' => true],
      ],
      'Du học nghề (Ausbildung)' => [
        'dv_s2_h2'   => ['label' => 'Tiêu đề mục', 'ta' => true, 'html' => true],
        'dv_s2_desc' => ['label' => 'Mô tả', 'ta' => true],
      ],
      'Công nhận bằng cấp' => [
        'dv_s3_h2'   => ['label' => 'Tiêu đề mục', 'ta' => true, 'html' => true],
        'dv_s3_desc' => ['label' => 'Mô tả', 'ta' => true],
      ],
      'Hỗ trợ Visa & Thẩm định' => [
        'dv_s4_h2'   => ['label' => 'Tiêu đề mục', 'ta' => true, 'html' => true],
        'dv_s4_desc' => ['label' => 'Mô tả', 'ta' => true],
      ],
      'Kết nối nhân lực cho DN Đức' => [
        'dv_s5_h2'   => ['label' => 'Tiêu đề mục', 'ta' => true, 'html' => true],
        'dv_s5_desc' => ['label' => 'Mô tả', 'ta' => true],
      ],
      'Du học Châu Âu & Úc' => [
        'dv_s6_h2'   => ['label' => 'Tiêu đề mục', 'ta' => true, 'html' => true],
        'dv_s6_desc' => ['label' => 'Mô tả', 'ta' => true],
      ],
      'CTA cuối trang' => [
        'dv_cta_h2' => ['label' => 'Câu hỏi CTA'],
      ],
    ],
  ],
  'about' => [
    'title' => 'Về chúng tôi', 'icon' => '👥',
    'file'  => 've-chung-toi/index.html', 'mode' => 'marker',
    'sections' => [
      'Giới thiệu đội ngũ' => [
        'team_intro' => ['label' => 'Dòng giới thiệu chung'],
      ],
      'Nguyễn Hương Trà (Co-Founder)' => [
        'founder1_role' => ['label' => 'Vai trò / Chuyên môn'],
        'founder1_bio'  => ['label' => 'Tiểu sử', 'ta' => true, 'html' => true],
      ],
      'Vannessa Do (Đại diện tại Đức)' => [
        'founder2_role' => ['label' => 'Vai trò / Chuyên môn'],
        'founder2_bio'  => ['label' => 'Tiểu sử', 'ta' => true, 'html' => true],
      ],
      'Thành viên đội ngũ' => [
        'bio_oanh'      => ['label' => 'Nguyễn Oanh — Bio',  'ta' => true],
        'bio_phuonganh' => ['label' => 'Phương Anh — Bio',   'ta' => true],
        'bio_khanhlinh' => ['label' => 'Khánh Linh — Bio',   'ta' => true],
      ],
    ],
  ],
  'contact' => [
    'title' => 'Liên hệ', 'icon' => '📞',
    'file'  => 'lien-he/index.html', 'mode' => 'marker',
    'sections' => [
      'Nội dung liên hệ' => [
        'contact_h2'      => ['label' => 'Tiêu đề phần liên hệ'],
        'contact_desc'    => ['label' => 'Mô tả liên hệ', 'ta' => true, 'html' => true],
        'contact_address' => ['label' => 'Địa chỉ',       'ta' => true, 'html' => true],
      ],
    ],
  ],
];

// ── Save handler ─────────────────────────────────────────────────
$flash = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['_a'] ?? '') === 'save') {
    $tab = $_POST['_page'] ?? '';
    if (isset($PAGES[$tab])) {
        $cfg  = $PAGES[$tab];
        $file = $ROOT . $cfg['file'];
        $html = readF($file);
        if ($html === false) {
            $flash = 'err|Không đọc được file: ' . htmlspecialchars($cfg['file']);
        } else {
            $mode = $cfg['mode'];
            foreach ($_POST['f'] ?? [] as $key => $val) {
                $found = false;
                foreach ($cfg['sections'] as $sec => $fields) {
                    if (isset($fields[$key])) { $found = true; break; }
                }
                if (!$found) continue;
                $val = trim($val);
                if ($mode === 'i18n') {
                    $html = setI18N($html, $key, $val);
                } else {
                    $html = setM($html, $key, $val);
                }
            }
            if (saveF($file, $html, $BCK)) {
                $flash = 'ok|Lưu thành công! File đã được cập nhật.';
            } else {
                $flash = 'err|Lỗi khi ghi file. Kiểm tra quyền truy cập.';
            }
        }
    }
}

// ── Current tab & values ─────────────────────────────────────────
$tab = $_GET['p'] ?? 'home';
if (!isset($PAGES[$tab])) $tab = 'home';
$cfg = $PAGES[$tab];
$html_src = readF($ROOT . $cfg['file']);

function getVal($html, $mode, $key) {
    if ($html === false) return null;
    return $mode === 'i18n' ? getI18N($html, $key) : getM($html, $key);
}

// ── Render ───────────────────────────────────────────────────────
function renderLogin($err) { ?>
<!DOCTYPE html><html lang="vi"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex"><title>Admin Login — VietnamSkilled</title>
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'Segoe UI',system-ui,sans-serif;background:linear-gradient(135deg,#1A3A5C 0%,#286A9A 100%);min-height:100vh;display:flex;align-items:center;justify-content:center}
.card{background:#fff;border-radius:18px;padding:44px 40px;width:360px;box-shadow:0 24px 64px rgba(0,0,0,.28)}
.logo{text-align:center;margin-bottom:32px}
.logo img{max-height:52px;max-width:200px;object-fit:contain}
.logo h1{font-size:13px;font-weight:700;color:#64748B;margin-top:10px;letter-spacing:.3px}
label{display:block;font-size:11px;font-weight:700;color:#1A3A5C;letter-spacing:.5px;text-transform:uppercase;margin-bottom:6px;margin-top:20px}
.pw-wrap{position:relative}
.pw-wrap input{width:100%;border:1.5px solid #D0E8F2;border-radius:9px;padding:12px 44px 12px 14px;font-size:14px;outline:none;transition:border .15s;background:#fff}
.pw-wrap input:focus{border-color:#47979F;box-shadow:0 0 0 3px rgba(71,151,159,.1)}
.eye{position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:#94A3B8;padding:4px;line-height:0;transition:color .15s}
.eye:hover{color:#47979F}
.remember{display:flex;align-items:center;gap:8px;margin-top:14px;cursor:pointer;user-select:none}
.remember input{width:15px;height:15px;accent-color:#47979F;cursor:pointer;flex-shrink:0}
.remember span{font-size:13px;color:#64748B}
.btn{width:100%;background:#47979F;color:#fff;border:none;border-radius:9px;padding:13px;font-size:15px;font-weight:700;cursor:pointer;margin-top:22px;transition:background .2s}
.btn:hover{background:#1A3A5C}
.err{background:#FEE2E2;color:#991B1B;border-radius:8px;padding:10px 14px;font-size:13px;margin-top:16px;font-weight:500}
</style></head><body>
<div class="card">
  <div class="logo">
    <img src="../logo.png" alt="VietnamSkilled" onerror="this.src='../logo.jpg'" id="logoImg">
  </div>
  <form method="post" id="lf">
    <input type="hidden" name="_a" value="login">
    <label for="pw">Mật khẩu</label>
    <div class="pw-wrap">
      <input type="password" id="pw" name="pw" placeholder="••••••••••••" autofocus autocomplete="current-password">
      <button type="button" class="eye" id="eyeBtn" aria-label="Hiện/ẩn mật khẩu">
        <svg id="icoShow" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
        <svg id="icoHide" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
      </button>
    </div>
    <label class="remember"><input type="checkbox" id="rem"><span>Ghi nhớ mật khẩu</span></label>
    <button class="btn" type="submit">Đăng nhập</button>
    <?php if ($err): ?><div class="err">⚠ <?= htmlspecialchars($err) ?></div><?php endif ?>
  </form>
</div>
<script>
(function(){
  var pw=document.getElementById('pw'),rem=document.getElementById('rem');
  var eyeBtn=document.getElementById('eyeBtn'),icoShow=document.getElementById('icoShow'),icoHide=document.getElementById('icoHide');
  var failed=<?= $err ? 'true' : 'false' ?>;

  // Nếu vừa sai mật khẩu → xóa giá trị đã lưu ngay lập tức
  if(failed){ localStorage.removeItem('vs_adm_pw'); pw.value=''; }

  // Tự điền mật khẩu đã lưu (chỉ khi trang mới mở, không phải sau lần sai)
  if(!failed){
    var saved=localStorage.getItem('vs_adm_pw');
    if(saved){pw.value=saved;rem.checked=true;}
  }

  // Show/hide toggle
  eyeBtn.addEventListener('click',function(){
    var show=pw.type==='password';
    pw.type=show?'text':'password';
    icoShow.style.display=show?'none':'';
    icoHide.style.display=show?'':'none';
    pw.focus();
  });

  // Lưu/xóa khi submit (chỉ lưu khi nhập đúng sẽ không tự fill sai nữa)
  document.getElementById('lf').addEventListener('submit',function(){
    if(rem.checked)localStorage.setItem('vs_adm_pw',pw.value);
    else localStorage.removeItem('vs_adm_pw');
  });

  document.getElementById('logoImg').addEventListener('error',function(){this.style.display='none';});
})();
</script>
</body></html>
<?php }

// ── Admin UI ─────────────────────────────────────────────────────
global $PAGES;
list($ftype, $fmsg) = $flash ? explode('|', $flash, 2) : ['', ''];
$totalFields = array_sum(array_map('count', $cfg['sections']));
$fileModTime = file_exists($ROOT . $cfg['file']) ? date('d/m/Y H:i', filemtime($ROOT . $cfg['file'])) : 'N/A';
?>
<!DOCTYPE html><html lang="vi"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex">
<title><?= htmlspecialchars($cfg['title']) ?> ‹ VietnamSkilled Admin</title>
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Oxygen,sans-serif;background:#f0f0f1;color:#3c434a;font-size:13px;line-height:1.4}

/* ── Admin Bar ── */
#wpadminbar{position:fixed;top:0;left:0;right:0;height:32px;background:#1d2327;z-index:99999;display:flex;align-items:center}
.ab-logo{display:flex;align-items:center;justify-content:center;width:36px;height:32px;color:rgba(240,240,241,.6);flex-shrink:0;border-right:none;text-decoration:none;transition:color .1s}
.ab-logo:hover{color:#fff;background:rgba(255,255,255,.07)}
.ab-logo svg{display:block}
.ab-site{display:flex;align-items:center;gap:5px;padding:0 10px;height:32px;color:rgba(240,240,241,.8);text-decoration:none;font-size:13px;font-weight:500;transition:color .1s;border-right:1px solid rgba(255,255,255,.07)}
.ab-site:hover{color:#fff;background:rgba(255,255,255,.07)}
.ab-right{margin-left:auto;display:flex;align-items:center}
.ab-btn{display:flex;align-items:center;gap:5px;padding:0 12px;height:32px;color:rgba(240,240,241,.6);text-decoration:none;font-size:12px;transition:all .1s;white-space:nowrap;border:none;background:none;cursor:pointer;font-family:inherit}
.ab-btn:hover{color:#fff;background:rgba(255,255,255,.07)}
.ab-sep{width:1px;height:16px;background:rgba(255,255,255,.1);flex-shrink:0}
.ab-user{display:flex;align-items:center;gap:6px;padding:0 12px;color:rgba(240,240,241,.6);font-size:12px;height:32px}
.ab-avatar{width:22px;height:22px;border-radius:50%;background:#47979F;display:flex;align-items:center;justify-content:center;color:#fff;font-size:10px;font-weight:700;flex-shrink:0}

/* ── Sidebar ── */
#adminmenuwrap{position:fixed;top:32px;left:0;bottom:0;width:160px;background:#1d2327;overflow-y:auto;z-index:9990}
#adminmenu{list-style:none}
.sb-brand{padding:14px 14px 12px;border-bottom:1px solid rgba(255,255,255,.07)}
.sb-brand img{max-height:26px;max-width:132px;object-fit:contain;opacity:.6}
.menu-top{display:flex;align-items:center;gap:9px;padding:9px 14px;color:rgba(240,240,241,.7);text-decoration:none;font-size:13px;cursor:pointer;transition:color .1s;border-left:4px solid transparent;user-select:none}
.menu-top:hover{color:#fff;background:rgba(255,255,255,.05)}
.menu-top.open .menu-arrow{transform:rotate(90deg)}
.menu-icon{font-size:14px;width:16px;text-align:center;flex-shrink:0}
.menu-name{flex:1;font-size:12.5px}
.menu-arrow{font-size:9px;opacity:.5;transition:transform .2s;flex-shrink:0}
.wp-submenu{display:none;list-style:none;background:#16191c;padding:4px 0 6px}
.wp-submenu.open{display:block}
.wp-submenu li a{display:block;padding:6px 14px 6px 40px;color:rgba(240,240,241,.55);text-decoration:none;font-size:12px;transition:color .1s;border-left:4px solid transparent}
.wp-submenu li a:hover{color:rgba(240,240,241,.9)}
.wp-submenu li a.current{color:#fff;border-left-color:#47979F}
.menu-sep{height:1px;background:rgba(255,255,255,.07);margin:6px 14px}
.menu-logout{display:flex;align-items:center;gap:9px;padding:9px 14px;color:rgba(240,240,241,.45);text-decoration:none;font-size:12.5px;transition:color .1s;border-left:4px solid transparent}
.menu-logout:hover{color:rgba(240,240,241,.85);background:rgba(255,255,255,.04)}
#adminmenuwrap::-webkit-scrollbar{width:3px}
#adminmenuwrap::-webkit-scrollbar-thumb{background:#333}

/* ── Content ── */
#wpcontent{margin-left:160px;margin-top:32px;padding:20px 20px 60px}
.wrap{max-width:1280px}

/* ── Page heading ── */
.wp-heading-inline{font-size:23px;font-weight:400;color:#1d2327;line-height:1.3;display:inline}
.page-action-btn{display:inline-flex;align-items:center;gap:4px;margin-left:10px;font-size:12px;font-weight:600;padding:4px 10px;border:1px solid #2271b1;border-radius:3px;color:#2271b1;text-decoration:none;vertical-align:middle;transition:all .15s}
.page-action-btn:hover{background:#2271b1;color:#fff}
.wp-header-end{border-bottom:1px solid #c3c4c7;margin:6px 0 16px;clear:both}

/* ── Notices ── */
.notice{background:#fff;border-left:4px solid #72aee6;box-shadow:0 1px 1px rgba(0,0,0,.04);padding:10px 14px;margin:0 0 16px;display:flex;align-items:center;gap:8px}
.notice p{margin:0;font-size:13px}
.notice-success{border-left-color:#00a32a}
.notice-error{border-left-color:#d63638}
.notice-success p{color:#007017}
.notice-error p{color:#8a2424}

/* ── Two-column layout ── */
#poststuff{display:flex;gap:20px;align-items:flex-start}
#postbox-container-1{width:260px;flex-shrink:0;position:sticky;top:52px}
#postbox-container-2{flex:1;min-width:0}

/* ── Postbox ── */
.postbox{background:#fff;border:1px solid #c3c4c7;margin-bottom:16px}
.postbox-header{display:flex;align-items:center;padding:0 12px;cursor:pointer;user-select:none;border-bottom:1px solid #c3c4c7;min-height:36px}
.postbox-header:hover h2{color:#2271b1}
.postbox-header h2{font-size:13px;font-weight:600;color:#1d2327;flex:1;margin:0;padding:8px 0;line-height:1.4}
.postbox-handle{width:30px;height:30px;display:flex;align-items:center;justify-content:center;color:#787c82;font-size:16px;transition:transform .2s;flex-shrink:0}
.postbox.closed .postbox-handle{transform:rotate(-90deg)}
.postbox .inside{padding:0}
.postbox.closed .inside{display:none}
.section-count{display:inline-block;background:#e9f0f9;color:#2271b1;font-size:10px;font-weight:700;padding:1px 7px;border-radius:10px;margin-left:6px;vertical-align:middle;line-height:1.8}

/* ── Form table ── */
.form-table{width:100%;border-collapse:collapse}
.form-table tr{border-bottom:1px solid #f0f0f1}
.form-table tr:last-child{border-bottom:none}
.form-table th{width:28%;padding:12px 10px 12px 12px;vertical-align:top;text-align:left;white-space:nowrap}
.form-table td{padding:10px 12px 10px 0}
.form-table th label{font-size:12.5px;font-weight:600;color:#1d2327;line-height:1.4;display:block;margin-bottom:3px}
.key-mono{display:inline-block;font-family:'SFMono-Regular',Consolas,monospace;font-size:10px;color:#646970;background:#f6f7f7;border:1px solid #dcdcde;padding:1px 5px;border-radius:2px}
.html-badge{display:inline-block;font-size:9.5px;font-weight:700;padding:1px 5px;border-radius:2px;background:#e6f0f8;color:#2271b1;border:1px solid #c2d9ee;text-transform:uppercase;letter-spacing:.2px;margin-left:3px}
.reg-text{width:100%;border:1px solid #8c8f94;border-radius:3px;padding:6px 8px;font-size:13px;font-family:inherit;outline:none;background:#fff;color:#2c3338;transition:border .1s,box-shadow .1s}
.reg-text:focus{border-color:#2271b1;box-shadow:0 0 0 1px #2271b1;outline:2px solid transparent}
textarea.reg-text{resize:vertical;min-height:76px;line-height:1.5}
.missing-field{font-size:12px;color:#d63638;font-style:italic;padding:2px 0}

/* ── Publish box ── */
.submitbox .submitbox-hd{background:#f6f7f7;border-bottom:1px solid #c3c4c7;padding:7px 12px;font-size:10.5px;font-weight:700;text-transform:uppercase;letter-spacing:.4px;color:#646970}
.submitbox-row{padding:7px 12px;border-bottom:1px solid #f0f0f1;font-size:12px;display:flex;align-items:baseline;gap:6px}
.submitbox-row:last-of-type{border-bottom:none}
.submitbox-row .sr-lbl{color:#646970;min-width:54px;flex-shrink:0;font-size:11.5px}
.submitbox-row .sr-val{color:#3c434a;word-break:break-all;font-size:11.5px}
.submitbox-row a{color:#2271b1;text-decoration:none;font-size:11.5px}
.submitbox-row a:hover{color:#135e96;text-decoration:underline}
.sr-ok{color:#007017!important}
.btn-update{display:block;width:calc(100% - 24px);margin:12px;background:#2271b1;color:#fff;border:none;border-radius:3px;padding:9px 14px;font-size:13px;font-weight:600;cursor:pointer;text-align:center;transition:background .15s;font-family:inherit;letter-spacing:.1px}
.btn-update:hover{background:#135e96}
.btn-update:active{background:#0a4b78;transform:translateY(1px)}

/* ── Meta box ── */
.meta-row{padding:8px 12px;border-bottom:1px solid #f0f0f1;font-size:11.5px}
.meta-row:last-child{border-bottom:none}
.meta-row .mr-lbl{font-size:10.5px;text-transform:uppercase;letter-spacing:.3px;color:#b5b5b5;margin-bottom:2px;font-weight:600}
.meta-row .mr-val{color:#3c434a;word-break:break-all}
.meta-row a{color:#2271b1;text-decoration:none}
.meta-row a:hover{text-decoration:underline}

@media(max-width:960px){
  #postbox-container-1{width:220px}
}
@media(max-width:782px){
  #adminmenuwrap{display:none}
  #wpcontent{margin-left:0}
  #poststuff{flex-direction:column-reverse}
  #postbox-container-1{width:100%;position:static}
}
</style></head>
<body>

<!-- ── Admin Bar ── -->
<div id="wpadminbar">
  <a class="ab-logo" href="?p=<?= $tab ?>" title="VietnamSkilled CMS">
    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M9 21V9"/></svg>
  </a>
  <a class="ab-site" href="?p=<?= $tab ?>">VietnamSkilled</a>
  <div class="ab-right">
    <a class="ab-btn" href="../" target="_blank">
      <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
      Xem trang
    </a>
    <div class="ab-sep"></div>
    <div class="ab-user">
      <div class="ab-avatar">A</div>
      Admin
    </div>
    <a class="ab-btn" href="?out=1">
      <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
      Đăng xuất
    </a>
  </div>
</div>

<!-- ── Sidebar ── -->
<div id="adminmenuwrap">
  <ul id="adminmenu">
    <li class="sb-brand">
      <img src="../logo.png" alt="VietnamSkilled" onerror="this.src='../logo.jpg';this.onerror=null;">
    </li>
    <li>
      <div class="menu-top open" id="menu-pages" onclick="toggleMenu('pages')">
        <span class="menu-icon">📄</span>
        <span class="menu-name">Trang</span>
        <span class="menu-arrow">▶</span>
      </div>
      <ul class="wp-submenu open" id="submenu-pages">
        <?php foreach ($PAGES as $slug => $pg): ?>
        <li><a href="?p=<?= $slug ?>" class="<?= $tab === $slug ? 'current' : '' ?>"><?= $pg['icon'] ?> <?= htmlspecialchars($pg['title']) ?></a></li>
        <?php endforeach ?>
      </ul>
    </li>
    <li><a href="/admin/tin-tuc/" class="menu-logout"><span>📝 Tin tức &amp; Cập nhật</span></a></li>
    <li><a href="/admin/seo/" class="menu-logout"><span>🔎 Cấu hình SEO</span></a></li>
    <li><div class="menu-sep"></div></li>
    <li><a href="?out=1" class="menu-logout">
      <span class="menu-icon" style="font-size:12px">⎋</span>
      <span>Đăng xuất</span>
    </a></li>
  </ul>
</div>

<!-- ── Main Content ── -->
<div id="wpcontent">
<div class="wrap">

  <h1 class="wp-heading-inline"><?= $cfg['icon'] ?> <?= htmlspecialchars($cfg['title']) ?></h1>
  <a class="page-action-btn" href="../<?= htmlspecialchars($cfg['file']) ?>" target="_blank">
    <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
    Xem trang
  </a>
  <hr class="wp-header-end">

  <?php if ($ftype): ?>
  <div class="notice notice-<?= $ftype === 'ok' ? 'success' : 'error' ?>">
    <p><?= $ftype === 'ok' ? '<strong>✓</strong>' : '<strong>⚠</strong>' ?> <?= htmlspecialchars($fmsg) ?></p>
  </div>
  <?php endif ?>

  <form method="post" id="post-form">
    <input type="hidden" name="_a" value="save">
    <input type="hidden" name="_page" value="<?= htmlspecialchars($tab) ?>">

    <div id="poststuff">

      <!-- ── LEFT: Fields ── -->
      <div id="postbox-container-2">
        <?php foreach ($cfg['sections'] as $sec_name => $fields):
          $boxId = 'pb-' . preg_replace('/[^a-z0-9]+/', '-', strtolower($sec_name));
        ?>
        <div class="postbox" id="<?= $boxId ?>">
          <div class="postbox-header" onclick="togglePostbox('<?= $boxId ?>')">
            <h2><?= htmlspecialchars($sec_name) ?><span class="section-count"><?= count($fields) ?></span></h2>
            <span class="postbox-handle">▾</span>
          </div>
          <div class="inside">
            <table class="form-table">
              <?php foreach ($fields as $key => $f):
                $val   = getVal($html_src, $cfg['mode'], $key);
                $isHtml = !empty($f['html']);
                $isTa   = !empty($f['ta']);
              ?>
              <tr>
                <th>
                  <label for="fld-<?= htmlspecialchars($key) ?>"><?= htmlspecialchars($f['label']) ?></label>
                  <span class="key-mono"><?= htmlspecialchars($key) ?></span><?php if ($isHtml): ?><span class="html-badge">HTML</span><?php endif ?>
                </th>
                <td>
                  <?php if ($val === null): ?>
                    <span class="missing-field">⚠ Marker VS:<?= htmlspecialchars($key) ?> chưa có trong file</span>
                  <?php elseif ($isTa): ?>
                    <textarea id="fld-<?= htmlspecialchars($key) ?>" class="reg-text" name="f[<?= htmlspecialchars($key) ?>]" rows="<?= min(10, max(3, substr_count($val, "\n") + 2)) ?>"><?= htmlspecialchars($val) ?></textarea>
                  <?php else: ?>
                    <input id="fld-<?= htmlspecialchars($key) ?>" class="reg-text" type="text" name="f[<?= htmlspecialchars($key) ?>]" value="<?= htmlspecialchars($val) ?>">
                  <?php endif ?>
                </td>
              </tr>
              <?php endforeach ?>
            </table>
          </div>
        </div>
        <?php endforeach ?>
      </div>

      <!-- ── RIGHT: Publish + Meta ── -->
      <div id="postbox-container-1">

        <!-- Publish box -->
        <div class="postbox" id="pb-publish">
          <div class="postbox-header" onclick="togglePostbox('pb-publish')">
            <h2>Xuất bản</h2>
            <span class="postbox-handle">▾</span>
          </div>
          <div class="inside">
            <div class="submitbox">
              <div class="submitbox-hd">Trạng thái</div>
              <div class="submitbox-row"><span class="sr-lbl">Trang:</span><span class="sr-val"><?= htmlspecialchars($cfg['title']) ?></span></div>
              <div class="submitbox-row"><span class="sr-lbl">Chế độ:</span><span class="sr-val"><?= $cfg['mode'] === 'i18n' ? 'I18N (JS)' : 'Marker (HTML)' ?></span></div>
              <div class="submitbox-row"><span class="sr-lbl">Backup:</span><span class="sr-val sr-ok">✓ Tự động</span></div>
              <div class="submitbox-row"><span class="sr-lbl">Sửa lần cuối:</span><span class="sr-val"><?= $fileModTime ?></span></div>
              <button class="btn-update" type="submit">↑ Cập nhật trang</button>
            </div>
          </div>
        </div>

        <!-- Page info box -->
        <div class="postbox" id="pb-pageinfo">
          <div class="postbox-header" onclick="togglePostbox('pb-pageinfo')">
            <h2>Thông tin trang</h2>
            <span class="postbox-handle">▾</span>
          </div>
          <div class="inside">
            <div class="meta-row"><div class="mr-lbl">File</div><div class="mr-val"><?= htmlspecialchars($cfg['file']) ?></div></div>
            <div class="meta-row"><div class="mr-lbl">Tổng trường</div><div class="mr-val"><?= $totalFields ?> trường / <?= count($cfg['sections']) ?> mục</div></div>
            <div class="meta-row"><div class="mr-lbl">Xem trước</div><div class="mr-val"><a href="../<?= htmlspecialchars($cfg['file']) ?>" target="_blank">Mở trang ↗</a></div></div>
            <div class="meta-row"><div class="mr-lbl">Trang khác</div><div class="mr-val">
              <?php
              $links = [];
              foreach ($PAGES as $s => $pg) {
                if ($s !== $tab) $links[] = '<a href="?p='.htmlspecialchars($s).'">'.htmlspecialchars($pg['title']).'</a>';
              }
              echo implode(' · ', $links);
              ?>
            </div></div>
          </div>
        </div>

      </div><!-- /postbox-container-1 -->

    </div><!-- /poststuff -->
  </form>

</div><!-- /wrap -->
</div><!-- /wpcontent -->

<script>
function togglePostbox(id) {
  var box = document.getElementById(id);
  if (!box) return;
  box.classList.toggle('closed');
  try { localStorage.setItem('vsa_pb_' + id, box.classList.contains('closed') ? '1' : '0'); } catch(e){}
}
function toggleMenu(name) {
  var btn = document.getElementById('menu-' + name);
  var sub = document.getElementById('submenu-' + name);
  if (!btn || !sub) return;
  btn.classList.toggle('open');
  sub.classList.toggle('open');
}
// Restore collapsed state from localStorage
document.querySelectorAll('.postbox[id]').forEach(function(box) {
  try { if (localStorage.getItem('vsa_pb_' + box.id) === '1') box.classList.add('closed'); } catch(e){}
});
</script>

</body></html>
