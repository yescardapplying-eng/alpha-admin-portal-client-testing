<?php
/**
 * ============================================================================
 * ADMIN LOGIN API
 * Endpoint: POST /api/login.php
 * ============================================================================
 */

require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJson(405, ['success' => false, 'message' => 'Method Not Allowed. Use POST.']);
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

$username = trim($data['username'] ?? ($_POST['username'] ?? ''));
$password = trim($data['password'] ?? ($_POST['password'] ?? ''));

if (empty($username) || empty($password)) {
    sendJson(400, ['success' => false, 'message' => 'Username and password are required.']);
}

try {
    $pdo = getAdminDB();
    $usersTable = ADMIN_TABLE_USERS;
    $stmt = $pdo->prepare("SELECT id, username, password_hash, role FROM `{$usersTable}` WHERE username = ? LIMIT 1");
    $stmt->execute([$username]);
    $admin = $stmt->fetch();

    if (!$admin || !password_verify($password, $admin['password_hash'])) {
        sendJson(401, ['success' => false, 'message' => 'Invalid username or password.']);
    }

    // Update last login timestamp
    $up = $pdo->prepare("UPDATE `{$usersTable}` SET last_login = NOW() WHERE id = ?");
    $up->execute([$admin['id']]);

    // Establish secure session
    loginAdminSession($admin['username'], $admin['role']);

    sendJson(200, [
        'success' => true,
        'message' => 'Authentication successful.',
        'user' => [
            'username' => $admin['username'],
            'role' => $admin['role']
        ],
        'redirect' => '/index.html'
    ]);

} catch (Exception $e) {
    error_log("[Login Error] " . $e->getMessage());
    sendJson(500, ['success' => false, 'message' => 'Server error during authentication: ' . $e->getMessage()]);
}
