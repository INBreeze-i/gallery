<?php
// ... other code

// Fix CSRF token validation
if (!CSRFProtection::validateToken($_POST['csrf_token'] ?? '', 'upload_images')) {
    // Handle invalid CSRF token
}

// ... other code
