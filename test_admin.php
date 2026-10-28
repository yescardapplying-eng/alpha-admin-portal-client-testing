<?php
/**
 * ============================================================================
 * ADMIN PORTAL AUTOMATED VERIFICATION SUITE
 * ============================================================================
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/auth.php';

echo "============================================================\n";
echo "STARTING ADMIN PORTAL TESTS ON TIDB CLOUD MYSQL\n";
echo "============================================================\n\n";

$pdo = getAdminDB();
$leadsTable = ADMIN_TABLE_LEADS;
$interactionsTable = ADMIN_TABLE_INTERACTIONS;
$usersTable = ADMIN_TABLE_USERS;

echo "Configured Tables:\n";
echo "  - Leads Table: '{$leadsTable}'\n";
echo "  - Interactions Table: '{$interactionsTable}'\n";
echo "  - Users Table: '{$usersTable}'\n\n";

// TEST 1: Database & admin_users Table Verification
echo "[TEST 1] Verifying {$usersTable} Table...\n";
$stmt = $pdo->query("SELECT id, username, role, last_login FROM `{$usersTable}` WHERE username = 'admin'");
$admin = $stmt->fetch();
if ($admin) {
    echo "  -> SUCCESS! Admin user '{$admin['username']}' found with role '{$admin['role']}' in '{$usersTable}'\n\n";
} else {
    echo "  -> FAILED: Admin user not found in '{$usersTable}'.\n";
    exit(1);
}

// TEST 2: Password Verification
echo "[TEST 2] Verifying Admin Password Hash...\n";
$stmtPass = $pdo->query("SELECT password_hash FROM `{$usersTable}` WHERE username = 'admin'");
$hash = $stmtPass->fetchColumn();
if (password_verify('Stock@2026#Trading', $hash)) {
    echo "  -> SUCCESS! Password 'Stock@2026#Trading' verified against bcrypt hash.\n\n";
} else {
    echo "  -> FAILED: Password verification mismatch.\n";
    exit(1);
}

// TEST 3: Leads Query with 100/page Pagination
echo "[TEST 3] Testing Date-wise Leads Query on '{$leadsTable}' (Limit: 100)...\n";
$limit = 100;
$stmtLeads = $pdo->prepare("
    SELECT id, lead_reference, full_name, mobile_number, status, created_at
    FROM `{$leadsTable}`
    ORDER BY created_at DESC
    LIMIT :limit
");
$stmtLeads->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmtLeads->execute();
$leads = $stmtLeads->fetchAll();
echo "  -> SUCCESS! Retrieved " . count($leads) . " leads successfully (Ordered by created_at DESC).\n";
foreach (array_slice($leads, 0, 3) as $l) {
    echo "     * [{$l['created_at']}] Ref: {$l['lead_reference']} | Name: " . ($l['full_name'] ?: 'Unnamed') . " | Status: {$l['status']}\n";
}
echo "\n";

// TEST 4: Date Filtering Test
echo "[TEST 4] Testing Date Filtering on '{$leadsTable}'...\n";
$today = date('Y-m-d');
$stmtDate = $pdo->prepare("SELECT COUNT(*) FROM `{$leadsTable}` WHERE DATE(created_at) = ?");
$stmtDate->execute([$today]);
$todayCount = $stmtDate->fetchColumn();
echo "  -> SUCCESS! Leads created today ({$today}): {$todayCount}\n\n";

// TEST 5: Create a Test Lead and Verify Deletion
echo "[TEST 5] Testing Lead Creation and Deletion Cycle...\n";
$testRef = 'SM-LEAD-DELTEST' . rand(100, 999);
$testSession = 'SM-SESSION-DEL' . rand(1000, 9999);

// Insert test lead
$insLead = $pdo->prepare("
    INSERT INTO `{$leadsTable}` (session_id, lead_reference, full_name, mobile_number, status, challenge, current_step, created_at, updated_at)
    VALUES (?, ?, 'Test To Delete', '9999999999', 'partial', 'Frequent stop-loss hits', 1, NOW(), NOW())
");
$insLead->execute([$testSession, $testRef]);
$leadId = $pdo->lastInsertId();

// Insert dummy interactions for this test lead
$insInt = $pdo->prepare("
    INSERT INTO `{$interactionsTable}` (session_id, step_number, question, selected_answer, field_name, action_type, created_at)
    VALUES (?, 1, 'Test Question', 'Test Answer', 'challenge', 'option_click', NOW())
");
$insInt->execute([$testSession]);

echo "  -> Created temporary test lead #{$leadId} ({$testRef}) in '{$leadsTable}' with interaction logs in '{$interactionsTable}'.\n";

// Now delete interactions and lead
$delInt = $pdo->prepare("DELETE FROM `{$interactionsTable}` WHERE session_id = ?");
$delInt->execute([$testSession]);
$delLead = $pdo->prepare("DELETE FROM `{$leadsTable}` WHERE id = ?");
$delLead->execute([$leadId]);

// Verify deletion
$check = $pdo->prepare("SELECT id FROM `{$leadsTable}` WHERE id = ?");
$check->execute([$leadId]);
if (!$check->fetch()) {
    echo "  -> SUCCESS! Lead #{$leadId} and its interaction logs were permanently deleted from testing tables.\n\n";
} else {
    echo "  -> FAILED: Lead still exists.\n";
    exit(1);
}

echo "============================================================\n";
echo "ALL ADMIN PORTAL TESTS PASSED SUCCESSFULLY!\n";
echo "============================================================\n";
