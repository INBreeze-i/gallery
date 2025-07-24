<?php
class ImageHandler {
    private $upload_dir;
    private $max_width;
    private $max_height;
    private $quality;
    private $has_gd;
    
    public function __construct($upload_dir = '../uploads/albums/', $max_width = 800, $max_height = 600, $quality = 80) {
        $this->upload_dir = $upload_dir;
        $this->max_width = $max_width;
        $this->max_height = $max_height;
        $this->quality = $quality;
        
        // ตรวจสอบว่ามี GD extension หรือไม่
        $this->has_gd = extension_loaded('gd');
        
        // สร้างโฟลเดอร์ถ้ายังไม่มี
        if (!file_exists($this->upload_dir)) {
            mkdir($this->upload_dir, 0755, true);
        }
    }
    
    // สร้างชื่อไฟล์ที่ไม่ซ้ำ
    public function generateUniqueFilename($original_name) {
        $extension = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
        $timestamp = date('YmdHis');
        $random = substr(md5(uniqid(rand(), true)), 0, 8);
        return "img_{$timestamp}_{$random}.{$extension}";
    }
    
    // ตรวจสอบไฟล์รูปภาพ
    public function validateImage($file) {
        $errors = [];
        
        // ตรวจสอบขนาดไฟล์ (5MB)
        if ($file['size'] > 5 * 1024 * 1024) {
            $errors[] = "ไฟล์รูปภาพต้องมีขนาดไม่เกิน 5MB (ขนาดปัจจุบัน: " . $this->formatFileSize($file['size']) . ")";
        }
        
        // ตรวจสอบประเภทไฟล์
        $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
        if (!in_array(strtolower($file['type']), $allowed_types)) {
            $errors[] = "รองรับเฉพาะไฟล์ JPEG, PNG, GIF, WebP เท่านั้น (ไฟล์ปัจจุบัน: {$file['type']})";
        }
        
        // ตรวจสอบว่าเป็นรูปภาพจริง (ใช้ getimagesize แทน GD)
        if ($file['tmp_name'] && function_exists('getimagesize')) {
            $image_info = @getimagesize($file['tmp_name']);
            if (!$image_info) {
                $errors[] = "ไฟล์ที่อัปโหลดไม่ใช่รูปภาพที่ถูกต้อง";
            }
        }
        
        // ตรวจสอบนามสกุลไฟล์
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, $allowed_extensions)) {
            $errors[] = "นามสกุลไฟล์ไม่ถูกต้อง รองรับเฉพาะ: " . implode(', ', $allowed_extensions);
        }
        
        return $errors;
    }
    
    // ปรับขนาดรูปภาพ (ใช้ GD ถ้ามี)
    public function resizeImage($source_path, $destination_path, $max_width = null, $max_height = null) {
        $max_width = $max_width ?: $this->max_width;
        $max_height = $max_height ?: $this->max_height;
        
        // ถ้าไม่มี GD ให้คัดลอกไฟล์เดิม
        if (!$this->has_gd) {
            return copy($source_path, $destination_path);
        }
        
        try {
            $image_info = @getimagesize($source_path);
            if (!$image_info) {
                // ถ้าไม่สามารถอ่านได้ ให้คัดลอกไฟล์เดิม
                return copy($source_path, $destination_path);
            }
            
            $original_width = $image_info[0];
            $original_height = $image_info[1];
            $mime_type = $image_info['mime'];
            
            // คำนวณขนาดใหม่โดยรักษาอัตราส่วน
            $ratio = min($max_width / $original_width, $max_height / $original_height);
            
            // ถ้ารูปเล็กกว่าขนาดที่กำหนด ไม่ต้องปรับขนาด
            if ($ratio >= 1) {
                return copy($source_path, $destination_path);
            }
            
            $new_width = round($original_width * $ratio);
            $new_height = round($original_height * $ratio);
            
            // สร้าง image resource ตามประเภทไฟล์
            $source_image = null;
            switch ($mime_type) {
                case 'image/jpeg':
                    if (function_exists('imagecreatefromjpeg')) {
                        $source_image = @imagecreatefromjpeg($source_path);
                    }
                    break;
                case 'image/png':
                    if (function_exists('imagecreatefrompng')) {
                        $source_image = @imagecreatefrompng($source_path);
                    }
                    break;
                case 'image/gif':
                    if (function_exists('imagecreatefromgif')) {
                        $source_image = @imagecreatefromgif($source_path);
                    }
                    break;
                case 'image/webp':
                    if (function_exists('imagecreatefromwebp')) {
                        $source_image = @imagecreatefromwebp($source_path);
                    }
                    break;
            }
            
            // ถ้าไม่สามารถสร้าง image resource ได้ ให้คัดลอกไฟล์เดิม
            if (!$source_image) {
                return copy($source_path, $destination_path);
            }
            
            // สร้างภาพใหม่
            $new_image = @imagecreatetruecolor($new_width, $new_height);
            if (!$new_image) {
                imagedestroy($source_image);
                return copy($source_path, $destination_path);
            }
            
            // รักษาความโปร่งใสสำหรับ PNG และ GIF
            if ($mime_type == 'image/png' || $mime_type == 'image/gif') {
                imagealphablending($new_image, false);
                imagesavealpha($new_image, true);
            }
            
            // ปรับขนาด
            $resize_result = @imagecopyresampled($new_image, $source_image, 0, 0, 0, 0, $new_width, $new_height, $original_width, $original_height);
            
            if (!$resize_result) {
                imagedestroy($source_image);
                imagedestroy($new_image);
                return copy($source_path, $destination_path);
            }
            
            // บันทึกไฟล์ใหม่
            $result = false;
            switch ($mime_type) {
                case 'image/jpeg':
                    if (function_exists('imagejpeg')) {
                        $result = @imagejpeg($new_image, $destination_path, $this->quality);
                    }
                    break;
                case 'image/png':
                    if (function_exists('imagepng')) {
                        $compression = 9 - round($this->quality / 10);
                        $result = @imagepng($new_image, $destination_path, $compression);
                    }
                    break;
                case 'image/gif':
                    if (function_exists('imagegif')) {
                        $result = @imagegif($new_image, $destination_path);
                    }
                    break;
                case 'image/webp':
                    if (function_exists('imagewebp')) {
                        $result = @imagewebp($new_image, $destination_path, $this->quality);
                    }
                    break;
            }
            
            // ล้าง memory
            imagedestroy($source_image);
            imagedestroy($new_image);
            
            // ถ้าบันทึกไม่สำเร็จ ให้คัดลอกไฟล์เดิม
            if (!$result) {
                return copy($source_path, $destination_path);
            }
            
            return true;
            
        } catch (Exception $e) {
            error_log("Image resize error: " . $e->getMessage());
            return copy($source_path, $destination_path);
        }
    }
    
    // สร้าง thumbnail
    public function createThumbnail($source_path, $thumbnail_path, $size = 200) {
        return $this->resizeImage($source_path, $thumbnail_path, $size, $size);
    }
    
    // อัปโหลดและประมวลผลรูปภาพ
    public function uploadImage($file, $album_id = null) {
        try {
            $errors = $this->validateImage($file);
            if (!empty($errors)) {
                return ['success' => false, 'errors' => $errors];
            }
            
            // สร้างชื่อไฟล์ใหม่
            $new_filename = $this->generateUniqueFilename($file['name']);
            
            // สร้างโฟลเดอร์ย่อยตาม album_id
            $album_dir = $this->upload_dir;
            if ($album_id) {
                $album_dir .= "album_{$album_id}/";
                if (!file_exists($album_dir)) {
                    mkdir($album_dir, 0755, true);
                }
            }
            
            $full_path = $album_dir . $new_filename;
            $thumbnail_path = $album_dir . "thumb_" . $new_filename;
            
            // ตรวจสอบว่าสามารถเขียนไฟล์ได้หรือไม่
            if (!is_writable(dirname($full_path))) {
                return ['success' => false, 'errors' => ['ไม่สามารถเขียนไฟล์ในโฟลเดอร์ได้ กรุณาตรวจสอบสิทธิ์']];
            }
            
            // ปรับขนาดและบันทึกรูปภาพ
            if ($this->resizeImage($file['tmp_name'], $full_path)) {
                // สร้าง thumbnail
                $this->createThumbnail($full_path, $thumbnail_path);
                
                // ตรวจสอบว่าไฟล์ถูกสร้างจริง
                if (file_exists($full_path)) {
                    return [
                        'success' => true,
                        'filename' => $new_filename,
                        'full_path' => $full_path,
                        'thumbnail_path' => $thumbnail_path,
                        'relative_path' => str_replace('../', '', $full_path),
                        'thumbnail_relative_path' => str_replace('../', '', $thumbnail_path),
                        'file_size' => filesize($full_path),
                        'original_name' => $file['name']
                    ];
                } else {
                    return ['success' => false, 'errors' => ['ไม่สามารถบันทึกไฟล์ได้']];
                }
            }
            
            return ['success' => false, 'errors' => ['ไม่สามารถประมวลผลรูปภาพได้']];
            
        } catch (Exception $e) {
            error_log("Upload error: " . $e->getMessage());
            return ['success' => false, 'errors' => ['เกิดข้อผิดพลาดในการอัปโหลด: ' . $e->getMessage()]];
        }
    }
    
    // ลบไฟล์รูปภาพ
    public function deleteImage($file_path) {
        try {
            if (file_exists($file_path)) {
                unlink($file_path);
            }
            
            // ลบ thumbnail ด้วย
            $thumbnail_path = dirname($file_path) . '/thumb_' . basename($file_path);
            if (file_exists($thumbnail_path)) {
                unlink($thumbnail_path);
            }
            
            return true;
        } catch (Exception $e) {
            error_log("Delete image error: " . $e->getMessage());
            return false;
        }
    }
    
    // ฟอร์แมตขนาดไฟล์
    private function formatFileSize($bytes) {
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        } else {
            return $bytes . ' bytes';
        }
    }
    
    // ตรวจสอบระบบ
    public function getSystemInfo() {
        return [
            'gd_available' => $this->has_gd,
            'gd_version' => $this->has_gd ? gd_info()['GD Version'] ?? 'Unknown' : 'Not available',
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size' => ini_get('post_max_size'),
            'max_file_uploads' => ini_get('max_file_uploads'),
            'upload_dir' => $this->upload_dir,
            'upload_dir_writable' => is_writable($this->upload_dir),
            'supported_formats' => $this->getSupportedFormats()
        ];
    }
    
    // ได้รูปแบบที่รองรับ
    private function getSupportedFormats() {
        $formats = [];
        
        if ($this->has_gd) {
            if (function_exists('imagecreatefromjpeg')) $formats[] = 'JPEG';
            if (function_exists('imagecreatefrompng')) $formats[] = 'PNG';
            if (function_exists('imagecreatefromgif')) $formats[] = 'GIF';
            if (function_exists('imagecreatefromwebp')) $formats[] = 'WebP';
        } else {
            $formats[] = 'Basic file copy (no resizing)';
        }
        
        return $formats;
    }
}
?>