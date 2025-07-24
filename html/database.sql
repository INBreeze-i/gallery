CREATE DATABASE php_project;
USE php_project;

-- ตารางสำหรับหมวดหมู่
CREATE TABLE categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ตารางสำหรับกิจกรรม
CREATE TABLE activities (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_id INT,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id)
);

-- ตารางสำหรับรูปภาพ
CREATE TABLE activity_images (
    id INT AUTO_INCREMENT PRIMARY KEY,
    activity_id INT,
    image_path VARCHAR(255) NOT NULL,
    image_order INT DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (activity_id) REFERENCES activities(id)
);

-- เพิ่มข้อมูลตัวอย่าง
INSERT INTO categories (name, description) VALUES
('กีฬา', 'กิจกรรมด้านกีฬาและออกกำลังกาย'),
('ศิลปะ', 'กิจกรรมด้านศิลปะและความคิดสร้างสรรค์'),
('วิทยาศาสตร์', 'กิจกรรมด้านวิทยาศาสตร์และเทคโนโลยี'),
('ดนตรี', 'กิจกรรมด้านดนตรีและการแสดง'),
('การเรียนรู้', 'กิจกรรมด้านการศึกษาและการเรียนรู้'),
('สังคม', 'กิจกรรมด้านสังคมและชุมชน');

INSERT INTO activities (category_id, title, description, date) VALUES
(1, 'การแข่งขันฟุตบอล', 'การแข่งขันฟุตบอลประจำปี', '2024-01-15'),
(2, 'นิทรรศการศิลปะ', 'แสดงผลงานศิลปะของนักเรียน', '2024-01-20'),
(3, 'โครงการวิทยาศาสตร์', 'การนำเสนอโครงการวิทยาศาสตร์', '2024-01-25'),
(4, 'คอนเสิร์ตดนตรี', 'การแสดงดนตรีประจำเทอม', '2024-01-30'),
(5, 'สัมมนาการเรียนรู้', 'สัมมนาเทคนิคการเรียนรู้ใหม่', '2024-02-05'),
(6, 'งานอาสาสมัคร', 'กิจกรรมช่วยเหลือชุมชน', '2024-02-10');

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