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

    // ดึงสถิติหมวดหมู่พร้อมสถิติ
    public function getCategoryStatistics() {
        $query = "SELECT 
                    c.id,
                    c.name,
                    c.description,
                    c.icon,
                    c.color,
                    COUNT(a.id) as album_count,
                    COALESCE(SUM(a.view_count), 0) as total_views,
                    COALESCE(album_images.total_images, 0) as total_images,
                    MIN(a.created_at) as earliest_album,
                    MAX(a.created_at) as latest_album
                  FROM album_categories c
                  LEFT JOIN albums a ON c.id = a.category_id AND a.status = 'active'
                  LEFT JOIN (
                      SELECT 
                          a.category_id,
                          COUNT(ai.id) as total_images
                      FROM albums a
                      LEFT JOIN album_images ai ON a.id = ai.album_id
                      WHERE a.status = 'active'
                      GROUP BY a.category_id
                  ) album_images ON c.id = album_images.category_id
                  GROUP BY c.id, c.name, c.description, c.icon, c.color
                  ORDER BY album_count DESC, c.name";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt;
    }

    // ดึงอัลบั้มด้วยการกรองหลายหมวดหมู่
    public function getAlbumsWithMultiCategoryFilter($filters = []) {
        $search = isset($filters['search']) ? trim($filters['search']) : '';
        $categories = isset($filters['categories']) && is_array($filters['categories']) ? $filters['categories'] : [];
        $status = isset($filters['status']) ? $filters['status'] : '';
        $sort = isset($filters['sort']) ? $filters['sort'] : 'created_at';
        $order = isset($filters['order']) && strtoupper($filters['order']) === 'ASC' ? 'ASC' : 'DESC';
        $page = isset($filters['page']) ? max(1, (int)$filters['page']) : 1;
        $per_page = isset($filters['per_page']) ? max(1, min(100, (int)$filters['per_page'])) : 10;
        $offset = ($page - 1) * $per_page;

        // สร้าง WHERE clause
        $where_conditions = ['1=1'];
        $params = [];

        if (!empty($search)) {
            $where_conditions[] = "(a.title LIKE ? OR a.description LIKE ?)";
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
        }

        if (!empty($categories)) {
            $category_placeholders = implode(',', array_fill(0, count($categories), '?'));
            $where_conditions[] = "a.category_id IN ({$category_placeholders})";
            $params = array_merge($params, $categories);
        }

        if (!empty($status)) {
            $where_conditions[] = "a.status = ?";
            $params[] = $status;
        }

        $where_clause = implode(' AND ', $where_conditions);

        // กำหนดการ sort
        $valid_sorts = ['title', 'created_at', 'view_count', 'category_name', 'status'];
        $sort_field = in_array($sort, $valid_sorts) ? $sort : 'created_at';
        
        if ($sort_field === 'category_name') {
            $sort_field = 'c.name';
        } elseif ($sort_field === 'title') {
            $sort_field = 'a.title';
        } elseif ($sort_field === 'created_at') {
            $sort_field = 'a.created_at';
        } elseif ($sort_field === 'view_count') {
            $sort_field = 'a.view_count';
        } elseif ($sort_field === 'status') {
            $sort_field = 'a.status';
        }

        // นับจำนวนรวม
        $count_query = "SELECT COUNT(*) as total 
                        FROM albums a 
                        JOIN album_categories c ON a.category_id = c.id 
                        WHERE {$where_clause}";
        $count_stmt = $this->conn->prepare($count_query);
        $count_stmt->execute($params);
        $total_records = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];

        // ดึงข้อมูลอัลบั้ม
        $albums_query = "SELECT a.*, c.name as category_name, c.color as category_color, c.icon as category_icon,
                                (SELECT COUNT(*) FROM album_images WHERE album_id = a.id) as image_count,
                                (SELECT image_path FROM album_images WHERE album_id = a.id AND is_cover = 1 LIMIT 1) as cover_image_path,
                                u.full_name as created_by_name
                         FROM albums a 
                         JOIN album_categories c ON a.category_id = c.id 
                         LEFT JOIN admin_users u ON a.created_by = u.id
                         WHERE {$where_clause}
                         ORDER BY {$sort_field} {$order}
                         LIMIT {$per_page} OFFSET {$offset}";

        $albums_stmt = $this->conn->prepare($albums_query);
        $albums_stmt->execute($params);
        $albums = $albums_stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'albums' => $albums,
            'total_records' => $total_records,
            'total_pages' => ceil($total_records / $per_page),
            'current_page' => $page,
            'per_page' => $per_page
        ];
    }

    // ค้นหาหมวดหมู่
    public function searchCategories($query = '') {
        $sql = "SELECT c.*, 
                       COUNT(a.id) as album_count,
                       COALESCE(SUM(a.view_count), 0) as total_views
                FROM album_categories c
                LEFT JOIN albums a ON c.id = a.category_id AND a.status = 'active'
                WHERE 1=1";
        
        $params = [];
        
        if (!empty($query)) {
            $sql .= " AND (c.name LIKE ? OR c.description LIKE ?)";
            $params[] = '%' . $query . '%';
            $params[] = '%' . $query . '%';
        }
        
        $sql .= " GROUP BY c.id, c.name, c.description, c.icon, c.color
                  ORDER BY c.name";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    // ดึงอัลบั้มยอดนิยมในหมวดหมู่
    public function getPopularAlbumsByCategory($category_id, $limit = 5) {
        $query = "SELECT a.*, c.name as category_name, c.color,
                         (SELECT COUNT(*) FROM album_images WHERE album_id = a.id) as image_count
                  FROM albums a
                  JOIN album_categories c ON a.category_id = c.id
                  WHERE a.category_id = ? AND a.status = 'active'
                  ORDER BY a.view_count DESC, a.created_at DESC
                  LIMIT ?";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $category_id, PDO::PARAM_INT);
        $stmt->bindParam(2, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt;
    }

    // ค้นหา Albums ด้วยชื่อ หมวดหมู่ และ/หรือช่วงวันที่
    public function searchAlbumsWithCategory($query = '', $category_id = 0, $start_date = '', $end_date = '') {
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
        
        // เพิ่มเงื่อนไขค้นหาตามหมวดหมู่
        if ($category_id > 0) {
            $sql .= " AND a.category_id = ?";
            $params[] = $category_id;
            $param_count++;
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