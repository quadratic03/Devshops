<?php
// Only start the session if one doesn't already exist
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../includes/functions.php';

// Check if user is admin
if (!is_admin()) {
    // Redirect to login page
    header('Location: ../login.php');
    exit;
}

// Get filter parameters
$period = isset($_GET['period']) ? $_GET['period'] : 'all';
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';

// Query base
$report_query_base = "SELECT 
    p.id AS product_id,
    p.name AS product_title,
    c.name AS category_name,
    u.username AS seller_name,
    COUNT(t.id) AS total_sales,
    SUM(t.amount) AS total_revenue
FROM 
    products p
JOIN 
    categories c ON p.category_id = c.id
JOIN 
    users u ON p.seller_id = u.id
LEFT JOIN 
    transactions t ON p.id = t.product_id AND t.status = 'completed'";

// Add filters based on period
$where_clause = " WHERE 1=1";

if ($period == 'today') {
    $where_clause .= " AND DATE(t.created_at) = CURDATE()";
} elseif ($period == 'week') {
    $where_clause .= " AND WEEK(t.created_at) = WEEK(CURDATE()) AND YEAR(t.created_at) = YEAR(CURDATE())";
} elseif ($period == 'month') {
    $where_clause .= " AND MONTH(t.created_at) = MONTH(CURDATE()) AND YEAR(t.created_at) = YEAR(CURDATE())";
} elseif ($period == 'year') {
    $where_clause .= " AND YEAR(t.created_at) = YEAR(CURDATE())";
} elseif ($period == 'custom' && !empty($start_date) && !empty($end_date)) {
    $where_clause .= " AND DATE(t.created_at) BETWEEN '$start_date' AND '$end_date'";
}

// Complete the query
$report_query = $report_query_base . $where_clause . " GROUP BY p.id ORDER BY total_revenue DESC";

// Execute the query
$report_result = $conn->query($report_query);

// Get summary data
$summary_query = "SELECT 
    COUNT(DISTINCT t.id) AS transaction_count,
    COUNT(DISTINCT t.buyer_id) AS buyer_count,
    SUM(t.amount) AS total_sales
FROM 
    transactions t
WHERE 
    t.status = 'completed'";

// Add period filters to summary
if ($period == 'today') {
    $summary_query .= " AND DATE(t.created_at) = CURDATE()";
} elseif ($period == 'week') {
    $summary_query .= " AND WEEK(t.created_at) = WEEK(CURDATE()) AND YEAR(t.created_at) = YEAR(CURDATE())";
} elseif ($period == 'month') {
    $summary_query .= " AND MONTH(t.created_at) = MONTH(CURDATE()) AND YEAR(t.created_at) = YEAR(CURDATE())";
} elseif ($period == 'year') {
    $summary_query .= " AND YEAR(t.created_at) = YEAR(CURDATE())";
} elseif ($period == 'custom' && !empty($start_date) && !empty($end_date)) {
    $summary_query .= " AND DATE(t.created_at) BETWEEN '$start_date' AND '$end_date'";
}

$summary_result = $conn->query($summary_query)->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales Reports - DevMarket Philippines</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <?php include 'includes/sidebar.php'; ?>
            
            <!-- Main Content -->
            <div class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Sales Reports</h1>
                    <div class="btn-toolbar mb-2 mb-md-0 no-print">
                        <div class="btn-group me-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="window.print()">
                                <i class="fas fa-print me-2"></i> Print Report
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="exportToCsv()">
                                <i class="fas fa-download me-2"></i> Export CSV
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Filter Options -->
                <div class="card mb-4 no-print">
                    <div class="card-header bg-dark text-white">
                        <h5 class="mb-0">Filter Options</h5>
                    </div>
                    <div class="card-body">
                        <form action="reports.php" method="GET" class="row g-3">
                            <div class="col-md-4">
                                <label for="period" class="form-label">Time Period</label>
                                <select class="form-select" id="period" name="period" onchange="toggleCustomDateFields()">
                                    <option value="all" <?php echo $period == 'all' ? 'selected' : ''; ?>>All Time</option>
                                    <option value="today" <?php echo $period == 'today' ? 'selected' : ''; ?>>Today</option>
                                    <option value="week" <?php echo $period == 'week' ? 'selected' : ''; ?>>This Week</option>
                                    <option value="month" <?php echo $period == 'month' ? 'selected' : ''; ?>>This Month</option>
                                    <option value="year" <?php echo $period == 'year' ? 'selected' : ''; ?>>This Year</option>
                                    <option value="custom" <?php echo $period == 'custom' ? 'selected' : ''; ?>>Custom Range</option>
                                </select>
                            </div>
                            
                            <div class="col-md-4 custom-date" <?php echo $period != 'custom' ? 'style="display: none;"' : ''; ?>>
                                <label for="start_date" class="form-label">Start Date</label>
                                <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo $start_date; ?>">
                            </div>
                            
                            <div class="col-md-4 custom-date" <?php echo $period != 'custom' ? 'style="display: none;"' : ''; ?>>
                                <label for="end_date" class="form-label">End Date</label>
                                <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo $end_date; ?>">
                            </div>
                            
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-filter me-2"></i> Apply Filters
                                </button>
                                <a href="reports.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-undo me-2"></i> Reset
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Summary Cards -->
                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="card dashboard-card bg-primary text-white">
                            <div class="card-body">
                                <h5 class="card-title">Total Transactions</h5>
                                <p class="card-text fs-1"><?php echo $summary_result['transaction_count'] ?? 0; ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card dashboard-card bg-success text-white">
                            <div class="card-body">
                                <h5 class="card-title">Unique Buyers</h5>
                                <p class="card-text fs-1"><?php echo $summary_result['buyer_count'] ?? 0; ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card dashboard-card bg-info text-white">
                            <div class="card-body">
                                <h5 class="card-title">Total Revenue</h5>
                                <p class="card-text fs-1"><?php echo format_currency($summary_result['total_sales'] ?? 0); ?></p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Report Table -->
                <div class="card mb-4">
                    <div class="card-header bg-dark text-white">
                        <h5 class="mb-0">Sales Report <?php echo getPeriodTitle($period, $start_date, $end_date); ?></h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover" id="reportTable">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Product</th>
                                        <th>Category</th>
                                        <th>Seller</th>
                                        <th>Sales Count</th>
                                        <th>Revenue</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($report_result && $report_result->num_rows > 0): ?>
                                        <?php while ($row = $report_result->fetch_assoc()): ?>
                                            <tr>
                                                <td><?php echo $row['product_id']; ?></td>
                                                <td><?php echo $row['product_title']; ?></td>
                                                <td><?php echo $row['category_name']; ?></td>
                                                <td><?php echo $row['seller_name']; ?></td>
                                                <td><?php echo $row['total_sales']; ?></td>
                                                <td><?php echo format_currency($row['total_revenue'] ?? 0); ?></td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="6" class="text-center">No data available for the selected period.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                
                <!-- Generated Date -->
                <div class="text-muted text-end">
                    <p>Report generated on: <?php echo date('F d, Y h:i A'); ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Custom JavaScript -->
    <script src="../assets/js/script.js"></script>
    <script>
        function toggleCustomDateFields() {
            const periodSelect = document.getElementById('period');
            const customDateFields = document.querySelectorAll('.custom-date');
            
            if (periodSelect.value === 'custom') {
                customDateFields.forEach(field => field.style.display = 'block');
            } else {
                customDateFields.forEach(field => field.style.display = 'none');
            }
        }
        
        function exportToCsv() {
            const table = document.getElementById('reportTable');
            let csv = [];
            const rows = table.querySelectorAll('tr');
            
            for (let i = 0; i < rows.length; i++) {
                const row = [], cols = rows[i].querySelectorAll('td, th');
                
                for (let j = 0; j < cols.length; j++) {
                    // Replace HTML entities and comma with a space to avoid CSV issues
                    let data = cols[j].innerText.replace(/,/g, ' ');
                    row.push('"' + data + '"');
                }
                
                csv.push(row.join(','));
            }
            
            const csvContent = 'data:text/csv;charset=utf-8,' + csv.join('\n');
            const encodedUri = encodeURI(csvContent);
            const link = document.createElement('a');
            link.setAttribute('href', encodedUri);
            link.setAttribute('download', 'sales_report_<?php echo date('Y-m-d'); ?>.csv');
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
    </script>
</body>
</html>

<?php
// Helper function to get period title
function getPeriodTitle($period, $start_date, $end_date) {
    switch ($period) {
        case 'today':
            return 'for Today (' . date('F d, Y') . ')';
        case 'week':
            return 'for This Week';
        case 'month':
            return 'for ' . date('F Y');
        case 'year':
            return 'for ' . date('Y');
        case 'custom':
            return "from $start_date to $end_date";
        default:
            return 'for All Time';
    }
}
?> 