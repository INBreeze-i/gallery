<?php
class Album {
    private $conn;
    private $table_name = "albums";

    public function __construct($db) {
        $this->conn = $db;
    }

    // ดึง Albums ล่าสุดของแต่ละประเภท (จำกัด 6 ประเภท)
    public function getLatestAlbumsByCategory() {
        $query = "SELECT 
                    c.id as category_id,
                    c.name as category_name,
                    c.description as category_description,
                    c.icon,
                    c.color,
                    a.id as album_id,
                    a.title as album_title,
                    a.description as album_description,
                    a.cover_image,
                    a.date_created,
                    a.view_count,
                    (SELECT COUNT(*) FROM album_images WHERE album_id = a.id) as image_count
                  FROM album_categories c 
                  LEFT JOIN albums a ON c.id = a.category_id
                  WHERE a.status = 'active' AND a.id IN (
                      SELECT MAX(id) 
                      FROM albums 
                      WHERE status = 'active'
                      GROUP BY category_id
                  )
                  ORDER BY c.id
                  LIMIT 6";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt;
    }

    // ดึงรูปภาพในแต่ละ Album (4 รูปแรก)
    public function getAlbumImages($album_id, $limit = 4) {
        $query = "SELECT 
                    image_path,
                    image_title,
                    image_description,
                    is_cover
                  FROM album_images 
                  WHERE album_id = ? 
                  ORDER BY image_order 
                  LIMIT ?";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $album_id, PDO::PARAM_INT);
        $stmt->bindParam(2, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt;
    }

    // ดึงข้อมูล Album ทั้งหมดของประเภทเดียว
    public function getAlbumsByCategory($category_id) {
        $query = "SELECT 
                    a.*,
                    c.name as category_name,
                    c.color,
                    (SELECT COUNT(*) FROM album_images WHERE album_id = a.id) as image_count
                  FROM albums a
                  JOIN album_categories c ON a.category_id = c.id
                  WHERE a.category_id = ? AND a.status = 'active'
                  ORDER BY a.created_at DESC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $category_id);
        $stmt->execute();
        return $stmt;
    }

    // เพิ่มจำนวนการดู Album
    public function incrementViewCount($album_id) {
        $query = "UPDATE albums SET view_count = view_count + 1 WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $album_id);
        return $stmt->execute();
    }

    // ดึงข้อมูล Album เดียว
    public function getAlbumById($album_id) {
        $query = "SELECT 
                    a.*,
                    c.name as category_name,
                    c.color,
                    c.icon
                  FROM albums a
                  JOIN album_categories c ON a.category_id = c.id
                  WHERE a.id = ? AND a.status = 'active'";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $album_id);
        $stmt->execute();
        return $stmt;
    }
    
    // ค้นหา Albums ด้วยชื่อและ/หรือช่วงวันที่
    public function searchAlbums($query = '', $start_date = '', $end_date = '') {
        $sql = "SELECT 
                    a.*,
                    c.name as category_name,
                    c.color,
                    c.icon,
                    (SELECT COUNT(*) FROM album_images WHERE album_id = a.id) as image_count
                FROM albums a
                JOIN album_categories c ON a.category_id = c.id
                WHERE a.status = 'active'";
        
        $params = [];
        $param_count = 0;
        
        // เพิ่มเงื่อนไขค้นหาตามชื่อ
        if (!empty($query)) {
            $sql .= " AND (a.title LIKE ? OR a.description LIKE ?)";
            $params[] = '%' . $query . '%';
            $params[] = '%' . $query . '%';
            $param_count += 2;
        }
        
        // เพิ่มเงื่อนไขค้นหาตามวันที่
        if (!empty($start_date)) {
            $sql .= " AND a.date_created >= ?";
            $params[] = $start_date;
            $param_count++;
        }
        
        if (!empty($end_date)) {
            $sql .= " AND a.date_created <= ?";
            $params[] = $end_date;
            $param_count++;
        }
        
        $sql .= " ORDER BY a.date_created DESC";
        
        $stmt = $this->conn->prepare($sql);
        
        // bind parameters
        for ($i = 0; $i < $param_count; $i++) {
            $stmt->bindParam($i + 1, $params[$i]);
        }
        
        $stmt->execute();
        return $stmt;
    }
}
?>