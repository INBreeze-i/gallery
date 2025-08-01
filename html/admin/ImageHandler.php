<?php
class ImageHandler {
    private $upload_dir;
    private $max_width;
    private $max_height;
    private $quality;
    private $webp_quality;
    private $has_gd;
    private $has_webp;
    
    public function __construct($upload_dir = '../uploads/albums/', $max_width = 800, $max_height = 600, $quality = 80, $webp_quality = 85) {
        $this->upload_dir = $upload_dir;
        $this->max_width = $max_width;
        $this->max_height = $max_height;
        $this->quality = $quality;
        $this->webp_quality = $webp_quality;
        
        // ตรวจสอบว่ามี GD extension หรือไม่
        $this->has_gd = extension_loaded('gd');
        
        // ตรวจสอบการรองรับ WebP
        $this->has_webp = $this->has_gd && function_exists('imagewebp') && function_exists('imagecreatefromwebp');
        
        // สร้างโฟลเดอร์ถ้ายังไม่มี
        if (!file_exists($this->upload_dir)) {
            mkdir($this->upload_dir, 0755, true);
        }
    }
    
    // สร้างชื่อไฟล์ที่ไม่ซ้ำ
    public function generateUniqueFilename($original_name) {
        $original_extension = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
        $timestamp = date('YmdHis');
        $random = substr(md5(uniqid(rand(), true)), 0, 8);
        
        // ใช้ .webp ถ้ารองรับ WebP และไฟล์เป็นรูปภาพที่แปลงได้
        $supported_for_webp = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        if ($this->has_webp && in_array($original_extension, $supported_for_webp)) {
            return "img_{$timestamp}_{$random}.webp";
        }
        
        // ถ้าไม่รองรับ WebP หรือไฟล์ไม่สามารถแปลงได้ ใช้นามสกุลเดิม
        return "img_{$timestamp}_{$random}.{$original_extension}";
    }
    
    // ตรวจสอบไฟล์รูปภาพ
    public function validateImage($file) {
        $errors = [];
        
        // ตรวจสอบว่ามีไฟล์หรือไม่
        if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            $errors[] = "ไม่พบไฟล์ที่อัปโหลด: {$file['name']}";
            return $errors;
        }
        
        // ตรวจสอบขนาดไฟล์
        $max_size = $this->parseSize(ini_get('upload_max_filesize'));
        if ($file['size'] > $max_size) {
            $errors[] = "ไฟล์ {$file['name']} มีขนาด " . $this->formatFileSize($file['size']) . 
                       " ใหญ่เกินกำหนด (ขนาดสูงสุด: " . ini_get('upload_max_filesize') . ")";
        }
        
        // ตรวจสอบประเภทไฟล์
        $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
        $file_type = strtolower($file['type']);
        
        if (!in_array($file_type, $allowed_types)) {
            $errors[] = "ประเภทไฟล์ {$file['name']} ไม่ถูกต้อง (ประเภท: {$file['type']}) - รองรับเฉพาะ JPEG, PNG, GIF, WebP";
        }
        
        // ตรวจสอบนามสกุลไฟล์
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if (!in_array($extension, $allowed_extensions)) {
            $errors[] = "นามสกุลไฟล์ {$file['name']} ไม่ถูกต้อง (.{$extension}) - รองรับเฉพาะ: " . implode(', ', $allowed_extensions);
        }
        
        // ตรวจสอบว่าเป็นรูปภาพจริง (ใช้ getimagesize แทน GD)
        if ($file['tmp_name'] && function_exists('getimagesize')) {
            $image_info = @getimagesize($file['tmp_name']);
            if (!$image_info) {
                $errors[] = "ไฟล์ {$file['name']} ไม่ใช่รูปภาพที่ถูกต้อง หรือไฟล์เสียหาย";
            } else {
                // ตรวจสอบขนาดภาพ
                $width = $image_info[0];
                $height = $image_info[1];
                
                if ($width < 10 || $height < 10) {
                    $errors[] = "รูปภาพ {$file['name']} มีขนาดเล็กเกินไป ({$width}x{$height} พิกเซล)";
                }
                
                if ($width > 10000 || $height > 10000) {
                    $errors[] = "รูปภาพ {$file['name']} มีขนาดใหญ่เกินไป ({$width}x{$height} พิกเซล) ขนาดสูงสุด: 10000x10000 พิกเซล";
                }
                
                // ตรวจสอบความสอดคล้องระหว่าง MIME type และข้อมูลภาพ
                $detected_type = $image_info['mime'];
                if ($detected_type !== $file_type && !($file_type === 'image/jpg' && $detected_type === 'image/jpeg')) {
                    $errors[] = "ประเภทไฟล์ {$file['name']} ไม่ตรงกับเนื้อหา (ประกาศ: {$file_type}, จริง: {$detected_type})";
                }
            }
        }
        
        // ตรวจสอบความปลอดภัยไฟล์
        $file_content = file_get_contents($file['tmp_name'], false, null, 0, 1024);
        if ($file_content && preg_match('/<\?php|<script|<html/i', $file_content)) {
            $errors[] = "ไฟล์ {$file['name']} มีเนื้อหาที่อาจเป็นอันตราย";
        }
        
        return $errors;
    }
    
    // แปลงรูปภาพเป็น WebP
    public function convertToWebP($source_path, $destination_path, $source_mime_type = null) {
        // ถ้าไม่รองรับ WebP ให้คัดลอกไฟล์เดิม
        if (!$this->has_webp) {
            return copy($source_path, $destination_path);
        }
        
        try {
            // ตรวจสอบประเภทไฟล์ถ้าไม่ได้ระบุ
            if (!$source_mime_type) {
                $image_info = @getimagesize($source_path);
                if (!$image_info) {
                    return copy($source_path, $destination_path);
                }
                $source_mime_type = $image_info['mime'];
            }
            
            // สร้าง image resource ตามประเภทไฟล์
            $source_image = null;
            switch ($source_mime_type) {
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
            
            // บันทึกเป็น WebP
            $result = @imagewebp($source_image, $destination_path, $this->webp_quality);
            
            // ล้าง memory
            imagedestroy($source_image);
            
            // ถ้าบันทึกไม่สำเร็จ ให้คัดลอกไฟล์เดิม
            if (!$result) {
                return copy($source_path, $destination_path);
            }
            
            return true;
            
        } catch (Exception $e) {
            error_log("WebP conversion error: " . $e->getMessage());
            return copy($source_path, $destination_path);
        }
    }
    
    // ปรับขนาดรูปภาพและแปลงเป็น WebP (ถ้ารองรับ)
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
            
            // ถ้ารูปเล็กกว่าขนาดที่กำหนด ไม่ต้องปรับขนาด แต่ยังแปลงเป็น WebP ได้
            if ($ratio >= 1) {
                if ($this->has_webp && pathinfo($destination_path, PATHINFO_EXTENSION) === 'webp') {
                    return $this->convertToWebP($source_path, $destination_path, $mime_type);
                } else {
                    return copy($source_path, $destination_path);
                }
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
            if ($mime_type == 'image/png' || $mime_type == 'image/gif' || $mime_type == 'image/webp') {
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
            
            // บันทึกไฟล์ใหม่ - ใช้ WebP ถ้ารองรับและชื่อไฟล์เป็น .webp
            $result = false;
            $destination_extension = strtolower(pathinfo($destination_path, PATHINFO_EXTENSION));
            
            if ($this->has_webp && $destination_extension === 'webp') {
                // บันทึกเป็น WebP
                $result = @imagewebp($new_image, $destination_path, $this->webp_quality);
            } else {
                // บันทึกตามประเภทเดิม
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
            'webp_available' => $this->has_webp,
            'gd_version' => $this->has_gd ? gd_info()['GD Version'] ?? 'Unknown' : 'Not available',
            'webp_quality' => $this->webp_quality,
            'auto_webp_conversion' => $this->has_webp,
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
            if (function_exists('imagecreatefromwebp')) $formats[] = 'WebP (Input)';
            if (function_exists('imagewebp')) $formats[] = 'WebP (Output)';
            
            if ($this->has_webp) {
                $formats[] = 'Auto WebP Conversion (85% quality)';
            }
        } else {
            $formats[] = 'Basic file copy (no resizing/conversion)';
        }
        
        return $formats;
    }
    
    // แปลงรหัสข้อผิดพลาดการอัปโหลดเป็นข้อความภาษาไทย
    public function getUploadErrorMessage($error_code) {
        switch ($error_code) {
            case UPLOAD_ERR_OK:
                return 'อัปโหลดสำเร็จ';
            case UPLOAD_ERR_INI_SIZE:
                return 'ไฟล์มีขนาดใหญ่เกินที่กำหนดในระบบ (' . ini_get('upload_max_filesize') . ')';
            case UPLOAD_ERR_FORM_SIZE:
                return 'ไฟล์มีขนาดใหญ่เกินที่กำหนดในฟอร์ม';
            case UPLOAD_ERR_PARTIAL:
                return 'ไฟล์ถูกอัปโหลดไม่สมบูรณ์';
            case UPLOAD_ERR_NO_FILE:
                return 'ไม่มีไฟล์ถูกอัปโหลด';
            case UPLOAD_ERR_NO_TMP_DIR:
                return 'ไม่พบโฟลเดอร์ชั่วคราวสำหรับอัปโหลด';
            case UPLOAD_ERR_CANT_WRITE:
                return 'ไม่สามารถเขียนไฟล์ลงดิสก์ได้';
            case UPLOAD_ERR_EXTENSION:
                return 'การอัปโหลดถูกหยุดโดย extension ของ PHP';
            default:
                return 'เกิดข้อผิดพลาดที่ไม่ทราบสาเหตุ (รหัส: ' . $error_code . ')';
        }
    }
    
    // ตรวจสอบการตั้งค่า PHP สำหรับการอัปโหลด
    public function validatePHPUploadSettings() {
        $issues = [];
        $warnings = [];
        
        // ตรวจสอบการเปิดใช้การอัปโหลด
        if (!ini_get('file_uploads')) {
            $issues[] = 'การอัปโหลดไฟล์ถูกปิดใช้งานในระบบ (file_uploads = Off)';
        }
        
        // ตรวจสอบขนาดไฟล์สูงสุด
        $upload_max = $this->parseSize(ini_get('upload_max_filesize'));
        $post_max = $this->parseSize(ini_get('post_max_size'));
        
        if ($upload_max === 0) {
            $issues[] = 'ไม่สามารถอัปโหลดไฟล์ได้ (upload_max_filesize = 0)';
        } elseif ($upload_max < 1048576) { // < 1MB
            $warnings[] = 'ขนาดไฟล์สูงสุดน้อยมาก (' . $this->formatFileSize($upload_max) . ')';
        }
        
        if ($post_max < $upload_max) {
            $warnings[] = 'post_max_size (' . ini_get('post_max_size') . ') น้อยกว่า upload_max_filesize (' . ini_get('upload_max_filesize') . ')';
        }
        
        // ตรวจสอบจำนวนไฟล์สูงสุด
        $max_files = (int)ini_get('max_file_uploads');
        if ($max_files < 10) {
            $warnings[] = 'จำนวนไฟล์ที่อัปโหลดได้พร้อมกันน้อย (' . $max_files . ' ไฟล์)';
        }
        
        // ตรวจสอบเวลาสูงสุด
        $max_execution = (int)ini_get('max_execution_time');
        if ($max_execution > 0 && $max_execution < 60) {
            $warnings[] = 'เวลาประมวลผลสูงสุดอาจไม่เพียงพอสำหรับไฟล์ขนาดใหญ่ (' . $max_execution . ' วินาที)';
        }
        
        // ตรวจสอบสิทธิ์โฟลเดอร์
        if (!is_dir($this->upload_dir)) {
            $issues[] = 'โฟลเดอร์อัปโหลดไม่มีอยู่: ' . $this->upload_dir;
        } elseif (!is_writable($this->upload_dir)) {
            $issues[] = 'ไม่สามารถเขียนไฟล์ในโฟลเดอร์อัปโหลดได้: ' . $this->upload_dir;
        }
        
        return [
            'issues' => $issues,
            'warnings' => $warnings,
            'settings' => [
                'file_uploads' => ini_get('file_uploads') ? 'On' : 'Off',
                'upload_max_filesize' => ini_get('upload_max_filesize'),
                'post_max_size' => ini_get('post_max_size'),
                'max_file_uploads' => ini_get('max_file_uploads'),
                'max_execution_time' => ini_get('max_execution_time'),
                'upload_dir' => $this->upload_dir,
                'upload_dir_writable' => is_writable($this->upload_dir) ? 'Yes' : 'No'
            ]
        ];
    }
    
    // แปลงขนาดไฟล์จาก string เป็น bytes
    private function parseSize($size) {
        $size = trim($size);
        $last = strtolower($size[strlen($size) - 1]);
        $size = (int)$size;
        
        switch($last) {
            case 'g': $size *= 1024;
            case 'm': $size *= 1024;
            case 'k': $size *= 1024;
        }
        
        return $size;
    }
}
?>