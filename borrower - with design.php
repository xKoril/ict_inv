<?php
require 'db.php';

// Handle return form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['slip_no'])) {
    // Process return logic here
    // ... (existing return processing code)
}

// Pagination setup
$slip_page = isset($_GET['slip_page']) ? max(1, (int)$_GET['slip_page']) : 1;
$borrowed_page = isset($_GET['borrowed_page']) ? max(1, (int)$_GET['borrowed_page']) : 1;
$available_page = isset($_GET['available_page']) ? max(1, (int)$_GET['available_page']) : 1;

$limit = 10; // Items per page

// Fetch slip summary with pagination
$slip_offset = ($slip_page - 1) * $limit;

$slip_sql = "
SELECT 
    bt.slip_no,
    COUNT(bt.equipment_id) as total_items,
    SUM(CASE WHEN bt.date_returned IS NOT NULL THEN 1 ELSE 0 END) as returned_items,
    bt.borrower,
    bt.office_borrower as office,
    bt.purpose,
    bt.date_borrowed,
    bt.due_date
FROM borrow_transactions bt
GROUP BY bt.slip_no, bt.borrower, bt.office_borrower, bt.purpose, bt.date_borrowed, bt.due_date
ORDER BY bt.date_borrowed DESC
LIMIT $limit OFFSET $slip_offset
";
$slip_stmt = $pdo->query($slip_sql);
$slips = $slip_stmt->fetchAll();

// Get total slip count for pagination
$slip_count_sql = "SELECT COUNT(DISTINCT slip_no) as total FROM borrow_transactions";
$slip_total = $pdo->query($slip_count_sql)->fetchColumn();
$slip_total_pages = ceil($slip_total / $limit);

// Fetch borrowed equipment filters
$b_type = $_GET['b_type'] ?? '';
$b_date_from = $_GET['b_date_from'] ?? '';
$b_date_to = $_GET['b_date_to'] ?? '';
$b_office = $_GET['b_office'] ?? '';

// Build borrowed equipment query with pagination
$borrowed_offset = ($borrowed_page - 1) * $limit;
$b_where = [];
$b_params = [];
if ($b_type) { $b_where[] = 'e.equipment_type LIKE ?'; $b_params[] = "%$b_type%"; }
if ($b_date_from) { $b_where[] = 'bt.date_borrowed >= ?'; $b_params[] = $b_date_from; }
if ($b_date_to) { $b_where[] = 'bt.date_borrowed <= ?'; $b_params[] = $b_date_to; }
if ($b_office) { $b_where[] = 'bt.office_borrower = ?'; $b_params[] = $b_office; }

$borrowed_sql = "
SELECT 
    e.equipment_type as type,
    e.brand,
    e.model,
    e.serial_number as serial,
    bt.date_borrowed,
    bt.due_date,
    bt.purpose,
    bt.borrower,
    bt.office_borrower as office
FROM borrow_transactions bt
JOIN equipment e ON bt.equipment_id = e.equipment_id
WHERE bt.date_returned IS NULL
AND (bt.equipment_id, bt.date_borrowed) IN (
    SELECT equipment_id, MAX(date_borrowed)
    FROM borrow_transactions 
    WHERE date_returned IS NULL
    GROUP BY equipment_id
)";

if ($b_where) {
    $borrowed_sql .= ' AND ' . implode(' AND ', $b_where);
}
$borrowed_sql .= ' ORDER BY bt.date_borrowed DESC';
$borrowed_sql .= " LIMIT $limit OFFSET $borrowed_offset";

$borrowed_stmt = $pdo->prepare($borrowed_sql);
$borrowed_stmt->execute($b_params);
$borrowed = $borrowed_stmt->fetchAll();

// Get total borrowed count for pagination
$borrowed_count_sql = "
SELECT COUNT(*) as total FROM (
    SELECT bt.equipment_id
    FROM borrow_transactions bt
    JOIN equipment e ON bt.equipment_id = e.equipment_id
    WHERE bt.date_returned IS NULL
    AND (bt.equipment_id, bt.date_borrowed) IN (
        SELECT equipment_id, MAX(date_borrowed)
        FROM borrow_transactions 
        WHERE date_returned IS NULL
        GROUP BY equipment_id
    )";
if ($b_where) {
    $borrowed_count_sql .= ' AND ' . implode(' AND ', $b_where);
}
$borrowed_count_sql .= ') as borrowed_items';

$borrowed_count_stmt = $pdo->prepare($borrowed_count_sql);
$borrowed_count_stmt->execute($b_params);
$borrowed_total = $borrowed_count_stmt->fetchColumn();
$borrowed_total_pages = max(1, ceil($borrowed_total / $limit)); // Ensure at least 1 page

// Fetch available equipment filters
$a_type = $_GET['a_type'] ?? '';
$a_brand = $_GET['a_brand'] ?? '';
$a_model = $_GET['a_model'] ?? '';

// Fetch available equipment with pagination
$available_offset = ($available_page - 1) * $limit;

$a_where = [];
$a_params = [];
if ($a_type) { $a_where[] = 'equipment_type LIKE ?'; $a_params[] = "%$a_type%"; }
if ($a_brand) { $a_where[] = 'brand LIKE ?'; $a_params[] = "%$a_brand%"; }
if ($a_model) { $a_where[] = 'model LIKE ?'; $a_params[] = "%$a_model%"; }

$available_sql = "
SELECT equipment_type as type, brand, model, locator, serial_number as serial, description_specification as description
FROM equipment 
WHERE equipment_status = 'Available for Deployment'";

if ($a_where) {
    $available_sql .= ' AND ' . implode(' AND ', $a_where);
}
$available_sql .= ' ORDER BY equipment_type, brand, model';
$available_sql .= " LIMIT $limit OFFSET $available_offset";

$available_stmt = $pdo->prepare($available_sql);
$available_stmt->execute($a_params);
$available = $available_stmt->fetchAll();

// Get total available count for pagination
$available_count_sql = "
SELECT COUNT(*) as total
FROM equipment 
WHERE equipment_status = 'Available for Deployment'";
if ($a_where) {
    $available_count_sql .= ' AND ' . implode(' AND ', $a_where);
}

$available_count_stmt = $pdo->prepare($available_count_sql);
$available_count_stmt->execute($a_params);
$available_total = $available_count_stmt->fetchColumn();
$available_total_pages = max(1, ceil($available_total / $limit)); // Ensure at least 1 page

// Get unique offices for filter
$offices_stmt = $pdo->query("SELECT DISTINCT office_borrower FROM borrow_transactions WHERE office_borrower IS NOT NULL ORDER BY office_borrower");
$offices = $offices_stmt->fetchAll(PDO::FETCH_COLUMN);

// Function to build pagination URL
function buildPaginationUrl($page, $page_param, $current_get) {
    $params = $current_get;
    $params[$page_param] = $page;
    return '?' . http_build_query($params);
}

// Function to render pagination
function renderPagination($current_page, $total_pages, $page_param, $current_get, $total_items, $limit) {
    if ($total_pages <= 1) return '';
    
    $html = '<div class="pagination">';
    
    // Previous Button
    if ($current_page > 1) {
        $prev_url = buildPaginationUrl($current_page - 1, $page_param, $current_get);
        $html .= '<a href="' . htmlspecialchars($prev_url) . '" class="nav-btn">« Previous</a>';
    } else {
        $html .= '<span class="disabled">« Previous</span>';
    }
    
    // Page Numbers
    $start_page = max(1, $current_page - 2);
    $end_page = min($total_pages, $current_page + 2);
    
    if ($start_page > 1) {
        $first_url = buildPaginationUrl(1, $page_param, $current_get);
        $html .= '<a href="' . htmlspecialchars($first_url) . '">1</a>';
        if ($start_page > 2) {
            $html .= '<span>...</span>';
        }
    }
    
    for ($i = $start_page; $i <= $end_page; $i++) {
        if ($i == $current_page) {
            $html .= '<span class="current">' . $i . '</span>';
        } else {
            $page_url = buildPaginationUrl($i, $page_param, $current_get);
            $html .= '<a href="' . htmlspecialchars($page_url) . '">' . $i . '</a>';
        }
    }
    
    if ($end_page < $total_pages) {
        if ($end_page < $total_pages - 1) {
            $html .= '<span>...</span>';
        }
        $last_url = buildPaginationUrl($total_pages, $page_param, $current_get);
        $html .= '<a href="' . htmlspecialchars($last_url) . '">' . $total_pages . '</a>';
    }
    
    // Next Button
    if ($current_page < $total_pages) {
        $next_url = buildPaginationUrl($current_page + 1, $page_param, $current_get);
        $html .= '<a href="' . htmlspecialchars($next_url) . '" class="nav-btn">Next »</a>';
    } else {
        $html .= '<span class="disabled">Next »</span>';
    }
    
    $html .= '</div>';
    
    // Pagination info
    $start_item = (($current_page - 1) * $limit) + 1;
    $end_item = min($current_page * $limit, $total_items);
    $html .= '<div class="pagination-info">';
    $html .= "Showing $start_item to $end_item of $total_items items";
    $html .= '</div>';
    
    return $html;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Borrower Dashboard</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            backdrop-filter: blur(10px);
        }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 3px solid #e0e7ff;
        }
        
        .header h1 {
            color: #1e293b;
            font-size: 2.5rem;
            font-weight: 700;
            background: linear-gradient(135deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .btn {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 12px;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
        }
        
        .section {
            background: white;
            border-radius: 16px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.08);
            border: 1px solid rgba(102, 126, 234, 0.1);
        }
        
        .section h2 {
            color: #1e293b;
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .section h2::before {
            content: '';
            width: 4px;
            height: 24px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-radius: 2px;
        }
        
        .filter-form {
            display: flex;
            gap: 15px;
            align-items: end;
            margin-bottom: 20px;
            padding: 20px;
            background: linear-gradient(135deg, #f8fafc, #e2e8f0);
            border-radius: 12px;
            border: 1px solid #e2e8f0;
        }
        
        .filter-group {
            flex: 1;
        }
        
        .filter-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: #475569;
            font-size: 0.875rem;
        }
        
        .filter-group input,
        .filter-group select {
            width: 100%;
            padding: 10px 12px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 0.875rem;
            transition: all 0.3s ease;
            background: white;
        }
        
        .filter-group input:focus,
        .filter-group select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .filter-buttons {
            display: flex;
            gap: 8px;
            flex-shrink: 0;
        }
        
        .btn-filter {
            background: #667eea;
            color: white;
            padding: 10px 16px;
            border: none;
            border-radius: 8px;
            font-size: 0.875rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .btn-filter:hover {
            background: #5a67d8;
            transform: translateY(-1px);
        }
        
        .btn-clear {
            background: #64748b;
            color: white;
            padding: 10px 16px;
            border: none;
            border-radius: 8px;
            font-size: 0.875rem;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.3s ease;
        }
        
        .btn-clear:hover {
            background: #475569;
            transform: translateY(-1px);
        }
        
        .table-container {
            overflow-x: auto;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            background: white;
        }
        
        th {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 15px 12px;
            text-align: left;
            font-weight: 600;
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        td {
            padding: 12px;
            border-bottom: 1px solid #f1f5f9;
            font-size: 0.875rem;
            color: #475569;
        }
        
        tr:hover {
            background: #f8fafc;
            transition: background 0.2s ease;
        }
        
        .status-badge {
            display: inline-flex;
            align-items: center;
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .status-pending {
            background: #fef3c7;
            color: #92400e;
        }
        
        .status-partial {
            background: #dbeafe;
            color: #1e40af;
        }
        
        .status-complete {
            background: #d1fae5;
            color: #059669;
        }
        
        .btn-return {
            background: #10b981;
            color: white;
            padding: 6px 12px;
            border: none;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
        }
        
        .btn-return:hover {
            background: #059669;
            transform: translateY(-1px);
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 8px;
            margin-top: 20px;
        }
        
        .pagination a,
        .pagination span {
            padding: 8px 12px;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            text-decoration: none;
            color: #475569;
            font-size: 0.875rem;
            transition: all 0.3s ease;
            min-width: 40px;
            text-align: center;
        }
        
        .pagination a:hover {
            background: #667eea;
            color: white;
            border-color: #667eea;
        }
        
        .pagination .current {
            background: #667eea;
            color: white;
            border-color: #667eea;
            font-weight: 600;
        }
        
        .pagination .nav-btn {
            background: #f8fafc;
            border-color: #cbd5e0;
            font-weight: 500;
        }
        
        .pagination .nav-btn:hover {
            background: #667eea;
            color: white;
            border-color: #667eea;
        }
        
        .pagination .disabled {
            background: #f8fafc;
            color: #cbd5e0;
            border-color: #e2e8f0;
            cursor: not-allowed;
        }
        
        .pagination .disabled:hover {
            background: #f8fafc;
            color: #cbd5e0;
            border-color: #e2e8f0;
        }
        
        .pagination-info {
            text-align: center;
            margin-top: 10px;
            color: #64748b;
            font-size: 0.875rem;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .stat-card {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 20px;
            border-radius: 12px;
            text-align: center;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 5px;
        }
        
        .stat-label {
            font-size: 0.875rem;
            opacity: 0.9;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #64748b;
        }
        
        .empty-state h3 {
            font-size: 1.25rem;
            margin-bottom: 10px;
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 20px;
                margin: 10px;
            }
            
            .header {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
            
            .filter-form {
                flex-direction: column;
                gap: 10px;
            }
            
            .filter-buttons {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📋 Borrower Dashboard</h1>
            <div>
                <a href="borrow_equipment.php" class="btn">
                    ➕ Create Borrow Form
                </a>
                <a href="index.php" class="btn" style="margin-left: 10px;">
                    🔙 Back to Inventory
                </a>
            </div>
        </div>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?= $slip_total ?></div>
                <div class="stat-label">Total Slips</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $borrowed_total ?></div>
                <div class="stat-label">Borrowed Items</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $available_total ?></div>
                <div class="stat-label">Available Items</div>
            </div>
        </div>

        <!-- Slip Summary -->
        <div class="section">
            <h2>📝 Slip Summary</h2>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Slip No.</th>
                            <th>Returned/Total</th>
                            <th>Borrower</th>
                            <th>Office</th>
                            <th>Purpose</th>
                            <th>Date Borrowed</th>
                            <th>Due Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($slips)): ?>
                            <tr>
                                <td colspan="8" class="empty-state">
                                    <h3>No borrow slips found</h3>
                                    <p>Create your first borrow form to get started.</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($slips as $slip): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($slip['slip_no']) ?></strong></td>
                                    <td>
                                        <span class="status-badge <?= 
                                            $slip['returned_items'] == 0 ? 'status-pending' : 
                                            ($slip['returned_items'] == $slip['total_items'] ? 'status-complete' : 'status-partial') 
                                        ?>">
                                            <?= $slip['returned_items'] ?>/<?= $slip['total_items'] ?>
                                        </span>
                                    </td>
                                    <td><?= htmlspecialchars($slip['borrower']) ?></td>
                                    <td><?= htmlspecialchars($slip['office']) ?></td>
                                    <td><?= htmlspecialchars(substr($slip['purpose'], 0, 50)) . (strlen($slip['purpose']) > 50 ? '...' : '') ?></td>
                                    <td><?= htmlspecialchars($slip['date_borrowed']) ?></td>
                                    <td><?= htmlspecialchars($slip['due_date'] ?: 'N/A') ?></td>
                                    <td>
                                        <a href="#" onclick="loadReturnForm('<?= htmlspecialchars($slip['slip_no']) ?>')" class="btn-return">
                                            🔄 Return Equipment
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <?= renderPagination($slip_page, $slip_total_pages, 'slip_page', $_GET, $slip_total, $limit) ?>
        </div>

        <!-- Borrowed Equipment -->
        <div class="section">
            <h2>📦 Currently Borrowed Equipment</h2>
            
            <form method="get" class="filter-form">
                <!-- Preserve other section pagination -->
                <?php if (isset($_GET['available_page'])): ?><input type="hidden" name="available_page" value="<?= htmlspecialchars($_GET['available_page']) ?>"><?php endif; ?>
                <?php if (isset($_GET['slip_page'])): ?><input type="hidden" name="slip_page" value="<?= htmlspecialchars($_GET['slip_page']) ?>"><?php endif; ?>
                
                <div class="filter-group">
                    <label>Type:</label>
                    <input type="text" name="b_type" value="<?= htmlspecialchars($b_type) ?>" placeholder="Search equipment type">
                </div>
                <div class="filter-group">
                    <label>Date From:</label>
                    <input type="date" name="b_date_from" value="<?= htmlspecialchars($b_date_from) ?>">
                </div>
                <div class="filter-group">
                    <label>Date To:</label>
                    <input type="date" name="b_date_to" value="<?= htmlspecialchars($b_date_to) ?>">
                </div>
                <div class="filter-group">
                    <label>Office:</label>
                    <select name="b_office">
                        <option value="">--All--</option>
                        <?php foreach ($offices as $office): ?>
                            <option value="<?= htmlspecialchars($office) ?>" <?= $office == $b_office ? 'selected' : '' ?>>
                                <?= htmlspecialchars($office) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-buttons">
                    <button type="submit" class="btn-filter">🔍 Filter</button>
                    <a href="?" class="btn-clear">🗑️ Clear</a>
                </div>
            </form>
            
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Type</th>
                            <th>Brand</th>
                            <th>Model</th>
                            <th>Serial</th>
                            <th>Date Borrowed</th>
                            <th>Due Date</th>
                            <th>Purpose</th>
                            <th>Borrower</th>
                            <th>Office</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($borrowed)): ?>
                            <tr>
                                <td colspan="9" class="empty-state">
                                    <h3>No borrowed equipment found</h3>
                                    <p>All equipment is currently available for deployment.</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($borrowed as $item): ?>
                                <tr>
                                    <td><?= htmlspecialchars($item['type']) ?></td>
                                    <td><?= htmlspecialchars($item['brand']) ?></td>
                                    <td><?= htmlspecialchars($item['model']) ?></td>
                                    <td><?= htmlspecialchars($item['serial']) ?></td>
                                    <td><?= htmlspecialchars($item['date_borrowed']) ?></td>
                                    <td><?= htmlspecialchars($item['due_date'] ?: 'N/A') ?></td>
                                    <td><?= htmlspecialchars(substr($item['purpose'], 0, 30)) . (strlen($item['purpose']) > 30 ? '...' : '') ?></td>
                                    <td><?= htmlspecialchars($item['borrower']) ?></td>
                                    <td><?= htmlspecialchars($item['office']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Always show pagination info, even when no results -->
            <?= renderPagination($borrowed_page, $borrowed_total_pages, 'borrowed_page', $_GET, $borrowed_total, $limit) ?>
        </div>

        <!-- Available Equipment -->
        <div class="section">
            <h2>✅ Available Equipment</h2>
            
            <form method="get" class="filter-form">
                <!-- Preserve borrowed equipment filters and other pagination -->
                <?php if ($b_type): ?><input type="hidden" name="b_type" value="<?= htmlspecialchars($b_type) ?>"><?php endif; ?>
                <?php if ($b_date_from): ?><input type="hidden" name="b_date_from" value="<?= htmlspecialchars($b_date_from) ?>"><?php endif; ?>
                <?php if ($b_date_to): ?><input type="hidden" name="b_date_to" value="<?= htmlspecialchars($b_date_to) ?>"><?php endif; ?>
                <?php if ($b_office): ?><input type="hidden" name="b_office" value="<?= htmlspecialchars($b_office) ?>"><?php endif; ?>
                <?php if (isset($_GET['borrowed_page'])): ?><input type="hidden" name="borrowed_page" value="<?= htmlspecialchars($_GET['borrowed_page']) ?>"><?php endif; ?>
                <?php if (isset($_GET['slip_page'])): ?><input type="hidden" name="slip_page" value="<?= htmlspecialchars($_GET['slip_page']) ?>"><?php endif; ?>
                
                <div class="filter-group">
                    <label>Type:</label>
                    <input type="text" name="a_type" value="<?= htmlspecialchars($a_type) ?>" placeholder="Search equipment type">
                </div>
                <div class="filter-group">
                    <label>Brand:</label>
                    <input type="text" name="a_brand" value="<?= htmlspecialchars($a_brand) ?>" placeholder="Search brand">
                </div>
                <div class="filter-group">
                    <label>Model:</label>
                    <input type="text" name="a_model" value="<?= htmlspecialchars($a_model) ?>" placeholder="Search model">
                </div>
                <div class="filter-buttons">
                    <button type="submit" class="btn-filter">🔍 Filter</button>
                    <a href="?" class="btn-clear">🗑️ Clear</a>
                </div>
            </form>
            
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Type</th>
                            <th>Brand</th>
                            <th>Model</th>
                            <th>Locator</th>
                            <th>Serial</th>
                            <th>Description</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($available)): ?>
                            <tr>
                                <td colspan="6" class="empty-state">
                                    <h3>No available equipment found</h3>
                                    <p>Try adjusting your search filters.</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($available as $item): ?>
                                <tr>
                                    <td><?= htmlspecialchars($item['type']) ?></td>
                                    <td><?= htmlspecialchars($item['brand']) ?></td>
                                    <td><?= htmlspecialchars($item['model']) ?></td>
                                    <td><?= htmlspecialchars($item['locator']) ?></td>
                                    <td><?= htmlspecialchars($item['serial']) ?></td>
                                    <td><?= htmlspecialchars($item['description']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <?= renderPagination($available_page, $available_total_pages, 'available_page', $_GET, $available_total, $limit) ?>
        </div>
    </div>

    <!-- Return Equipment Modal -->
    <div id="returnModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000;">
        <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 30px; border-radius: 16px; max-width: 800px; width: 90%; max-height: 80vh; overflow-y: auto;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3 style="color: #1e293b; margin: 0;">Return Equipment</h3>
                <button onclick="closeReturnModal()" style="background: none; border: none; font-size: 24px; cursor: pointer; color: #64748b;">&times;</button>
            </div>
            <div id="returnFormContent">
                <!-- Return form will be loaded here -->
            </div>
        </div>
    </div>

    <script>
        function loadReturnForm(slipNo) {
            const modal = document.getElementById('returnModal');
            const content = document.getElementById('returnFormContent');
            
            // Show loading state
            content.innerHTML = '<div style="text-align: center; padding: 20px;">Loading...</div>';
            modal.style.display = 'block';
            
            // Fetch return form
            fetch(`get_borrowed_equipment.php?slip_no=${encodeURIComponent(slipNo)}`)
                .then(response => response.text())
                .then(html => {
                    content.innerHTML = html;
                })
                .catch(error => {
                    content.innerHTML = '<div style="color: red; text-align: center; padding: 20px;">Error loading return form. Please try again.</div>';
                });
        }
        
        function closeReturnModal() {
            document.getElementById('returnModal').style.display = 'none';
        }
        
        // Close modal when clicking outside
        document.getElementById('returnModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeReturnModal();
            }
        });
    </script>
</body>
</html>