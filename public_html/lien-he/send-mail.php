<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowed = [
    'https://branding.test9.io.vn',
    'https://vietnamskilled.org',
    'https://www.vietnamskilled.org',
    'http://localhost'
];
if (in_array($origin, $allowed)) {
    header("Access-Control-Allow-Origin: $origin");
}
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$raw = file_get_contents('php://input', false, null, 0, 32001);
$data = is_string($raw) && strlen($raw) <= 32000 ? json_decode($raw, true) : null;

function mailInput($value, int $max): string {
    return mb_substr(trim(is_string($value) ? $value : ''), 0, $max);
}
function mailHtml(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function mailRow(string $label, string $value): string {
    return '<tr><td style="padding:10px 16px;color:#64748b;width:140px;border-bottom:1px solid #f1f5f9;">'
        . mailHtml($label) . '</td><td style="padding:10px 16px;color:#1e293b;border-bottom:1px solid #f1f5f9;">'
        . nl2br(mailHtml($value), false) . '</td></tr>';
}
function mailBadRequest(): void {
    http_response_code(400);
    echo json_encode(['error' => 'Missing or invalid required fields']);
    exit;
}

if (!is_array($data)) mailBadRequest();
$form = mailInput($data['form'] ?? '', 25);
$name = preg_replace('/[\r\n]+/', ' ', mailInput($data['name'] ?? '', 256));
$replyTo = mailInput($data['email'] ?? '', 256);
$hasEmail = filter_var($replyTo, FILTER_VALIDATE_EMAIL) !== false;
$fieldInput = $data['fields'] ?? [];
$fields = is_array($fieldInput) ? $fieldInput : [];
$value = function (string $key) use ($fields): string {
    return mailInput($fields[$key] ?? '', 5000);
};
$definitions = [
    'contact' => ['Liên hệ', ['Email', 'Nội dung'], ['Nội dung'], true],
    'applicant' => ['Ứng viên', ['Số điện thoại', 'Email', 'Tuổi', 'Trình độ học vấn', 'Tiếng Đức', 'Ngành quan tâm'], ['Số điện thoại'], true],
    'business' => ['Doanh nghiệp', ['Tên công ty', 'Ngành', 'Số lượng cần tuyển', 'Đối tượng tuyển', 'Người liên hệ', 'Email'], ['Tên công ty', 'Người liên hệ'], true],
    'consultation' => ['Tư vấn nhanh', ['Họ tên', 'Số điện thoại', 'Email', 'Dịch vụ quan tâm', 'Trình độ tiếng Đức', 'Ghi chú'], ['Số điện thoại'], false],
];
if ($name === '') mailBadRequest();
$message = '';
$plain = [];
if ($form === '') {
    // Existing API callers may still send a plain message or an older table.
    $legacy = mailInput($data['message'] ?? '', 14000);
    if ($legacy === '') mailBadRequest();
    $legacy = trim(html_entity_decode(strip_tags(preg_replace('~</t[dr]>|<br\s*/?>~i', "\n", $legacy)), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    if ($legacy === '') mailBadRequest();
    $label = 'Liên hệ';
    $message = mailRow('Nội dung', $legacy);
    $plain[] = $legacy;
} else {
    if (!isset($definitions[$form])) mailBadRequest();
    [$label, $labels, $required, $needsEmail] = $definitions[$form];
    if ($needsEmail && !$hasEmail) mailBadRequest();
    foreach ($required as $key) if ($value($key) === '') mailBadRequest();
    foreach ($labels as $key) {
        $field = $key === 'Email' ? $replyTo : $value($key);
        if ($field === '') continue;
        $message .= mailRow($key, $field);
        $plain[] = $key . ': ' . $field;
    }
}
$page = mailInput($data['page'] ?? '', 500);
if (!filter_var($page, FILTER_VALIDATE_URL)
    || parse_url($page, PHP_URL_SCHEME) !== 'https'
    || !in_array(parse_url($page, PHP_URL_HOST), ['vietnamskilled.org', 'www.vietnamskilled.org'], true)) {
    $page = '';
}
$page = mailHtml($page);
$email = mailHtml($hasEmail ? $replyTo : 'no-reply@vietnamskilled.org');
$reference = 'VS-' . strtoupper(bin2hex(random_bytes(6)));

$baseDir = dirname(__DIR__, 2);
$config = is_readable($baseDir . '/config.php') ? require $baseDir . '/config.php' : [];
$smtp = $config['smtp'] ?? [];
if (empty($smtp['username']) || empty($smtp['password']) || empty($smtp['recipient'])) {
    http_response_code(503);
    echo json_encode(['error' => 'Mail service is not configured']);
    exit;
}
require $baseDir . '/vendor/phpmailer/vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;

$mail = new PHPMailer(true);
try {
    $mail->isSMTP();
    $mail->Host = $smtp['host'] ?? 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username = $smtp['username'];
    $mail->Password = $smtp['password'];
    $mail->SMTPSecure = ($smtp['encryption'] ?? 'starttls') === 'smtps' ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = (int)($smtp['port'] ?? 587);
    $mail->CharSet    = 'UTF-8';

    $mail->setFrom($smtp['username'], $smtp['sender_name'] ?? 'VietnamSkilled');
    $mail->addAddress($smtp['recipient']);
    if ($hasEmail) $mail->addReplyTo($replyTo, $name);

    $date = date('d/m/Y H:i');
    $mail->Subject = "[$reference] $label - VietnamSkilled";
    $mail->addCustomHeader('X-VietnamSkilled-Reference', $reference);
    $name = mailHtml($name);
    $mail->isHTML(true);

    $pageInfo = $page ? "<tr><td style=\"padding:8px 16px;color:#64748b;font-size:13px;border-top:1px solid #f1f5f9;\">Gửi từ: <a href=\"$page\" style=\"color:#47979F;\">$page</a></td></tr>" : '';

    $mail->Body = <<<HTML
<!DOCTYPE html>
<html><head><meta charset="utf-8"></head>
<body style="margin:0;padding:0;background:#f4f7fa;font-family:'Segoe UI',Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f7fa;padding:24px 0;">
<tr><td align="center">
<table width="560" cellpadding="0" cellspacing="0" style="background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,0.06);">

  <tr><td style="background:linear-gradient(135deg,#0c1f2e,#1a3a5c);padding:24px 28px;">
    <table width="100%"><tr>
      <td><span style="font-size:20px;font-weight:800;color:#fff;">Vietnam</span><span style="font-size:20px;font-weight:800;color:#47979F;">Skilled</span></td>
      <td align="right"><span style="font-size:12px;color:rgba(255,255,255,0.5);">$date</span></td>
    </tr></table>
  </td></tr>

  <tr><td style="padding:28px 28px 8px;">
    <div style="background:#E8F6F7;border-left:4px solid #47979F;padding:14px 18px;border-radius:0 8px 8px 0;margin-bottom:20px;">
      <span style="font-size:11px;color:#47979F;font-weight:700;text-transform:uppercase;letter-spacing:1px;">Khách hàng mới</span>
      <div style="font-size:18px;font-weight:700;color:#1e293b;margin-top:4px;">$name</div>
    </div>
  </td></tr>

  <tr><td style="padding:0 28px 24px;">
    <table width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e2e8f0;border-radius:8px;overflow:hidden;">
      <tr style="background:#f8fafc;">
        <td colspan="2" style="padding:10px 16px;font-size:12px;font-weight:700;color:#47979F;text-transform:uppercase;letter-spacing:1px;border-bottom:1px solid #e2e8f0;">Thông tin chi tiết</td>
      </tr>
      $message
      $pageInfo
    </table>
  </td></tr>

  <tr><td style="padding:0 28px 24px;text-align:center;">
    <a href="mailto:$email" style="display:inline-block;background:#47979F;color:#fff;font-weight:600;font-size:14px;padding:10px 28px;border-radius:6px;text-decoration:none;">Trả lời $name</a>
  </td></tr>

  <tr><td style="background:#f8fafc;padding:16px 28px;text-align:center;border-top:1px solid #e2e8f0;">
    <span style="font-size:11px;color:#94a3b8;">Email tự động từ vietnamskilled.org</span>
  </td></tr>

</table>
</td></tr></table>
</body></html>
HTML;

    $mail->AltBody = html_entity_decode($name, ENT_QUOTES | ENT_HTML5, 'UTF-8') . "\n" . implode("\n", $plain);

    $mail->send();
    echo json_encode(['ok' => true, 'reference' => $reference]);
} catch (Exception $e) {
    http_response_code(500);
    error_log('VietnamSkilled mail ' . $reference . ': ' . $mail->ErrorInfo);
    echo json_encode(['error' => 'Mail delivery failed']);
}
