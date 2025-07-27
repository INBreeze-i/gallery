-- Migration for Enhanced Category Filter System
-- This adds new tables and indexes to support advanced filtering features

-- Table for storing user filter preferences
CREATE TABLE IF NOT EXISTS user_filter_preferences (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    filter_state JSON NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_user_filter (user_id),
    FOREIGN KEY (user_id) REFERENCES admin_users(id) ON DELETE CASCADE
);

-- Additional indexes for better performance
CREATE INDEX IF NOT EXISTS idx_albums_category_status ON albums(category_id, status);
CREATE INDEX IF NOT EXISTS idx_albums_title_search ON albums(title);
CREATE INDEX IF NOT EXISTS idx_albums_description_search ON albums(description);
CREATE INDEX IF NOT EXISTS idx_album_categories_name ON album_categories(name);

-- Index for faster statistics queries
CREATE INDEX IF NOT EXISTS idx_albums_view_count ON albums(view_count);
CREATE INDEX IF NOT EXISTS idx_album_images_album_count ON album_images(album_id);

-- Comment: These indexes will improve performance for:
-- 1. Multi-category filtering
-- 2. Search operations
-- 3. Statistics calculations
-- 4. Sorting operations