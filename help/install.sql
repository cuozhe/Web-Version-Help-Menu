-- 数据库: help
CREATE DATABASE IF NOT EXISTS help DEFAULT CHARSET=utf8mb4;
USE help;

-- 管理员表
CREATE TABLE IF NOT EXISTS admin (
  id INT PRIMARY KEY AUTO_INCREMENT,
  username VARCHAR(50) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL
);
INSERT INTO admin (username, password) VALUES ('suoyi', MD5('cnmb594188'));

-- 板块表
CREATE TABLE IF NOT EXISTS sections (
  id INT PRIMARY KEY AUTO_INCREMENT,
  title VARCHAR(100) NOT NULL,
  sort_order INT DEFAULT 0,
  hidden TINYINT(1) DEFAULT 0
);

-- 菜单项表
CREATE TABLE IF NOT EXISTS items (
  id INT PRIMARY KEY AUTO_INCREMENT,
  section_id INT NOT NULL,
  title VARCHAR(100) NOT NULL,
  description VARCHAR(255),
  icon VARCHAR(255),
  url VARCHAR(255),
  sort_order INT DEFAULT 0,
  hidden TINYINT(1) DEFAULT 0,
  FOREIGN KEY (section_id) REFERENCES sections(id) ON DELETE CASCADE
);

-- 站点设置表
CREATE TABLE IF NOT EXISTS settings (
  id INT PRIMARY KEY AUTO_INCREMENT,
  site_title VARCHAR(100) DEFAULT '襙衣帮助',
  logo VARCHAR(255),
  background VARCHAR(255),
  card_opacity FLOAT DEFAULT 1
);
INSERT INTO settings (site_title, logo, background, card_opacity) VALUES ('襙衣帮助', '', '', 1); 