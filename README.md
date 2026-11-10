# Nova Phone — Phone Sales Management System

Full-stack smartphone e-commerce storefront and inventory management system built with **HTML5**, **CSS3**, **Vanilla JavaScript**, **Vanilla PHP 8**, and **MySQL (PDO)** for XAMPP.

## Key Features

### Customer Storefront

- **Homepage (`index.php`):** Responsive announcement bar, sticky search navbar, interactive hero slider, 5-brand showcase (Apple, Samsung, Xiaomi, OPPO, realme), featured smartphones, hot deals, and trust highlights.
- **Catalog & Filtering (`products.php`):** Keyword search, multi-criteria filtering (brand, price range, RAM, internal storage, promotions), sorting, removable filter chips, and pagination (`8` items/page).
- **Product Details (`product-detail.php`):** Image gallery, dynamic variant selector (RAM / storage / color swatch updating price, SKU, stock badge, and image), full hardware specifications, and related models.
- **Shopping Cart & Checkout (`cart.php`, `checkout.php`):** Guest session cart with automatic merge on login, stock-bounded quantity controls, COD & simulated bank transfer checkout, and atomic inventory deduction using `PDO::beginTransaction()` + `SELECT ... FOR UPDATE`.
- **Account & Support (`register.php`, `login.php`, `logout.php`, `profile.php`, `contact.php`):** Client & server validation, profile/password management, order history inspection, and customer contact inquiry form.

### Admin Portal (`/admin/`)

- **Role-Based Guard (`includes/admin_guard.php`):** Restricts `/admin/*` strictly to authenticated `admin` accounts (`403 Forbidden` otherwise).
- **Dashboard (`admin/index.php`):** Order, revenue, customer, and low-stock (`<= 5`) KPI cards plus recent orders & low-stock alerts.
- **Brands & Products (`admin/brands.php`, `admin/products.php`, `admin/product-form.php`):** Full CRUD, dynamic multi-variant editor, and secure image upload (`finfo_file()` MIME check, `<= 2MB`, randomized filenames, `.htaccess` script execution block).
- **Inventory, Orders, Customers & Messages (`admin/inventory.php`, `admin/orders.php`, `admin/order-detail.php`, `admin/customers.php`, `admin/messages.php`):** Direct SKU stock updates, order lifecycle management with automatic stock restoration on cancellation, customer spend stats, and contact inbox management.

## Security & Performance

- **SQL Injection Defense:** 100% PDO Prepared Statements with `PDO::ATTR_EMULATE_PREPARES = false`.
- **XSS & CSRF Defense:** `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')` on all HTML output and `random_bytes(32)` + `hash_equals()` CSRF tokens on all `POST` forms.
- **Session Hardening:** `httponly` + `samesite=Lax` cookies and `session_regenerate_id(true)` upon login/logout.
- **SEO & Caching:** Per-page dynamic meta tags, `robots.txt`, `sitemap.xml`, `mod_deflate`, and `mod_expires` via `.htaccess`.

## Tech Stack

- **Frontend:** HTML5, CSS3, Vanilla JavaScript
- **Backend:** Vanilla PHP 8+ (PDO, GD, Fileinfo)
- **Database:** MySQL / MariaDB (`utf8mb4`, InnoDB)
- **Server:** Apache (XAMPP on Windows)

## Local Setup (XAMPP)

1. Clone or place this repository inside `D:\xampp\htdocs\Phone-Sales` (if using a different folder name, update `BASE_URL` in `config/config.php`).
2. Start **Apache** and **MySQL** in the XAMPP Control Panel.
3. Import `database/schema.sql` and `database/seed.sql` into phpMyAdmin (`http://localhost/phpmyadmin`).
4. Visit `http://localhost/Phone-Sales/database/init_test_passwords.php` once from `localhost` to generate bcrypt password hashes for the seeded accounts:
   - **Admin:** `admin@novaphone.vn` / `Admin@123`
   - **Customer 1:** `khach1@novaphone.vn` / `Customer@123`
   - **Customer 2:** `khach2@novaphone.vn` / `Customer@123`
5. Open the storefront at `http://localhost/Phone-Sales/` and wireframes at `http://localhost/Phone-Sales/wireframes/`.

## Project Structure

```text
Phone-Sales/
├── admin/          # Admin portal pages (dashboard, brands, products, inventory, orders, customers, messages)
├── assets/         # CSS, JS, brand SVGs, WebP product images, and secured upload directory
├── config/         # App constants and PDO database connection
├── database/       # MySQL schema, seed data, and local test password initializer
├── docs/           # Functional, non-functional, use-case, and issues-log documentation
├── includes/       # Shared layouts, auth/admin guards, CSRF, and helper functions
├── wireframes/     # Static HTML/CSS UI wireframes for core storefront pages
└── *.php           # Storefront entry points (index, products, product-detail, cart, checkout, auth, profile, contact)
```

> **Note:** Brand logos and smartphone product images in `assets/images/` are used strictly for educational demonstration purposes.
