<?php
/**
 * ============================================================================
 * ADMIN DELETE LEAD API
 * Endpoint: POST /api/delete-lead.php
 * Deletes lead from leads table and related records in assessment_interactions
 * ============================================================================
 */

require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/auth.php';

requireAdminAuth(true);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJson(405, ['success' => false, 'message' => 'Method Not Allowed. Use POST.']);
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

$id = (int)($data['id'] ?? ($_POST['id'] ?? 0));
$sessionId = trim($data['session_id'] ?? ($_POST['session_id'] ?? ''));

if ($id <= 0 && empty($sessionId)) {
    sendJson(400, ['success' => false, 'message' => 'Lead ID or session_id is required.']);
}

try {
    $pdo = getAdminDB();
    $leadsTable = ADMIN_TABLE_LEADS;
    $interactionsTable = ADMIN_TABLE_INTERACTIONS;

    // 1. Find the target lead
    if ($id > 0) {
        $stmt = $pdo->prepare("SELECT id, session_id, lead_reference, full_name FROM `{$leadsTable}` WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
    } else {
        $stmt = $pdo->prepare("SELECT id, session_id, lead_reference, full_name FROM `{$leadsTable}` WHERE session_id = ? LIMIT 1");
        $stmt->execute([$sessionId]);
    }
    $lead = $stmt->fetch();

    if (!$lead) {
        sendJson(404, ['success' => false, 'message' => 'Lead not found or already deleted.']);
    }

    $targetSessionId = $lead['session_id'];
    $leadRef = $lead['lead_reference'];

    // 2. Delete related clicks/interactions
    $delInt = $pdo->prepare("DELETE FROM `{$interactionsTable}` WHERE session_id = ?");
    $delInt->execute([$targetSessionId]);
    $deletedInteractions = $delInt->rowCount();

    // 3. Delete lead record
    $delLead = $pdo->prepare("DELETE FROM `{$leadsTable}` WHERE id = ?");
    $delLead->execute([$lead['id']]);

    sendJson(200, [
        'success' => true,
        'message' => "Lead {$leadRef} deleted successfully.",
        'deleted_id' => $lead['id'],
        'deleted_interactions_count' => $deletedInteractions
    ]);

} catch (Exception $e) {
    error_log("[Delete Lead Error] " . $e->getMessage());
    sendJson(500, ['success' => false, 'message' => 'Failed to delete lead: ' . $e->getMessage()]);
}
