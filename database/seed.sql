-- =============================================================================
-- SEED DATA: PHONE SALES MANAGEMENT SYSTEM (NovaPhone)
-- Import after database/schema.sql
-- Next step: visit http://localhost/Phone-Sales/database/init_test_passwords.php
-- once to generate real bcrypt password hashes using PHP password_hash().
-- =============================================================================

USE `phone_sales_db`;

SET FOREIGN_KEY_CHECKS = 0;
DELETE FROM `contact_messages`;
DELETE FROM `order_items`;
DELETE FROM `orders`;
DELETE FROM `cart_items`;
DELETE FROM `carts`;
DELETE FROM `product_images`;
DELETE FROM `product_variants`;
DELETE FROM `products`;
DELETE FROM `brands`;
DELETE FROM `users`;

ALTER TABLE `contact_messages` AUTO_INCREMENT = 1;
ALTER TABLE `order_items` AUTO_INCREMENT = 1;
ALTER TABLE `orders` AUTO_INCREMENT = 1;
ALTER TABLE `cart_items` AUTO_INCREMENT = 1;
ALTER TABLE `carts` AUTO_INCREMENT = 1;
ALTER TABLE `product_images` AUTO_INCREMENT = 1;
ALTER TABLE `product_variants` AUTO_INCREMENT = 1;
ALTER TABLE `products` AUTO_INCREMENT = 1;
ALTER TABLE `brands` AUTO_INCREMENT = 1;
ALTER TABLE `users` AUTO_INCREMENT = 1;
SET FOREIGN_KEY_CHECKS = 1;

-- -----------------------------------------------------------------------------
-- 1. Test Users (1 Admin, 2 Customers - unaccented Vietnamese names/addresses)
-- Default passwords after running init_test_passwords.php:
--   admin@novaphone.vn  -> Admin@123
--   khach1@novaphone.vn -> Customer@123
--   khach2@novaphone.vn -> Customer@123
-- -----------------------------------------------------------------------------
INSERT INTO `users` (`id`, `full_name`, `email`, `phone`, `password_hash`, `address`, `role`) VALUES
(1, 'Nguyen Quan Tri', 'admin@novaphone.vn', '0901000001', 'PENDING_INIT_BY_PHP', '128 Nguyen Van Cu, District 5, Ho Chi Minh City', 'admin'),
(2, 'Tran Minh Anh', 'khach1@novaphone.vn', '0912345678', 'PENDING_INIT_BY_PHP', '45 Le Loi, District 1, Ho Chi Minh City', 'customer'),
(3, 'Le Hoang Nam', 'khach2@novaphone.vn', '0987654321', 'PENDING_INIT_BY_PHP', '89 Cau Giay, Cau Giay District, Hanoi', 'customer');

-- -----------------------------------------------------------------------------
-- 2. Smartphone Brands (5 brands)
-- -----------------------------------------------------------------------------
INSERT INTO `brands` (`id`, `name`, `slug`, `logo_path`, `description`) VALUES
(1, 'Apple', 'apple', 'assets/images/brands/apple.svg', 'Official Apple iPhone devices with 12-month nationwide warranty.'),
(2, 'Samsung', 'samsung', 'assets/images/brands/samsung.svg', 'Galaxy S, Z Fold/Flip, and Galaxy A series authorized by Samsung Vietnam.'),
(3, 'Xiaomi', 'xiaomi', 'assets/images/brands/xiaomi.svg', 'Xiaomi, Redmi, and POCO smartphones known for flagship performance and fast charging.'),
(4, 'OPPO', 'oppo', 'assets/images/brands/oppo.svg', 'OPPO Find and Reno series featuring AI portrait photography and sleek designs.'),
(5, 'realme', 'realme', 'assets/images/brands/realme.svg', 'realme GT and C-Series phones with durable batteries and competitive pricing.');

-- -----------------------------------------------------------------------------
-- 3. Smartphone Products (20 models, 4 per brand)
-- -----------------------------------------------------------------------------
INSERT INTO `products` (`id`, `brand_id`, `name`, `slug`, `short_desc`, `description`, `screen_spec`, `chip_spec`, `battery_spec`, `camera_spec`, `os_spec`, `is_featured`, `promo_label`, `status`) VALUES
-- Apple (1-4)
(1, 1, 'iPhone 16 Pro Max', 'iphone-16-pro-max', 'Grade 5 Titanium frame, Camera Control button, and powerful A18 Pro chip.', 'iPhone 16 Pro Max delivers our largest 6.9-inch Super Retina XDR display, ultra-thin bezels, 4K 120fps Dolby Vision video, and exceptional battery life.', '6.9" Super Retina XDR OLED 120Hz', 'Apple A18 Pro (3nm)', '4685 mAh, 25W MagSafe fast charge', '48MP Main + 48MP Ultra Wide + 12MP 5x Telephoto', 'iOS 18', 1, '0% Installment', 'active'),
(2, 1, 'iPhone 16 Plus', 'iphone-16-plus', 'Expansive 6.7-inch display powered by A18 with Apple Intelligence.', 'Color-infused glass back, customizable Action button, and up to 27 hours of video playback.', '6.7" Super Retina XDR OLED 60Hz', 'Apple A18', '4674 mAh, 20W fast charge', '48MP Main + 12MP Ultra Wide', 'iOS 18', 1, 'Save 1.5M VND', 'active'),
(3, 1, 'iPhone 15', 'iphone-15', 'Dynamic Island experience, universal USB-C port, and 48MP camera.', 'A balanced everyday flagship combining crisp photography, smooth performance, and great value.', '6.1" Super Retina XDR OLED 60Hz', 'Apple A16 Bionic', '3349 mAh, 20W USB-C charge', '48MP Main + 12MP Ultra Wide', 'iOS 17', 0, 'Best Seller', 'active'),
(4, 1, 'iPhone 14', 'iphone-14', 'Durable aerospace-grade aluminum design with reliable all-day performance.', 'Ideal for users seeking a dependable iPhone with smooth Action Mode video stabilization.', '6.1" Super Retina XDR OLED 60Hz', 'Apple A15 Bionic', '3279 mAh, 20W fast charge', 'Dual 12MP Rear + 12MP Front', 'iOS 17', 0, 'Great Value', 'active'),

-- Samsung (5-8)
(5, 2, 'Samsung Galaxy S25 Ultra', 'samsung-galaxy-s25-ultra', 'Rounded Titanium frame, built-in S Pen, and next-gen Galaxy AI.', 'Samsung flagship powered by Snapdragon 8 Elite for Galaxy, 200MP quad camera system, and anti-reflective Gorilla Armor glass.', '6.9" Dynamic AMOLED 2X 120Hz QHD+', 'Snapdragon 8 Elite for Galaxy', '5000 mAh, 45W fast charge', '200MP Main + 50MP + 10MP + 50MP Telephoto', 'Android 15 (One UI 7)', 1, 'Save 3M VND', 'active'),
(6, 2, 'Samsung Galaxy Z Flip6', 'samsung-galaxy-z-flip6', 'Compact clamshell foldable with smart FlexWindow and AI zoom.', 'Reinforced FlexHinge durability paired with a 50MP wide camera and Auto Zoom framing.', '6.7" Main AMOLED 120Hz + 3.4" Cover', 'Snapdragon 8 Gen 3 for Galaxy', '4000 mAh, 25W fast charge', '50MP Main + 12MP Ultra Wide', 'Android 14 (One UI 6.1)', 1, 'Free Official Case', 'active'),
(7, 2, 'Samsung Galaxy S24 FE', 'samsung-galaxy-s24-fe', 'Flagship Galaxy AI features at an approachable mid-high price.', 'Bright 6.7-inch outdoor display, IP68 water resistance, and extended OS support.', '6.7" Dynamic AMOLED 2X 120Hz', 'Exynos 2400e', '4700 mAh, 25W fast charge', '50MP Main + 8MP Tele + 12MP Ultra Wide', 'Android 14', 0, 'Save 2M VND', 'active'),
(8, 2, 'Samsung Galaxy A55 5G', 'samsung-galaxy-a55-5g', 'Refined metal frame with IP67 water resistance and Knox Vault security.', 'Popular mid-range smartphone with a vibrant 120Hz Super AMOLED panel and 5000 mAh battery.', '6.6" Super AMOLED 120Hz FHD+', 'Exynos 1480', '5000 mAh, 25W fast charge', '50MP OIS + 12MP + 5MP', 'Android 14', 0, NULL, 'active'),

-- Xiaomi (9-12)
(9, 3, 'Xiaomi 14 Ultra', 'xiaomi-14-ultra', 'Quad Leica Summilux lens system with variable aperture.', 'Co-engineered with Leica for pro mobile photography, wrapped in wear-resistant nano-tech vegan leather.', '6.73" LTPO AMOLED 120Hz 2K', 'Snapdragon 8 Gen 3', '5000 mAh, 90W HyperCharge', 'Quad 50MP Leica 1-inch Sensor', 'HyperOS (Android 14)', 1, 'Free Leica Kit', 'active'),
(10, 3, 'Xiaomi 14T Pro', 'xiaomi-14t-pro', 'Leica night photography mastery and blazing 120W HyperCharge.', 'Flagship Dimensity power paired with an ultra-smooth 144Hz 1.5K AI display.', '6.67" AMOLED 144Hz 1.5K', 'MediaTek Dimensity 9300+', '5000 mAh, 120W HyperCharge', '50MP Leica Main + 50MP Tele + 12MP', 'HyperOS', 1, '0% Installment', 'active'),
(11, 3, 'Redmi Note 14 Pro 5G', 'redmi-note-14-pro-5g', 'Curved 1.5K AMOLED display and 200MP OIS camera.', 'IP68 dust and water resistance with Gorilla Glass Victus 2 protection in the mid-range segment.', '6.67" Curved AMOLED 120Hz 1.5K', 'Dimensity 7300-Ultra', '5110 mAh, 45W turbo charge', '200MP OIS + 8MP + 2MP', 'HyperOS', 0, 'Save 500K VND', 'active'),
(12, 3, 'POCO X7 Pro', 'poco-x7-pro', 'Gaming powerhouse with Dimensity 8400-Ultra and 6000mAh battery.', 'Equipped with LiquidCool thermal technology and a high-density silicon-carbon battery.', '6.67" CrystalRes AMOLED 120Hz', 'Dimensity 8400-Ultra', '6000 mAh, 90W fast charge', '50MP OIS + 8MP', 'HyperOS 2', 0, 'Hot Gaming', 'active'),

-- OPPO (13-16)
(13, 4, 'OPPO Find X8 Pro', 'oppo-find-x8-pro', 'Dual periscope Hasselblad telephoto cameras and Quick Button.', 'Powered by Dimensity 9400 with a slim 5910 mAh Silicon-Carbon battery.', '6.78" LTPO AMOLED 120Hz', 'MediaTek Dimensity 9400', '5910 mAh, 80W SuperVOOC', 'Quad 50MP Hasselblad Dual Periscope', 'ColorOS 15 (Android 15)', 1, 'Exclusive Deal', 'active'),
(14, 4, 'OPPO Reno13 5G', 'oppo-reno13-5g', 'Luminous butterfly wing design with IP69 underwater AI photography.', 'Aerospace-grade aluminum frame with AI Livephoto and AI Eraser tools.', '6.59" AMOLED 120Hz 1.5K', 'Dimensity 8350', '5600 mAh, 80W SuperVOOC', '50MP OIS + 8MP + 50MP Selfie', 'ColorOS 15', 1, 'New Arrival', 'active'),
(15, 4, 'OPPO Reno12 F 5G', 'oppo-reno12-f-5g', 'Halo Light camera ring with AI LinkBoost connectivity.', 'Youthful styling packed with everyday AI portrait and productivity tools.', '6.67" AMOLED 120Hz', 'Dimensity 6300', '5000 mAh, 45W SuperVOOC', '50MP + 8MP + 2MP', 'ColorOS 14', 0, NULL, 'active'),
(16, 4, 'OPPO A3x', 'oppo-a3x', 'Military-grade shock resistance and 1000-nit splash-touch screen.', 'Built for students and outdoor workers needing durability and fast charging on a budget.', '6.67" IPS LCD 90Hz', 'Snapdragon 6s 4G Gen 1', '5100 mAh, 45W SuperVOOC', '8MP Rear + 5MP Front', 'ColorOS 14', 0, 'Student Deal', 'active'),

-- realme (17-20)
(17, 5, 'realme GT 7 Pro', 'realme-gt-7-pro', 'Snapdragon 8 Elite flagship with massive 6500mAh battery and 120W charge.', 'Ultra-bright Eco2 OLED Plus display and underwater photography mode.', '6.78" Eco2 OLED Plus 120Hz', 'Snapdragon 8 Elite', '6500 mAh, 120W SuperVOOC', '50MP Main + 50MP 3x Periscope + 8MP', 'realme UI 6.0 (Android 15)', 1, 'Save 1M VND', 'active'),
(18, 5, 'realme 13 Pro+ 5G', 'realme-13-pro-plus-5g', 'Dual Sony sensors featuring a 3x periscope portrait lens.', 'Monet-inspired frosted glass design with reliable 80W fast charging.', '6.7" Curved AMOLED 120Hz', 'Snapdragon 7s Gen 2', '5200 mAh, 80W fast charge', '50MP Sony LYT-701 + 50MP Periscope + 8MP', 'realme UI 5.0', 0, '0% Installment', 'active'),
(19, 5, 'realme C75', 'realme-c75', 'Rare IP69 water and dust resistance in the budget segment.', 'ArmorShell shock protection paired with a long-lasting 6000 mAh battery.', '6.72" IPS LCD 90Hz FHD+', 'Helio G92 Max', '6000 mAh, 45W fast charge', '50MP Rear + 8MP Front', 'realme UI 5.0', 0, 'IP69 Durable', 'active'),
(20, 5, 'realme Note 60', 'realme-note-60', 'Slim body with ArmorShell die-cast metal protection.', 'Affordable everyday phone for calls, study, and social media.', '6.74" IPS LCD 90Hz', 'Unisoc T612', '5000 mAh, 10W charge', '32MP Rear + 5MP Front', 'realme UI (Android 14)', 0, 'Budget Pick', 'active');

-- -----------------------------------------------------------------------------
-- 4. Product Variants (42 variants across RAM/Storage/Color/Price/Stock)
-- Includes low-stock (<= 5) and out-of-stock (0) variants for inventory & checkout testing
-- -----------------------------------------------------------------------------
INSERT INTO `product_variants` (`id`, `product_id`, `ram`, `storage`, `color_name`, `color_hex`, `price`, `original_price`, `stock_qty`, `sku`, `is_default`) VALUES
-- Product 1: iPhone 16 Pro Max (1..3)
(1, 1, '8GB', '256GB', 'Desert Titanium', '#C5A98E', 33990000, 34990000, 14, 'IP16PM-256-DES', 1),
(2, 1, '8GB', '512GB', 'Natural Titanium', '#9E9B95', 39990000, 40990000, 4, 'IP16PM-512-NAT', 0),
(3, 1, '8GB', '1TB', 'Black Titanium', '#27272A', 45990000, 46990000, 2, 'IP16PM-1TB-BLK', 0),

-- Product 2: iPhone 16 Plus (4..5)
(4, 2, '8GB', '128GB', 'Ultramarine', '#647DEE', 24490000, 25990000, 12, 'IP16P-128-BLU', 1),
(5, 2, '8GB', '256GB', 'Pink', '#F472B6', 27490000, 28990000, 7, 'IP16P-256-PNK', 0),

-- Product 3: iPhone 15 (6..7)
(6, 3, '6GB', '128GB', 'Black', '#18181B', 19490000, 22990000, 18, 'IP15-128-BLK', 1),
(7, 3, '6GB', '256GB', 'Blue', '#93C5FD', 22490000, 25990000, 9, 'IP15-256-BLU', 0),

-- Product 4: iPhone 14 (8..9)
(8, 4, '6GB', '128GB', 'Starlight', '#F1F5F9', 15990000, 17990000, 11, 'IP14-128-STR', 1),
(9, 4, '6GB', '256GB', 'Purple', '#C4B5FD', 18490000, 19990000, 0, 'IP14-256-PRP', 0),

-- Product 5: Samsung Galaxy S25 Ultra (10..12)
(10, 5, '12GB', '256GB', 'Titanium Gray', '#64748B', 31990000, 33990000, 15, 'S25U-256-GRY', 1),
(11, 5, '12GB', '512GB', 'Titanium Silverblue', '#475569', 35490000, 37490000, 6, 'S25U-512-BLU', 0),
(12, 5, '12GB', '1TB', 'Titanium Black', '#0F172A', 41990000, 44490000, 3, 'S25U-1TB-BLK', 0),

-- Product 6: Samsung Galaxy Z Flip6 (13..14)
(13, 6, '12GB', '256GB', 'Mint', '#6EE7B7', 23990000, 28990000, 8, 'ZF6-256-MNT', 1),
(14, 6, '12GB', '512GB', 'Yellow', '#FDE047', 26990000, 32990000, 4, 'ZF6-512-YLW', 0),

-- Product 7: Samsung Galaxy S24 FE (15..16)
(15, 7, '8GB', '128GB', 'Blue', '#38BDF8', 14990000, 16990000, 16, 'S24FE-128-BLU', 1),
(16, 7, '8GB', '256GB', 'Graphite', '#334155', 16490000, 18490000, 10, 'S24FE-256-BLK', 0),

-- Product 8: Samsung Galaxy A55 5G (17..18)
(17, 8, '8GB', '128GB', 'Awesome Iceblue', '#BAE6FD', 8990000, 9990000, 22, 'A55-128-ICE', 1),
(18, 8, '12GB', '256GB', 'Awesome Lilac', '#DDD6FE', 10490000, 11490000, 13, 'A55-256-LIL', 0),

-- Product 9: Xiaomi 14 Ultra (19..20)
(19, 9, '16GB', '512GB', 'Black Vegan Leather', '#1E293B', 27990000, 32990000, 5, 'XM14U-512-BLK', 1),
(20, 9, '16GB', '512GB', 'White', '#F8FAFC', 27990000, 32990000, 3, 'XM14U-512-WHT', 0),

-- Product 10: Xiaomi 14T Pro (21..22)
(21, 10, '12GB', '512GB', 'Titan Black', '#1F2937', 16990000, 17990000, 14, 'XM14TP-512-BLK', 1),
(22, 10, '12GB', '256GB', 'Titan Gray', '#9CA3AF', 15490000, 16990000, 9, 'XM14TP-256-GRY', 0),

-- Product 11: Redmi Note 14 Pro 5G (23..24)
(23, 11, '8GB', '256GB', 'Lavender Purple', '#A78BFA', 8990000, 9490000, 25, 'RN14P-256-PRP', 1),
(24, 11, '12GB', '512GB', 'Midnight Black', '#111827', 10490000, 10990000, 11, 'RN14P-512-BLK', 0),

-- Product 12: POCO X7 Pro (25..26)
(25, 12, '8GB', '256GB', 'Yellow Black', '#EAB308', 9490000, 9990000, 19, 'PX7P-256-YLW', 1),
(26, 12, '12GB', '512GB', 'Emerald Green', '#10B981', 10990000, 11490000, 8, 'PX7P-512-GRN', 0),

-- Product 13: OPPO Find X8 Pro (27..28)
(27, 13, '16GB', '512GB', 'Pearl White', '#F1F5F9', 28990000, 29990000, 7, 'FX8P-512-WHT', 1),
(28, 13, '16GB', '512GB', 'Space Black', '#0F172A', 28990000, 29990000, 4, 'FX8P-512-BLK', 0),

-- Product 14: OPPO Reno13 5G (29..30)
(29, 14, '12GB', '256GB', 'Plume Blue', '#60A5FA', 15990000, NULL, 17, 'RN13-256-BLU', 1),
(30, 14, '12GB', '512GB', 'Plume White', '#E2E8F0', 17490000, NULL, 9, 'RN13-512-WHT', 0),

-- Product 15: OPPO Reno12 F 5G (31..32)
(31, 15, '8GB', '256GB', 'Amber Orange', '#F97316', 8490000, 9490000, 15, 'RN12F-256-ORG', 1),
(32, 15, '12GB', '256GB', 'Olive Green', '#065F46', 9290000, 9990000, 10, 'RN12F-256-GRN', 0),

-- Product 16: OPPO A3x (33..34)
(33, 16, '4GB', '64GB', 'Nebula Red', '#DC2626', 3490000, NULL, 30, 'OA3X-64-RED', 1),
(34, 16, '6GB', '128GB', 'Ocean Blue', '#0284C7', 4290000, NULL, 20, 'OA3X-128-BLU', 0),

-- Product 17: realme GT 7 Pro (35..36)
(35, 17, '12GB', '256GB', 'Mars Orange', '#EA580C', 21990000, 22990000, 6, 'RGT7P-256-ORG', 1),
(36, 17, '16GB', '512GB', 'Galaxy Grey', '#475569', 24990000, 25990000, 3, 'RGT7P-512-GRY', 0),

-- Product 18: realme 13 Pro+ 5G (37..38)
(37, 18, '8GB', '256GB', 'Monet Gold', '#FACC15', 11490000, 12990000, 12, 'R13PP-256-GLD', 1),
(38, 18, '12GB', '512GB', 'Emerald Green', '#059669', 12990000, 13990000, 0, 'R13PP-512-GRN', 0),

-- Product 19: realme C75 (39..40)
(39, 19, '8GB', '128GB', 'Lightning Gold', '#FBBF24', 5290000, 5690000, 24, 'RC75-128-GLD', 1),
(40, 19, '8GB', '256GB', 'Storm Black', '#1E293B', 5990000, 6490000, 14, 'RC75-256-BLK', 0),

-- Product 20: realme Note 60 (41..42)
(41, 20, '4GB', '64GB', 'Voyage Blue', '#3B82F6', 2890000, 3090000, 35, 'RN60-64-BLU', 1),
(42, 20, '6GB', '128GB', 'Marble Black', '#18181B', 3490000, 3790000, 21, 'RN60-128-BLK', 0);

-- -----------------------------------------------------------------------------
-- 5. Product Primary Images (20 WebP paths with descriptive SEO alt text)
-- -----------------------------------------------------------------------------
INSERT INTO `product_images` (`product_id`, `variant_id`, `image_path`, `alt_text`, `is_primary`, `sort_order`) VALUES
(1, NULL, 'assets/images/products/iphone-16-pro-max.webp', 'iPhone 16 Pro Max Desert Titanium smartphone', 1, 1),
(2, NULL, 'assets/images/products/iphone-16-plus.webp', 'iPhone 16 Plus Ultramarine smartphone', 1, 1),
(3, NULL, 'assets/images/products/iphone-15.webp', 'Apple iPhone 15 with Dynamic Island', 1, 1),
(4, NULL, 'assets/images/products/iphone-14.webp', 'Apple iPhone 14 official VN/A edition', 1, 1),
(5, NULL, 'assets/images/products/samsung-galaxy-s25-ultra.webp', 'Samsung Galaxy S25 Ultra with S Pen', 1, 1),
(6, NULL, 'assets/images/products/samsung-galaxy-z-flip6.webp', 'Samsung Galaxy Z Flip6 foldable phone', 1, 1),
(7, NULL, 'assets/images/products/samsung-galaxy-s24-fe.webp', 'Samsung Galaxy S24 FE with Galaxy AI', 1, 1),
(8, NULL, 'assets/images/products/samsung-galaxy-a55-5g.webp', 'Samsung Galaxy A55 5G metal frame', 1, 1),
(9, NULL, 'assets/images/products/xiaomi-14-ultra.webp', 'Xiaomi 14 Ultra Leica camera flagship', 1, 1),
(10, NULL, 'assets/images/products/xiaomi-14t-pro.webp', 'Xiaomi 14T Pro 120W HyperCharge smartphone', 1, 1),
(11, NULL, 'assets/images/products/redmi-note-14-pro-5g.webp', 'Redmi Note 14 Pro 5G 200MP camera phone', 1, 1),
(12, NULL, 'assets/images/products/poco-x7-pro.webp', 'POCO X7 Pro gaming smartphone', 1, 1),
(13, NULL, 'assets/images/products/oppo-find-x8-pro.webp', 'OPPO Find X8 Pro Hasselblad camera phone', 1, 1),
(14, NULL, 'assets/images/products/oppo-reno13-5g.webp', 'OPPO Reno13 5G Plume Blue smartphone', 1, 1),
(15, NULL, 'assets/images/products/oppo-reno12-f-5g.webp', 'OPPO Reno12 F 5G Halo Light phone', 1, 1),
(16, NULL, 'assets/images/products/oppo-a3x.webp', 'OPPO A3x durable budget smartphone', 1, 1),
(17, NULL, 'assets/images/products/realme-gt-7-pro.webp', 'realme GT 7 Pro Snapdragon 8 Elite flagship', 1, 1),
(18, NULL, 'assets/images/products/realme-13-pro-plus-5g.webp', 'realme 13 Pro+ 5G Monet Gold phone', 1, 1),
(19, NULL, 'assets/images/products/realme-c75.webp', 'realme C75 IP69 rugged smartphone', 1, 1),
(20, NULL, 'assets/images/products/realme-note-60.webp', 'realme Note 60 entry-level smartphone', 1, 1);

-- -----------------------------------------------------------------------------
-- 6. Sample Orders & Order Items (4 orders across different statuses)
-- -----------------------------------------------------------------------------
INSERT INTO `orders` (`id`, `order_code`, `user_id`, `customer_name`, `customer_phone`, `shipping_address`, `payment_method`, `payment_status`, `order_status`, `total_amount`, `note`) VALUES
(1, 'NP20261101', 2, 'Tran Minh Anh', '0912345678', '45 Le Loi, District 1, Ho Chi Minh City', 'bank_transfer', 'paid', 'completed', 33990000, 'Please deliver during office hours'),
(2, 'NP20261102', 2, 'Tran Minh Anh', '0912345678', '45 Le Loi, District 1, Ho Chi Minh City', 'cod', 'unpaid', 'shipping', 16990000, 'Call 15 minutes before arrival'),
(3, 'NP20261103', 3, 'Le Hoang Nam', '0987654321', '89 Cau Giay, Cau Giay District, Hanoi', 'bank_transfer', 'paid', 'confirmed', 42570000, 'Include company VAT invoice'),
(4, 'NP20261104', 3, 'Le Hoang Nam', '0987654321', '89 Cau Giay, Cau Giay District, Hanoi', 'cod', 'unpaid', 'pending', 15990000, NULL);

INSERT INTO `order_items` (`order_id`, `variant_id`, `product_name`, `variant_label`, `unit_price`, `quantity`, `subtotal`) VALUES
(1, 1, 'iPhone 16 Pro Max', '8GB / 256GB - Desert Titanium', 33990000, 1, 33990000),
(2, 21, 'Xiaomi 14T Pro', '12GB / 512GB - Titan Black', 16990000, 1, 16990000),
(3, 10, 'Samsung Galaxy S25 Ultra', '12GB / 256GB - Titanium Gray', 31990000, 1, 31990000),
(3, 39, 'realme C75', '8GB / 128GB - Lightning Gold', 5290000, 2, 10580000),
(4, 29, 'OPPO Reno13 5G', '12GB / 256GB - Plume Blue', 15990000, 1, 15990000);

-- -----------------------------------------------------------------------------
-- 7. Sample Contact Messages (unaccented Vietnamese names/addresses)
-- -----------------------------------------------------------------------------
INSERT INTO `contact_messages` (`name`, `email`, `phone`, `subject`, `message`, `is_read`) VALUES
('Pham Quoc Bao', 'bao.pham@example.com', '0909112233', '0% installment inquiry for iPhone 16 Pro Max', 'Does your store support 0% credit card installment for Vietcombank cards?', 1),
('Nguyen Thi Lan', 'lan.nguyen@example.com', '0938445566', 'Samsung Galaxy S25 Ultra trade-in inquiry', 'I would like to ask about your trade-in policy at the District 5 branch.', 0),
('Do Van Hung', 'hung.do@example.com', '0977889900', 'Bulk quote for company order', 'We need a quotation for 5 units of Redmi Note 14 Pro 5G 8GB/256GB.', 0);
