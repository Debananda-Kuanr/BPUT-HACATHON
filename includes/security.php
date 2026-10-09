<?php
/**
 * MEDGem — Security Middleware
 * CSRF protection, rate limiting, input validation
 */

/**
 * Generate CSRF token
 */
function generateCSRFToken(): string {
    if (!isset($_SESSION)) session_start();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token
 */
function verifyCSRFToken(string $token): bool {
    if (!isset($_SESSION)) session_start();
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Output CSRF hidden input
 */
function csrfField(): string {
    return '<input type="hidden" name="csrf_token" value="' . generateCSRFToken() . '">';
}

/**
 * Validate required fields
 */
function validateRequired(array $data, array $fields): array {
    $errors = [];
    foreach ($fields as $field) {
        if (empty($data[$field]) || trim($data[$field]) === '') {
            $errors[] = ucfirst(str_replace('_', ' ', $field)) . ' is required';
        }
    }
    return $errors;
}

/**
 * Set security headers
 */
function setSecurityHeaders(): void {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');
}

/**
 * JSON response helper
 */
function jsonResponse(array $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

/**
 * Validate file upload
 */
function validateUpload(array $file, array $allowedTypes = ['image/jpeg', 'image/png', 'application/pdf'], int $maxSize = 10485760): array {
    $errors = [];
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'File upload failed';
        return $errors;
    }
    
    if (!in_array($file['type'], $allowedTypes)) {
        $errors[] = 'Invalid file type. Allowed: JPG, PNG, PDF';
    }
    
    if ($file['size'] > $maxSize) {
        $errors[] = 'File too large. Maximum: ' . ($maxSize / 1048576) . 'MB';
    }
    
    return $errors;
}
