<?php
/**
 * ============================================================================
 * ADMIN LEADS LISTING API (DATE-WISE WITH 100/PAGE PAGINATION & FILTERS)
 * Endpoint: GET /api/leads.php
 * ============================================================================
 */

require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/auth.php';

requireAdminAuth(true);

try {
    $pdo = getAdminDB();

    // 1. Pagination Parameters (Default 100 per page as specified)
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = max(1, min(250, (int)($_GET['limit'] ?? 100)));
    $offset = ($page - 1) * $limit;

    // 2. Filter Parameters
    $date = trim($_GET['date'] ?? '');
    $dateFrom = trim($_GET['date_from'] ?? '');
    $dateTo = trim($_GET['date_to'] ?? '');
    $status = trim($_GET['status'] ?? 'all');
    $search = trim($_GET['search'] ?? '');
    $sort = strtoupper(trim($_GET['sort'] ?? 'DESC'));
    if ($sort !== 'ASC' && $sort !== 'DESC') {
        $sort = 'DESC';
    }

    $where = ["1=1"];
    $params = [];

    // Date filters
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

    // Status filter
    if ($status !== 'all' && in_array($status, ['completed', 'partial'], true)) {
        $where[] = "status = :status";
        $params[':status'] = $status;
    }

    // Search query filter
    if (!empty($search)) {
        $where[] = "(
            full_name LIKE :search 
            OR mobile_number LIKE :search 
            OR whatsapp_number LIKE :search 
            OR lead_reference LIKE :search 
            OR challenge LIKE :search 
            OR state LIKE :search
            OR trading_segment LIKE :search
        )";
        $params[':search'] = "%{$search}%";
    }

    $whereSql = implode(' AND ', $where);
    $leadsTable = ADMIN_TABLE_LEADS;

    // 3. Count Total Matching Records
    $countSql = "SELECT COUNT(*) as total FROM `{$leadsTable}` WHERE {$whereSql}";
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($params);
    $totalItems = (int)$countStmt->fetchColumn();

    $totalPages = (int)ceil($totalItems / $limit);
    if ($totalPages === 0) {
        $totalPages = 1;
    }

    // 4. Fetch Paginated Records (Date-wise sorted)
    $sql = "
        SELECT 
            id, session_id, lead_reference, status, current_step,
            challenge, full_name, mobile_number, trading_segment, trading_capital,
            whatsapp_number, state,
            utm_source, utm_medium, utm_campaign,
            created_at, updated_at, completed_at
        FROM `{$leadsTable}`
        WHERE {$whereSql}
        ORDER BY created_at {$sort}
        LIMIT :limit OFFSET :offset
    ";

    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $val) {
        $stmt->bindValue($key, $val);
    }
    $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
    $stmt->execute();
    $items = $stmt->fetchAll();

    // 5. Aggregate Quick Counts
    $summaryStmt = $pdo->query("
        SELECT 
            COUNT(*) as total_all,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as total_completed,
            SUM(CASE WHEN status = 'partial' THEN 1 ELSE 0 END) as total_partial,
            SUM(CASE WHEN DATE(created_at) = CURRENT_DATE() THEN 1 ELSE 0 END) as total_today
        FROM `{$leadsTable}`
    ");
    $summary = $summaryStmt->fetch() ?: [
        'total_all' => 0,
        'total_completed' => 0,
        'total_partial' => 0,
        'total_today' => 0
    ];

    sendJson(200, [
        'success' => true,
        'items' => $items,
        'pagination' => [
            'total_items' => $totalItems,
            'current_page' => $page,
            'per_page' => $limit,
            'total_pages' => $totalPages,
            'has_next' => $page < $totalPages,
            'has_prev' => $page > 1
        ],
        'filters' => [
            'date' => $date,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'status' => $status,
            'search' => $search,
            'sort' => $sort
        ],
        'summary' => [
            'total_all' => (int)($summary['total_all'] ?? 0),
            'total_completed' => (int)($summary['total_completed'] ?? 0),
            'total_partial' => (int)($summary['total_partial'] ?? 0),
            'total_today' => (int)($summary['total_today'] ?? 0)
        ]
    ]);

} catch (Exception $e) {
    error_log("[Admin Leads API Error] " . $e->getMessage());
    sendJson(500, ['success' => false, 'message' => 'Failed to fetch leads: ' . $e->getMessage()]);
}
