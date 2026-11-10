# Project Rules — Phone Sales Management System (NovaPhone)

## Tech Stack Constraints
- Pure HTML5, CSS3, Vanilla JavaScript, Vanilla PHP (no frameworks), MySQL, PDO, running on XAMPP (Windows) at `D:\xampp\htdocs\Phone-Sales`.
- MySQL connection: `127.0.0.1:3306`, database `phone_sales_db`, user `root`, empty password, `utf8mb4`.

## Coding Language & Localization Rules
- **100% English in Code:** All source code, identifiers, code comments, SQL schemas, seed data, UI copy, validation/error messages, flash alerts, and SEO metadata must be written in English.
- **Unaccented Vietnamese Addresses:** Any Vietnamese address or local proper name in code, config, or seed data must be written without diacritics (e.g., `128 Nguyen Van Cu, District 5, Ho Chi Minh City`).

## Security & Code Quality
- One responsibility per file; clear, descriptive filenames and readable logic with concise English comments on core flows (DB connection, auth, cart, checkout transaction, inventory management).
- Use PDO Prepared Statements for all SQL queries (`ATTR_EMULATE_PREPARES = false`).
- Escape all HTML output via `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')`.
- Enforce CSRF tokens on POST forms, `password_hash`/`password_verify`, `session_regenerate_id(true)` on login, strict admin role checks on `/admin/*`, and strict image upload validation.

