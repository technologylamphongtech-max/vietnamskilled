# VietnamSkilled

Bản mã nguồn website tại thời điểm 03/11/2026. Tài liệu Word hướng dẫn quản trị, viết bài, SEO, email và vận hành.

| Đường dẫn | Vai trò |
| --- | --- |
| `public_html/` | HTML, CSS, JavaScript, ảnh, PHP quản trị và email, trình soạn thảo TinyMCE |
| `cms/` | Tin tức, quản trị bài viết và SEO, sitemap và robots |
| `cms-data/` | Nội dung, SEO và ảnh tải lên được dữ liệu xuất tham chiếu |
| `vendor/phpmailer/` | Thư viện gửi email và Composer autoloader |
| `config.example.php` | Mẫu cấu hình mật khẩu quản trị và SMTP |
| `deploy/` | Nginx, trình cài máy mới và lịch gia hạn tham chiếu |
| `audit/404-links.csv` | Các lượt liên kết đã sửa hoặc gỡ |

ZIP loại trừ mật khẩu, khóa TLS, nhật ký, bản sao lịch sử, bài trong thùng rác và ảnh CMS không được dữ liệu xuất tham chiếu. Tích hợp bên ngoài vẫn dùng dịch vụ của nhà cung cấp.

## Cài máy mới

1. Chuẩn bị Linux, Nginx, PHP-FPM, PHP CLI và các module curl, DOM/XML, fileinfo, iconv, JSON, mbstring, OpenSSL. Mở cổng 80 và 443. Website dùng `/var/www/vietnamskilled.org` và tài khoản PHP `www-data`.
2. Giải nén và vào `VietnamSkilled`. Kiểm tra socket bằng `ls /run/php/*.sock`, rồi chạy `sudo bash deploy/install.sh /run/php/phpX.Y-fpm.sock` với tên socket thực tế. Trình cài chỉ dùng khi đường dẫn đích chưa tồn tại.
3. Khi DNS trỏ đến máy mới và HTTP mở từ Internet, cấp chứng chỉ bằng `sudo certbot --nginx --redirect -d vietnamskilled.org` với plugin Nginx đã cài. Chỉ đăng nhập quản trị qua HTTPS. Kiểm tra lịch gia hạn của bản Certbot trên máy mới và chạy `sudo certbot renew --dry-run`. `deploy/nginx-https.conf` dùng để tham chiếu.
4. Sửa `/var/www/vietnamskilled.org/config.php`. Điền hash mật khẩu quản trị mới, thông tin SMTP và người nhận. Giữ quyền `0640` với chủ `root:www-data`.
5. Kiểm tra trang chủ, `/tin-tuc/`, `/admin/`, `/admin/seo/`, `/robots.txt` và `/sitemap.xml`. Thử bản nháp, ảnh và xem trước. Kiểm tra thư tới hộp thư nhận bằng dữ liệu thử được cho phép.

Tạo hash trong Bash qua STDIN.

```bash
read -rsp "Mat khau quan tri moi: " vs_admin_password; echo
printf '%s' "$vs_admin_password" | php -r 'echo password_hash(stream_get_contents(STDIN), PASSWORD_DEFAULT), PHP_EOL;'
unset vs_admin_password
```

Chép hash vào `admin_password_hash`. Chạy `php -l /var/www/vietnamskilled.org/config.php` sau khi lưu.

## Email

Với Gmail, cấu hình mặc định là `smtp.gmail.com`, cổng 587 và `starttls`. Điền tài khoản gửi, mật khẩu ứng dụng, hộp thư nhận và tên hiển thị. Gmail yêu cầu xác minh hai bước và chính sách tài khoản phải cho phép mật khẩu ứng dụng. Xem https://support.google.com/accounts/answer/185833. Với nhà cung cấp khác, dùng cổng và chế độ TLS họ cung cấp.

Trang chủ dùng Google Apps Script và PHP gửi email. Trang Liên hệ có trình gửi PHP và biểu mẫu bên thứ ba cũ. Xem các hạn chế đã biết trong tài liệu Word. Xác thực SMTP thành công chỉ xác nhận kết nối và quyền gửi. Kiểm tra hộp thư nhận để xác minh toàn bộ luồng.

## Cập nhật biểu mẫu ngày 03/11/2026

Ba biểu mẫu Liên hệ, Ứng viên và Doanh nghiệp dùng chung luồng gửi thư qua PHP. Trang chỉ báo thành công khi máy chủ xác nhận SMTP và trả mã yêu cầu. Hộp tư vấn nhanh cũng dùng luồng này. Các lỗi cũ về trường ẩn, JavaScript Liên hệ và báo thành công giả đã được sửa. Phần mô tả lỗi cũ trong tài liệu Word là lịch sử kiểm tra.

Ba thư kiểm thử được xác nhận trong Hộp thư đến của hộp nhận đã cấu hình. Google Apps Script là tích hợp bổ sung sau khi SMTP thành công. Bài thử email chặn việc ghi dữ liệu mẫu vào Sheets. Xem hai biên bản bàn giao và nghiệm thu để đối chiếu mã email và phạm vi xác nhận.

## Sao lưu và khôi phục

Sao lưu `public_html/`, `cms/`, `vendor/`, `cms-data/` và cấu hình riêng, Nginx, TLS của máy. Cất bản có thông tin xác thực ở nơi hạn chế truy cập. Giữ `cms-data/content.json` cùng thời điểm với `cms-data/media/`. Phục hồi vào máy thử nghiệm, chạy `php -l` và `nginx -t`, rồi kiểm tra trang công khai, quản trị, email và SEO trước khi chuyển DNS.

Khi máy đích đã có website, sao lưu máy đích và đối chiếu từng đường dẫn. Tránh ghi đè dữ liệu CMS mới. Trình cài máy mới sẽ dừng nếu đường dẫn đã tồn tại.

