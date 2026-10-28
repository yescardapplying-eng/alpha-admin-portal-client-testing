<?php
/**
 * ============================================================================
 * ADMIN CSV EXPORT API
 * Endpoint: GET /api/export-csv.php
 * ============================================================================
 */

require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/auth.php';

requireAdminAuth(false); // Redirect if not logged in

try {
    $pdo = getAdminDB();

    $date = trim($_GET['date'] ?? '');
    $dateFrom = trim($_GET['date_from'] ?? '');
    $dateTo = trim($_GET['date_to'] ?? '');
    $status = trim($_GET['status'] ?? 'all');
    $search = trim($_GET['search'] ?? '');

    $where = ["1=1"];
    $params = [];

    if (!empty($date)) {
        $where[] = "DATE(created_at) = :exact_date";
        $params[':exact_date'] = $date;
    } else {
        if (!empty($dateFrom)) {
            $where[] = "DATE(created_at) >= :date_from";
            $params[':date_from'] = $dateFrom;
        }
        if (!empty($dateTo)) {
            $where[] = "DATE(created_at) <= :date_to";
            $params[':date_to'] = $dateTo;
        }
    }

    if ($status !== 'all' && in_array($status, ['completed', 'partial'], true)) {
        $where[] = "status = :status";
        $params[':status'] = $status;
    }

    if (!empty($search)) {
        $where[] = "(full_name LIKE :search OR mobile_number LIKE :search OR whatsapp_number LIKE :search OR lead_reference LIKE :search OR state LIKE :search)";
        $params[':search'] = "%{$search}%";
    }

    $whereSql = implode(' AND ', $where);
    $leadsTable = ADMIN_TABLE_LEADS;

    $sql = "
        SELECT 
            lead_reference, status, current_step,
            full_name, mobile_number, whatsapp_number, state,
            challenge, trading_segment, trading_capital,
            utm_source, utm_medium, utm_campaign,
            ip_address, created_at, completed_at
        FROM `{$leadsTable}`
        WHERE {$whereSql}
        ORDER BY created_at DESC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    $filename = "stock_market_leads_" . date('Y-m-d_His') . ".csv";

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    // UTF-8 BOM for proper Excel encoding
    echo "\xEF\xBB\xBF";

    $out = fopen('php://output', 'w');

    // Header row
    fputcsv($out, [
        'Lead Reference', 'Status', 'Current Step',
        'Full Name', 'Mobile Number', 'WhatsApp Number', 'State',
        'Challenge', 'Trading Segment', 'Trading Capital',
        'UTM Source', 'UTM Medium', 'UTM Campaign',
        'IP Address', 'Created Date', 'Completed Date'
    ]);

    foreach ($rows as $row) {
        fputcsv($out, [
            $row['lead_reference'],
            strtoupper($row['status']),
            $row['current_step'],
            $row['full_name'] ?: '—',
            $row['mobile_number'] ?: '—',
            $row['whatsapp_number'] ?: '—',
            $row['state'] ?: '—',
            $row['challenge'] ?: '—',
            $row['trading_segment'] ?: '—',
            $row['trading_capital'] ?: '—',
            $row['utm_source'] ?: '',
            $row['utm_medium'] ?: '',
            $row['utm_campaign'] ?: '',
            $row['ip_address'] ?: '',
            $row['created_at'],
            $row['completed_at'] ?: ''
        ]);
    }

    fclose($out);
    exit;

} catch (Exception $e) {
    http_response_code(500);
    echo "Error exporting CSV: " . $e->getMessage();
    exit;
}
