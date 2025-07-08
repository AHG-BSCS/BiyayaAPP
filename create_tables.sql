--database for tithes
CREATE TABLE IF NOT EXISTS tithes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    member_name VARCHAR(255) NOT NULL,
    date DATE NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
--database for offerings
CREATE TABLE IF NOT EXISTS offerings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    member_name VARCHAR(255) NOT NULL,
    date DATE NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS baptismal_records (
    id VARCHAR(20) PRIMARY KEY,
    name VARCHAR(255),
    nickname VARCHAR(255),
    address VARCHAR(255),
    telephone VARCHAR(50),
    cellphone VARCHAR(50),
    email VARCHAR(255),
    civil_status VARCHAR(50),
    sex VARCHAR(10),
    birthday DATE,
    father_name VARCHAR(255),
    mother_name VARCHAR(255),
    children TEXT,
    education VARCHAR(255),
    course VARCHAR(255),
    school VARCHAR(255),
    year VARCHAR(50),
    company VARCHAR(255),
    position VARCHAR(255),
    business VARCHAR(255),
    spiritual_birthday DATE,
    inviter VARCHAR(255),
    how_know TEXT,
    attendance_duration VARCHAR(255),
    previous_church VARCHAR(255),
    baptism_date DATE,
    officiant VARCHAR(255),
    venue VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
); 