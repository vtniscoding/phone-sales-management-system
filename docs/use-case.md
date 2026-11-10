# Sơ đồ Use Case — Phone Sales Management System (NovaPhone)

## 1. Sơ đồ tổng quan các Actor và Ca sử dụng (Mermaid)

```mermaid
flowchart LR
    Guest["Khách vãng lai (Guest)"]
    Customer["Khách hàng (Customer)"]
    Admin["Quản trị viên (Admin)"]

    subgraph Storefront["Phân hệ Khách hàng (Storefront)"]
        UC_Home["Xem Trang chủ & Khuyến mãi"]
        UC_Browse["Tìm kiếm, Lọc & Xem DS sản phẩm"]
        UC_Detail["Xem Chi tiết & Chọn biến thể"]
        UC_Cart["Quản lý Giỏ hàng (Thêm/Sửa/Xóa)"]
        UC_Contact["Gửi tin nhắn Liên hệ"]
        UC_Auth["Đăng ký / Đăng nhập / Đăng xuất"]
        UC_Profile["Xem/Sửa Hồ sơ & Đổi mật khẩu"]
        UC_Checkout["Thanh toán & Đặt hàng (COD/CK)"]
        UC_OrderHist["Xem Lịch sử & Trạng thái đơn hàng"]
    end

    subgraph AdminSystem["Phân hệ Quản trị (Admin)"]
        UC_Dash["Xem Dashboard & Cảnh báo tồn kho"]
        UC_Brands["Quản lý Hãng điện thoại"]
        UC_Products["Quản lý Sản phẩm, Biến thể & Upload ảnh"]
        UC_Stock["Cập nhật Tồn kho"]
        UC_Orders["Quản lý Đơn hàng & Đổi trạng thái"]
        UC_Users["Xem Danh sách Khách hàng"]
        UC_Messages["Xem & Đánh dấu Tin nhắn liên hệ"]
    end

    Guest --> UC_Home
    Guest --> UC_Browse
    Guest --> UC_Detail
    Guest --> UC_Cart
    Guest --> UC_Contact
    Guest --> UC_Auth

    Customer -.->|"Kế thừa quyền Guest"| Guest
    Customer --> UC_Profile
    Customer --> UC_Checkout
    Customer --> UC_OrderHist

    Admin --> UC_Auth
    Admin --> UC_Dash
    Admin --> UC_Brands
    Admin --> UC_Products
    Admin --> UC_Stock
    Admin --> UC_Orders
    Admin --> UC_Users
    Admin --> UC_Messages
```

---

## 2. Mô tả tóm tắt theo từng Actor

### A. Khách vãng lai (Guest)

- Xem trang chủ (banner, hãng điện thoại, sản phẩm nổi bật, khuyến mãi).
- Tìm kiếm sản phẩm theo tên/chip, lọc theo hãng, khoảng giá, RAM, bộ nhớ trong, sắp xếp và phân trang.
- Xem chi tiết điện thoại, bảng thông số kỹ thuật và chọn màu/dung lượng để xem giá & tồn kho thực tế.
- Thêm sản phẩm vào giỏ hàng tạm (theo session), cập nhật số lượng hoặc xóa khỏi giỏ.
- Gửi form liên hệ và thực hiện đăng ký/đăng nhập.

### B. Khách hàng đã đăng nhập (Customer)

- Thực hiện toàn bộ chức năng của Guest.
- Thực hiện thanh toán (`checkout.php`): nhập địa chỉ giao hàng, chọn COD hoặc chuyển khoản mô phỏng, xác nhận tạo đơn hàng (hệ thống tự động trừ tồn kho).
- Xem/cập nhật thông tin cá nhân, đổi mật khẩu tại `profile.php`.
- Theo dõi lịch sử các đơn hàng đã đặt và trạng thái xử lý (`pending`, `confirmed`, `shipping`, `completed`, `cancelled`).

### C. Quản trị viên (Admin)

- Đăng nhập bằng tài khoản có `role = 'admin'` để truy cập khu vực `/admin/`.
- Theo dõi Dashboard: tổng số đơn hàng, tổng doanh thu, cảnh báo sản phẩm sắp hết hàng (`stock_qty <= 5`).
- Quản lý hãng điện thoại (`brands.php`), quản lý sản phẩm & các biến thể kèm upload ảnh (`products.php`, `product-form.php`).
- Xem và cập nhật nhanh số lượng tồn kho (`inventory.php`).
- Xem chi tiết đơn hàng và cập nhật trạng thái giao hàng (`orders.php`, `order-detail.php`).
- Xem danh sách khách hàng (`customers.php`) và tiếp nhận tin nhắn liên hệ (`messages.php`).
