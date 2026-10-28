<?php
/**
 * ============================================================================
 * ADMIN USER TABLE SETUP & SEEDER
 * ============================================================================
 */

require_once dirname(__DIR__) . '/config/database.php';

try {
    echo "Connecting to MySQL/TiDB database...\n";
    $pdo = getAdminDB();
    echo "Connected successfully!\n\n";

    $usersTable = ADMIN_TABLE_USERS;

    // Create admin_users table
    $sql = "
        CREATE TABLE IF NOT EXISTS `{$usersTable}` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `username` VARCHAR(100) NOT NULL UNIQUE,
            `password_hash` VARCHAR(255) NOT NULL,
            `role` VARCHAR(50) NOT NULL DEFAULT 'Administrator',
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `last_login` DATETIME NULL,
            INDEX `idx_{$usersTable}_username` (`username`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";

    $pdo->exec($sql);
    echo "Table '{$usersTable}' created or verified.\n";

    // Check if default admin exists
    $defaultUser = getenv('ADMIN_USERNAME') ?: 'admin';
    $defaultPass = getenv('ADMIN_PASSWORD') ?: 'Stock@2026#Trading';

    $stmt = $pdo->prepare("SELECT id FROM `{$usersTable}` WHERE username = ?");
    $stmt->execute([$defaultUser]);
    $existing = $stmt->fetch();

    $hash = password_hash($defaultPass, PASSWORD_BCRYPT);

    if (!$existing) {
        $ins = $pdo->prepare("INSERT INTO `{$usersTable}` (username, password_hash, role) VALUES (?, ?, 'Administrator')");
        $ins->execute([$defaultUser, $hash]);
        echo "Default admin user '{$defaultUser}' created successfully in '{$usersTable}' with password.\n";
    } else {
        // Update password hash to match current .env password
        $up = $pdo->prepare("UPDATE `{$usersTable}` SET password_hash = ? WHERE username = ?");
        $up->execute([$hash, $defaultUser]);
        echo "Admin user '{$defaultUser}' password synchronized in '{$usersTable}'.\n";
    }

    echo "\nAdmin Setup Complete!\n";

} catch (Exception $e) {
    echo "Setup Error: " . $e->getMessage() . "\n";
    exit(1);
}
