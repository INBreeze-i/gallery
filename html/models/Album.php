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

    // ค้นหา Albums ตามชื่อและวันที่
    public function searchAlbums($search_name = '', $search_date = '') {
        $query = "SELECT 
                    a.id as album_id,
                    a.title as album_title,
                    a.description as album_description,
                    a.cover_image,
                    a.date_created,
                    a.view_count,
                    c.id as category_id,
                    c.name as category_name,
                    c.description as category_description,
                    c.icon,
                    c.color,
                    (SELECT COUNT(*) FROM album_images WHERE album_id = a.id) as image_count
                  FROM albums a
                  JOIN album_categories c ON a.category_id = c.id
                  WHERE a.status = 'active'";
        
        $params = [];
        
        // เพิ่มเงื่อนไขการค้นหาตามชื่อ
        if (!empty($search_name)) {
            $query .= " AND (a.title LIKE ? OR a.description LIKE ?)";
            $search_term = "%{$search_name}%";
            $params[] = $search_term;
            $params[] = $search_term;
        }
        
        // เพิ่มเงื่อนไขการค้นหาตามวันที่
        if (!empty($search_date)) {
            $query .= " AND a.date_created = ?";
            $params[] = $search_date;
        }
        
        $query .= " ORDER BY a.date_created DESC, a.id DESC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute($params);
        return $stmt;
    }

    // นับจำนวนผลการค้นหา
    public function countSearchResults($search_name = '', $search_date = '') {
        $query = "SELECT COUNT(*) as total
                  FROM albums a
                  JOIN album_categories c ON a.category_id = c.id
                  WHERE a.status = 'active'";
        
        $params = [];
        
        // เพิ่มเงื่อนไขการค้นหาตามชื่อ
        if (!empty($search_name)) {
            $query .= " AND (a.title LIKE ? OR a.description LIKE ?)";
            $search_term = "%{$search_name}%";
            $params[] = $search_term;
            $params[] = $search_term;
        }
        
        // เพิ่มเงื่อนไขการค้นหาตามวันที่
        if (!empty($search_date)) {
            $query .= " AND a.date_created = ?";
            $params[] = $search_date;
        }
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute($params);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['total'];
    }
}
?>