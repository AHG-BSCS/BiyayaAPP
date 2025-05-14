-- Create the database if it doesn't exist
CREATE DATABASE IF NOT EXISTS church_db;
USE church_db;

-- Create site_settings table
CREATE TABLE IF NOT EXISTS site_settings (
    id INT PRIMARY KEY,
    church_name VARCHAR(255) NOT NULL,
    tagline VARCHAR(255) NOT NULL,
    tagline2 VARCHAR(255) NOT NULL,
    about_us TEXT NOT NULL,
    church_address TEXT NOT NULL,
    phone_number VARCHAR(50) NOT NULL,
    email_address VARCHAR(255) NOT NULL,
    church_logo VARCHAR(255) NOT NULL,
    login_background VARCHAR(255) NOT NULL,
    service_times TEXT NOT NULL,
    certificate_template VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert default values
INSERT INTO site_settings (id, church_name, tagline, tagline2, about_us, church_address, phone_number, email_address, church_logo, login_background, service_times)
VALUES (
    1,
    'Church of Christ-Disciples',
    'To God be the Glory',
    'Becoming Christlike and Blessing Others',
    'Welcome to Church of Christ-Disciples. We are a community of believers dedicated to sharing God\'s love and message with the world. Our mission is to create a welcoming environment where people from all walks of life can come together to worship, learn, and grow in their faith.',
    '25 Artemio B. Fule St., San Pablo City, Laguna 4000 Philippines',
    '0927 012 7127',
    'cocd1910@gmail.com',
    'logo/cocd_icon.png',
    'logo/churchpic.jpg',
    '{"Sunday Worship Service": "9:00 AM - 11:00 AM", "Prayer Intercession": "5:00 PM - 7:00 PM"}'
) ON DUPLICATE KEY UPDATE id = id; 