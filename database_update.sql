-- Drop existing tables if they exist
DROP TABLE IF EXISTS order_items;
DROP TABLE IF EXISTS orders;
DROP TABLE IF EXISTS products;
DROP TABLE IF EXISTS categories;

-- Create new categories table with status column instead of active
CREATE TABLE categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL,
    description TEXT,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Create new products table with title instead of name and status instead of active
CREATE TABLE products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL,
    description TEXT,
    price DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    image_url VARCHAR(255),
    category VARCHAR(100),
    category_id INT,
    featured TINYINT(1) DEFAULT 0,
    status ENUM('active', 'inactive') DEFAULT 'active',
    stock INT DEFAULT 0,
    sku VARCHAR(50),
    weight DECIMAL(10, 2),
    dimensions VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
);

-- Create orders table
CREATE TABLE orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_number VARCHAR(50) NOT NULL,
    user_id INT,
    status ENUM('pending', 'processing', 'completed', 'cancelled') DEFAULT 'pending',
    total DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    subtotal DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    tax DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    shipping DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    discount DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    coupon_code VARCHAR(50),
    shipping_method VARCHAR(100),
    payment_method VARCHAR(100),
    payment_id VARCHAR(255),
    billing_name VARCHAR(255),
    billing_email VARCHAR(255),
    billing_phone VARCHAR(50),
    billing_address VARCHAR(255),
    billing_city VARCHAR(100),
    billing_state VARCHAR(100),
    billing_zip VARCHAR(20),
    billing_country VARCHAR(100),
    shipping_name VARCHAR(255),
    shipping_address VARCHAR(255),
    shipping_city VARCHAR(100),
    shipping_state VARCHAR(100),
    shipping_zip VARCHAR(20),
    shipping_country VARCHAR(100),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Create order items table
CREATE TABLE order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    product_id INT,
    product_title VARCHAR(255) NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    price DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    total DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL
);

-- Create PayPal settings table
CREATE TABLE paypal_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    client_id VARCHAR(255) NOT NULL,
    secret VARCHAR(255) NOT NULL,
    mode ENUM('sandbox', 'live') DEFAULT 'sandbox',
    currency VARCHAR(3) DEFAULT 'USD',
    business_name VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert sample categories
INSERT INTO categories (name, slug, description, status) VALUES
('Sports Cards', 'sports-cards', 'All sports trading cards including baseball, basketball, football, and hockey', 'active'),
('Trading Cards', 'trading-cards', 'Non-sports trading cards including Pokemon, Magic, and more', 'active'),
('Memorabilia', 'memorabilia', 'Sports memorabilia and collectibles', 'active'),
('Supplies', 'supplies', 'Card protection and display supplies', 'active');

-- Insert sample products
INSERT INTO products (title, slug, description, price, image_url, category, category_id, featured, status, stock, sku) VALUES
('2023 Topps Baseball Card Set', '2023-topps-baseball-card-set', 'Complete set of 2023 Topps Baseball Cards Series 1', 89.99, '/assets/images/products/topps-baseball-2023.jpg', 'Sports Cards', 1, 1, 'active', 15, 'TOP-BB-2023-S1'),
('Michael Jordan Rookie Card Reprint', 'michael-jordan-rookie-card-reprint', 'High-quality reprint of the classic Michael Jordan rookie card', 29.99, '/assets/images/products/jordan-rookie.jpg', 'Sports Cards', 1, 1, 'active', 25, 'NBA-MJ-ROOK-RP'),
('Pokemon Scarlet & Violet Booster Box', 'pokemon-sv-booster-box', 'Factory sealed Pokemon Scarlet & Violet booster box with 36 packs', 119.99, '/assets/images/products/pokemon-sv.jpg', 'Trading Cards', 2, 1, 'active', 8, 'PKM-SV-BB'),
('Ultra Pro Card Sleeves (100 count)', 'ultra-pro-card-sleeves', 'Standard size Ultra Pro card sleeves, pack of 100', 9.99, '/assets/images/products/card-sleeves.jpg', 'Supplies', 4, 0, 'active', 50, 'SUP-UP-SLV-100'),
('Signed Yankees Baseball', 'signed-yankees-baseball', 'Authentic team-signed New York Yankees baseball with certificate of authenticity', 299.99, '/assets/images/products/yankees-ball.jpg', 'Memorabilia', 3, 1, 'active', 3, 'MEM-NYY-BALL'),
('Card Display Case', 'card-display-case', 'Premium acrylic card display case with UV protection', 24.99, '/assets/images/products/display-case.jpg', 'Supplies', 4, 0, 'active', 35, 'SUP-DISP-CARD'),
('2023 Panini Football Cards', '2023-panini-football-cards', 'Box of 2023 Panini Football Cards', 64.99, '/assets/images/products/panini-football.jpg', 'Sports Cards', 1, 0, 'active', 12, 'PAN-FB-2023'),
('Magic: The Gathering Starter Kit', 'mtg-starter-kit', 'Magic: The Gathering starter kit with two ready-to-play decks', 19.99, '/assets/images/products/mtg-starter.jpg', 'Trading Cards', 2, 0, 'active', 20, 'MTG-START-KIT'),
('Autographed LeBron James Photo', 'lebron-james-autograph', 'Framed and authenticated autographed LeBron James photo', 499.99, '/assets/images/products/lebron-auto.jpg', 'Memorabilia', 3, 1, 'active', 2, 'MEM-LBJ-AUTO'),
('Card Storage Box', 'card-storage-box', 'Storage box that holds up to 1000 cards', 14.99, '/assets/images/products/storage-box.jpg', 'Supplies', 4, 0, 'active', 40, 'SUP-STOR-BOX'),
('2023 Upper Deck Hockey Cards', '2023-upper-deck-hockey', 'Box of 2023 Upper Deck Hockey Cards', 79.99, '/assets/images/products/upper-deck-hockey.jpg', 'Sports Cards', 1, 0, 'active', 10, 'UD-HKY-2023'),
('Yu-Gi-Oh! Structure Deck', 'yugioh-structure-deck', 'Ready-to-play Yu-Gi-Oh! structure deck', 15.99, '/assets/images/products/yugioh-deck.jpg', 'Trading Cards', 2, 0, 'active', 18, 'YGO-STRUCT-DK');

-- Insert PayPal settings
INSERT INTO paypal_settings (client_id, secret, mode, currency, business_name) VALUES
('YOUR_SANDBOX_CLIENT_ID', 'YOUR_SANDBOX_SECRET', 'sandbox', 'USD', 'Tristate Cards');
