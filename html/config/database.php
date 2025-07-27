<?php
class Database {
    private $host = "localhost";
    private $db_name = "test_gallery";
    private $username = "root";
    private $password = "";
    public $conn;

    public function getConnection() {
        $this->conn = null;
        try {
            // Try SQLite for testing
            $this->conn = new PDO("sqlite:/tmp/gallery.db");
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // Create tables if they don't exist
            $this->createTablesIfNotExist();
            
        } catch(PDOException $exception) {
            echo "Connection error: " . $exception->getMessage();
        }
        return $this->conn;
    }
    
    private function createTablesIfNotExist() {
        // Create album_categories table
        $sql_categories = "CREATE TABLE IF NOT EXISTS album_categories (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name VARCHAR(100) NOT NULL,
            description TEXT,
            icon VARCHAR(50) DEFAULT 'fas fa-folder',
            color VARCHAR(20) DEFAULT 'bg-blue-600',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )";
        $this->conn->exec($sql_categories);
        
        // Create albums table
        $sql_albums = "CREATE TABLE IF NOT EXISTS albums (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            category_id INTEGER,
            title VARCHAR(200) NOT NULL,
            description TEXT,
            cover_image VARCHAR(255),
            status VARCHAR(20) DEFAULT 'active',
            date_created DATE,
            created_by INTEGER,
            view_count INTEGER DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (category_id) REFERENCES album_categories(id)
        )";
        $this->conn->exec($sql_albums);
        
        // Create album_images table
        $sql_images = "CREATE TABLE IF NOT EXISTS album_images (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            album_id INTEGER NOT NULL,
            image_path VARCHAR(255) NOT NULL,
            image_title VARCHAR(200) DEFAULT '',
            image_description TEXT DEFAULT '',
            image_order INTEGER DEFAULT 1,
            is_cover BOOLEAN DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (album_id) REFERENCES albums(id)
        )";
        $this->conn->exec($sql_images);
        
        // Insert sample data if empty
        $check = $this->conn->query("SELECT COUNT(*) FROM album_categories")->fetchColumn();
        if ($check == 0) {
            $this->insertSampleData();
        }
    }
    
    private function insertSampleData() {
        // Insert categories
        $categories = [
            ['กีฬา', 'Albums ด้านกีฬาและการออกกำลังกาย', 'fas fa-futbol', 'bg-blue-600'],
            ['ศิลปะ', 'Albums ด้านศิลปะและความสร้างสรรค์', 'fas fa-palette', 'bg-purple-600'],
            ['วิทยาศาสตร์', 'Albums ด้านวิทยาศาสตร์และเทคโนโลยี', 'fas fa-flask', 'bg-green-600'],
            ['ดนตรี', 'Albums ด้านดนตรีและการแสดง', 'fas fa-music', 'bg-pink-600'],
            ['การเรียนรู้', 'Albums ด้านการศึกษาและการเรียนรู้', 'fas fa-graduation-cap', 'bg-indigo-600'],
            ['สังคม', 'Albums ด้านกิจกรรมสังคมและชุมชน', 'fas fa-users', 'bg-yellow-600']
        ];
        
        $stmt = $this->conn->prepare("INSERT INTO album_categories (name, description, icon, color) VALUES (?, ?, ?, ?)");
        foreach ($categories as $cat) {
            $stmt->execute($cat);
        }
        
        // Insert sample albums
        $albums = [
            [1, 'การแข่งขันฟุตบอลประจำปี 2024', 'การแข่งขันฟุตบอลประจำปีระหว่างคณะต่างๆ มีทีมเข้าร่วมกว่า 20 ทีม', 'active', '2024-01-15', 156],
            [2, 'นิทรรศการศิลปะร่วมสมัย', 'งานแสดงศิลปะจากนักศึกษาและศิลปินรุ่นใหม่', 'active', '2024-02-10', 89],
            [3, 'วิทยาศาสตร์มหัศจรรย์', 'การทดลองและการสาธิตทางวิทยาศาสตร์ที่น่าสนใจ', 'active', '2024-03-05', 124],
            [4, 'คอนเสิร์ตดนตรีคลาสสิก', 'การแสดงดนตรีคลาสสิกจากนักเรียนคณะดนตรี', 'active', '2024-04-20', 67],
            [5, 'สัมมนาวิชาการประจำปี', 'การนำเสนอผลงานวิจัยและนวัตกรรมการศึกษา', 'active', '2024-05-15', 201],
            [6, 'งานบุญประจำชุมชน', 'กิจกรรมการทำบุญและการช่วยเหลือชุมชน', 'active', '2024-06-10', 78]
        ];
        
        $stmt = $this->conn->prepare("INSERT INTO albums (category_id, title, description, status, date_created, view_count) VALUES (?, ?, ?, ?, ?, ?)");
        foreach ($albums as $album) {
            $stmt->execute($album);
        }
    }
}
?>