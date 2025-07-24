-- Extended Database Schema for Albums Gallery Application
-- This includes the original structure plus additional tables needed for the admin system

-- Drop existing tables if they exist (for clean installation)
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS album_deletion_logs;
DROP TABLE IF EXISTS login_logs;
DROP TABLE IF EXISTS album_images;
DROP TABLE IF EXISTS albums;
DROP TABLE IF EXISTS activity_images;
DROP TABLE IF EXISTS activities;
DROP TABLE IF EXISTS admin_users;
DROP TABLE IF EXISTS album_categories;
DROP TABLE IF EXISTS categories;
SET FOREIGN_KEY_CHECKS = 1;

-- ตารางสำหรับหมวดหมู่ (เดิม)
CREATE TABLE categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ตารางสำหรับกิจกรรม (เดิม)
CREATE TABLE activities (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_id INT,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
);

-- ตารางสำหรับรูปภาพกิจกรรม (เดิม)
CREATE TABLE activity_images (
    id INT AUTO_INCREMENT PRIMARY KEY,
    activity_id INT,
    image_path VARCHAR(255) NOT NULL,
    image_order INT DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (activity_id) REFERENCES activities(id) ON DELETE CASCADE
);

-- ตารางสำหรับผู้ดูแลระบบ
CREATE TABLE admin_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    role ENUM('admin', 'moderator', 'editor') DEFAULT 'editor',
    status ENUM('active', 'inactive') DEFAULT 'active',
    last_login TIMESTAMP NULL,
    login_attempts INT DEFAULT 0,
    locked_until TIMESTAMP NULL,
    remember_token VARCHAR(255) NULL,
    remember_expires TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- ตารางสำหรับ login logs
CREATE TABLE login_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    username VARCHAR(50) NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    user_agent TEXT,
    status ENUM('success', 'failed') NOT NULL,
    failure_reason VARCHAR(255) NULL,
    login_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES admin_users(id) ON DELETE SET NULL
);

-- ตารางสำหรับหมวดหมู่ Albums
CREATE TABLE album_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    icon VARCHAR(50) DEFAULT 'fas fa-folder',
    color VARCHAR(20) DEFAULT 'bg-blue-600',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ตารางสำหรับ Albums
CREATE TABLE albums (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    cover_image VARCHAR(255) NULL,
    status ENUM('active', 'inactive') DEFAULT 'active',
    view_count INT DEFAULT 0,
    date_created DATE NOT NULL,
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES album_categories(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES admin_users(id) ON DELETE SET NULL
);

-- ตารางสำหรับรูปภาพใน Albums
CREATE TABLE album_images (
    id INT AUTO_INCREMENT PRIMARY KEY,
    album_id INT NOT NULL,
    image_path VARCHAR(255) NOT NULL,
    image_title VARCHAR(200) DEFAULT '',
    image_description TEXT DEFAULT '',
    image_order INT DEFAULT 1,
    is_cover TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (album_id) REFERENCES albums(id) ON DELETE CASCADE
);

-- ตารางสำหรับ log การลบ Albums (optional)
CREATE TABLE album_deletion_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    album_id INT NOT NULL,
    album_title VARCHAR(200) NOT NULL,
    deleted_by INT NULL,
    deleted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    image_count INT DEFAULT 0,
    FOREIGN KEY (deleted_by) REFERENCES admin_users(id) ON DELETE SET NULL
);

-- เพิ่มข้อมูลตัวอย่าง

-- ข้อมูลหมวดหมู่เดิม
INSERT INTO categories (name, description) VALUES
('กีฬา', 'กิจกรรมด้านกีฬาและออกกำลังกาย'),
('ศิลปะ', 'กิจกรรมด้านศิลปะและความคิดสร้างสรรค์'),
('วิทยาศาสตร์', 'กิจกรรมด้านวิทยาศาสตร์และเทคโนโลยี'),
('ดนตรี', 'กิจกรรมด้านดนตรีและการแสดง'),
('การเรียนรู้', 'กิจกรรมด้านการศึกษาและการเรียนรู้'),
('สังคม', 'กิจกรรมด้านสังคมและชุมชน');

-- ข้อมูลกิจกรรมเดิม
INSERT INTO activities (category_id, title, description, date) VALUES
(1, 'การแข่งขันฟุตบอล', 'การแข่งขันฟุตบอลประจำปี', '2024-01-15'),
(2, 'นิทรรศการศิลปะ', 'แสดงผลงานศิลปะของนักเรียน', '2024-01-20'),
(3, 'โครงการวิทยาศาสตร์', 'การนำเสนอโครงการวิทยาศาสตร์', '2024-01-25'),
(4, 'คอนเสิร์ตดนตรี', 'การแสดงดนตรีประจำเทอม', '2024-01-30'),
(5, 'สัมมนาการเรียนรู้', 'สัมมนาเทคนิคการเรียนรู้ใหม่', '2024-02-05'),
(6, 'งานอาสาสมัคร', 'กิจกรรมช่วยเหลือชุมชน', '2024-02-10');

-- ข้อมูลรูปภาพกิจกรรมเดิม
INSERT INTO activity_images (activity_id, image_path, image_order) VALUES
-- กีฬา
(1, 'uploads/sport1.jpg', 1),
(1, 'uploads/sport2.jpg', 2),
(1, 'uploads/sport3.jpg', 3),
(1, 'uploads/sport4.jpg', 4),
-- ศิลปะ
(2, 'uploads/art1.jpg', 1),
(2, 'uploads/art2.jpg', 2),
(2, 'uploads/art3.jpg', 3),
(2, 'uploads/art4.jpg', 4),
-- วิทยาศาสตร์
(3, 'uploads/science1.jpg', 1),
(3, 'uploads/science2.jpg', 2),
(3, 'uploads/science3.jpg', 3),
(3, 'uploads/science4.jpg', 4),
-- ดนตรี
(4, 'uploads/music1.jpg', 1),
(4, 'uploads/music2.jpg', 2),
(4, 'uploads/music3.jpg', 3),
(4, 'uploads/music4.jpg', 4),
-- การเรียนรู้
(5, 'uploads/learn1.jpg', 1),
(5, 'uploads/learn2.jpg', 2),
(5, 'uploads/learn3.jpg', 3),
(5, 'uploads/learn4.jpg', 4),
-- สังคม
(6, 'uploads/social1.jpg', 1),
(6, 'uploads/social2.jpg', 2),
(6, 'uploads/social3.jpg', 3),
(6, 'uploads/social4.jpg', 4);

-- ข้อมูลผู้ดูแลระบบเริ่มต้น
INSERT INTO admin_users (username, email, password, full_name, role, status) VALUES
('admin', 'admin@gallery.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrator', 'admin', 'active'),
('editor', 'editor@gallery.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Content Editor', 'editor', 'active');
-- Default password for both accounts is 'password'

-- หมวดหมู่ Albums
INSERT INTO album_categories (name, description, icon, color) VALUES
('กีฬา', 'Albums ด้านกีฬาและการออกกำลังกาย', 'fas fa-futbol', 'bg-blue-600'),
('ศิลปะ', 'Albums ด้านศิลปะและความสร้างสรรค์', 'fas fa-palette', 'bg-purple-600'),
('วิทยาศาสตร์', 'Albums ด้านวิทยาศาสตร์และเทคโนโลยี', 'fas fa-flask', 'bg-green-600'),
('ดนตรี', 'Albums ด้านดนตรีและการแสดง', 'fas fa-music', 'bg-pink-600'),
('การเรียนรู้', 'Albums ด้านการศึกษาและการเรียนรู้', 'fas fa-graduation-cap', 'bg-indigo-600'),
('สังคม', 'Albums ด้านกิจกรรมสังคมและชุมชน', 'fas fa-users', 'bg-yellow-600');

-- Albums ตัวอย่าง
INSERT INTO albums (category_id, title, description, status, date_created, created_by, view_count) VALUES
(1, 'การแข่งขันฟุตบอลประจำปี 2024', 'การแข่งขันฟุตบอลประจำปีระหว่างคณะต่างๆ มีทีมเข้าร่วมกว่า 20 ทีม', 'active', '2024-01-15', 1, 156),
(2, 'นิทรรศการศิลปะนักเรียน', 'นิทรรศการแสดงผลงานศิลปะของนักเรียนชั้นปีที่ 4 ประจำภาคเรียนที่ 2', 'active', '2024-01-20', 1, 89),
(3, 'โครงการวิทยาศาสตร์สร้างสรรค์', 'การนำเสนอโครงการวิทยาศาสตร์ที่เน้นการสร้างสรรค์และนวัตกรรม', 'active', '2024-01-25', 2, 134),
(4, 'คอนเสิร์ตดนตรีประจำเทอม', 'การแสดงดนตรีจากนักเรียนแผนกดนตรี รวมทั้งวง orchestra และ band', 'active', '2024-01-30', 1, 201),
(5, 'สัมมนาเทคนิคการเรียนรู้', 'สัมมนาเพื่อแลกเปลี่ยนเทคนิคการเรียนรู้ใหม่ๆ สำหรับศตวรรษที่ 21', 'active', '2024-02-05', 2, 67),
(6, 'กิจกรรมอาสาสมัครชุมชน', 'กิจกรรมช่วยเหลือชุมชนท้องถิ่น ทำความสะอาดและปลูกต้นไม้', 'active', '2024-02-10', 1, 98);

-- รูปภาพตัวอย่างสำหรับ Albums (จะต้องมีไฟล์จริงในโฟลเดอร์ uploads/albums/)
INSERT INTO album_images (album_id, image_path, image_title, image_description, image_order, is_cover) VALUES
-- Album 1: ฟุตบอล
(1, 'uploads/albums/album_1/img_20240115_001.webp', 'การเตะลูกฟุตบอล', 'นักเรียนกำลังเตะลูกฟุตบอลในระหว่างการแข่งขัน', 1, 1),
(1, 'uploads/albums/album_1/img_20240115_002.webp', 'การเชียร์จากผู้ชม', 'ผู้ชมเชียร์อย่างสนุกสนานริมสนาม', 2, 0),
(1, 'uploads/albums/album_1/img_20240115_003.webp', 'การมอบรางวัล', 'พิธีมอบรางวัลให้กับทีมชนะเลิศ', 3, 0),

-- Album 2: ศิลปะ
(2, 'uploads/albums/album_2/img_20240120_001.webp', 'ผลงานจิตรกรรม', 'ผลงานจิตรกรรมสีน้ำมันของนักเรียน', 1, 1),
(2, 'uploads/albums/album_2/img_20240120_002.webp', 'ประติมากรรม', 'ผลงานประติมากรรมดินเผา', 2, 0),
(2, 'uploads/albums/album_2/img_20240120_003.webp', 'การ์ตูนศิลปะ', 'ผลงานการ์ตูนและอนิเมชั่น', 3, 0);

-- สร้าง indexes สำหรับประสิทธิภาพ
CREATE INDEX idx_albums_category ON albums(category_id);
CREATE INDEX idx_albums_status ON albums(status);
CREATE INDEX idx_albums_created_at ON albums(created_at);
CREATE INDEX idx_album_images_album ON album_images(album_id);
CREATE INDEX idx_album_images_order ON album_images(image_order);
CREATE INDEX idx_login_logs_user ON login_logs(user_id);
CREATE INDEX idx_login_logs_time ON login_logs(login_time);