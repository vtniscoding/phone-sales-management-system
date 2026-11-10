# Đặc tả Yêu cầu Phi Chức năng (Non-Functional Requirements)

Tài liệu quy định các tiêu chuẩn kỹ thuật về bảo mật, hiệu năng, SEO, giao diện và khả năng bảo trì cho hệ thống **Phone Sales Management System (NovaPhone)**.

## 1. Bảo mật (Security)
- **Mã hóa mật khẩu:** Toàn bộ mật khẩu người dùng bắt buộc mã hóa một chiều bằng `password_hash($password, PASSWORD_DEFAULT)` (bcrypt) và xác thực bằng `password_verify()`. Không bao giờ lưu hoặc ghi log mật khẩu dạng plain-text.
- **Chống SQL Injection:** 100% truy vấn cơ sở dữ liệu sử dụng **PDO Prepared Statements** (`$pdo->prepare(...)` và `execute([...])`), kết hợp tắt `PDO::ATTR_EMULATE_PREPARES = false`.
- **Chống XSS (Cross-Site Scripting):** Mọi dữ liệu người dùng hoặc dữ liệu từ DB trước khi xuất ra HTML đều đi qua hàm escape `e($str)` sử dụng `htmlspecialchars($str, ENT_QUOTES, 'UTF-8')`.
- **Chống CSRF (Cross-Site Request Forgery):** Mọi form thao tác dữ liệu (`POST`) trên cả Customer và Admin đều gắn `<input type="hidden" name="csrf_token">` sinh từ `random_bytes(32)` trong session và xác thực bằng `hash_equals()`.
- **Bảo vệ Session:** Gọi `session_regenerate_id(true)` ngay sau khi đăng nhập thành công; thiết lập cờ cookie `httponly` và `samesite = Lax`.
- **Kiểm soát truy cập (RBAC):** Mọi trang trong `/admin/*` đều gọi `admin_guard.php` ở dòng đầu tiên để xác nhận đã đăng nhập và `$_SESSION['user']['role'] === 'admin'`. Guest hoặc Customer truy cập trái phép sẽ bị chặn (`403 Forbidden` hoặc chuyển hướng an toàn).
- **An toàn Upload File:** Khi Admin upload ảnh sản phẩm, PHP kiểm tra kích thước tối đa (`<= 2MB`), kiểm tra MIME thực sự của file bằng `finfo_file()` (chỉ nhận `image/jpeg`, `image/png`, `image/webp`), đổi tên file ngẫu nhiên an toàn và đặt `.htaccess` chặn thực thi `.php` trong thư mục `assets/uploads/`.
- **Toàn vẹn tồn kho:** Quy trình đặt hàng trong `checkout.php` và hủy đơn hoàn kho trong `admin/order-detail.php` chạy bên trong `PDO::beginTransaction()` với khóa dòng `SELECT ... FOR UPDATE` để tránh tình trạng bán âm kho khi có nhiều giao dịch đồng thời.

## 2. Hiệu năng (Performance)
- **Tối ưu hình ảnh:** Ưu tiên định dạng ảnh **WebP** nhẹ, có thuộc tính `width`/`height` rõ ràng chống giật bố cục (CLS) và gắn `loading="lazy"` cho ảnh sản phẩm bên dưới màn hình đầu tiên.
- **Nén & Bộ nhớ đệm HTTP:** Cấu hình file `.htaccess` trên Apache (XAMPP) bật `mod_deflate` (nén gzip cho HTML/CSS/JS/SVG/XML) và `mod_expires` (cache tài nguyên tĩnh CSS/JS/WebP).
- **Phân trang dữ liệu:** Trang danh sách sản phẩm (`products.php`) và các danh sách quản trị (`admin/orders.php`, `admin/products.php`) sử dụng `LIMIT` / `OFFSET` với các `INDEX` đã đánh sẵn trên `brand_id`, `ram`, `storage`, `price`, `order_status`.
- **Tài nguyên gọn nhẹ:** Sử dụng HTML/CSS/JavaScript thuần, không kéo thư viện JS/CSS nặng không cần thiết giúp thời gian tải trang nhanh trên localhost và mạng yếu.

## 3. Tối ưu công cụ tìm kiếm (SEO)
- **Thẻ Meta riêng từng trang:** `header.php` nhận biến `$pageTitle` và `$pageDescription` động từ từng trang (đặc biệt trang chi tiết điện thoại lấy theo tên sản phẩm + thông số nổi bật).
- **Cấu trúc Semantic HTML & Heading:** Sử dụng đúng thẻ ngữ nghĩa (`<header>`, `<nav>`, `<main>`, `<section>`, `<article>`, `<footer>`); mỗi trang chỉ có duy nhất một thẻ `<h1>`, phân cấp `<h2>`, `<h3>` hợp lý.
- **Thuộc tính `alt` và URL rõ ràng:** Mọi thẻ `<img>` đều có `alt` mô tả sản phẩm tiếng Việt có dấu; URL tham số lọc rõ nghĩa (`products.php?brand=apple&ram=8GB`).
- **Tệp hỗ trợ bot tìm kiếm:** Cung cấp `robots.txt` (chặn index `/admin/`, `/config/`, `/database/`) và `sitemap.xml` liệt kê các trang công khai chính.

## 4. Tương thích & Giao diện (Compatibility & UI/UX)
- **Responsive Design:** Hỗ trợ tốt 3 dải màn hình: Mobile (`375px–639px`), Tablet (`640px–1023px`) và Desktop (`>= 1024px`), không bị tràn ngang.
- **Đa trình duyệt:** Kiểm thử hoạt động ổn định trên Chrome, Microsoft Edge và Firefox phiên bản hiện đại.
- **Trạng thái giao diện đầy đủ:** Có thông báo thành công/lỗi rõ ràng (alert/flash message), trạng thái nút bấm khi gửi form, và trạng thái trống (empty state) thân thiện khi tìm kiếm không thấy máy hoặc giỏ hàng rỗng.

## 5. Khả năng bảo trì & giải thích (Maintainability)
- Mã nguồn tách biệt rõ ràng từng file theo chức năng (`config/`, `includes/`, `admin/`, `assets/`, `database/`).
- Có comment tiếng Việt tại các luồng xử lý cốt lõi (kết nối PDO, đăng ký/đăng nhập, giỏ hàng, đặt hàng transaction, kiểm tra quyền admin, upload ảnh) để phục vụ bảo vệ đồ án trực tiếp.

