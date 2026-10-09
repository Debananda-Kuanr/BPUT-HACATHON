<?php
/**
 * MEDGem — Authentication & Session Management
 * Session-based auth with role-based access control
 */

session_start();

/**
 * Check if user is logged in
 */
function isLoggedIn(): bool {
    return isset($_SESSION['user_id']) && isset($_SESSION['role']);
}

/**
 * Get current user data from session
 */
function getCurrentUser(): ?array {
    if (!isLoggedIn()) return null;
    return [
        'id'    => $_SESSION['user_id'],
        'name'  => $_SESSION['user_name'],
        'email' => $_SESSION['user_email'],
        'role'  => $_SESSION['role'],
    ];
}

/**
 * Require authentication — redirect to login if not authenticated
 */
function requireAuth(string $requiredRole = ''): void {
    if (!isLoggedIn()) {
        header('Location: /BPUT/staff/login.php');
        exit;
    }
    if ($requiredRole && $_SESSION['role'] !== $requiredRole) {
        http_response_code(403);
        die('Access denied. Insufficient privileges.');
    }
}

/**
 * Attempt login with email and password
 */
function attemptLogin(string $email, string $password): array {
    require_once __DIR__ . '/db.php';
    $db = getDB();
    
    $stmt = $db->prepare("SELECT id, name, email, password, role FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if (!$user || !password_verify($password, $user['password'])) {
        return ['success' => false, 'error' => 'Invalid credentials'];
    }
    
    $_SESSION['user_id']    = $user['id'];
    $_SESSION['user_name']  = $user['name'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['role']       = $user['role'];
    
    // Audit log
    require_once __DIR__ . '/functions.php';
    addAuditLog(null, $user['id'], 'LOGIN', $user['role'] . ' logged in');
    
    return ['success' => true, 'role' => $user['role']];
}

/**
 * Logout and destroy session
 */
function logout(): void {
    if (isLoggedIn()) {
        require_once __DIR__ . '/functions.php';
        addAuditLog(null, $_SESSION['user_id'], 'LOGOUT', $_SESSION['role'] . ' logged out');
    }
    session_unset();
    session_destroy();
    header('Location: /BPUT/staff/login.php');
    exit;
}
