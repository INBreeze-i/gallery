<?php
class Activity {
    private $conn;
    private $table_name = "activities";

    public function __construct($db) {
        $this->conn = $db;
    }

    public function getLatestActivitiesByCategory() {
        $query = "SELECT 
                    c.id as category_id,
                    c.name as category_name,
                    a.id as activity_id,
                    a.title as activity_title,
                    a.description,
                    a.date
                  FROM categories c 
                  LEFT JOIN activities a ON c.id = a.category_id
                  WHERE a.id IN (
                      SELECT MAX(id) 
                      FROM activities 
                      GROUP BY category_id
                  )
                  ORDER BY c.id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt;
    }

    public function getActivityImages($activity_id) {
        $query = "SELECT image_path 
                  FROM activity_images 
                  WHERE activity_id = ? 
                  ORDER BY image_order 
                  LIMIT 4";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $activity_id);
        $stmt->execute();
        return $stmt;
    }
}
?>