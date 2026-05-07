<?php
session_start();
require_once __DIR__ . '/../php/config.php';

// Check authentication
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit;
}

try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER, DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // Handle status update
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'mark_shipped') {
        $order_id = intval($_POST['order_id']);
        if ($order_id) {
            $stmt = $pdo->prepare("UPDATE orders SET status = 'SHIPPED' WHERE id = :id");
            $stmt->execute([':id' => $order_id]);
            header("Location: dashboard.php?msg=updated");
            exit;
        }
    }

    // Fetch Sales Stats
    $statsQuery = $pdo->query("SELECT COUNT(*) as total_books, SUM(amount) as total_revenue FROM orders");
    $stats = $statsQuery->fetch(PDO::FETCH_ASSOC);
    $total_books = $stats['total_books'] ?: 0;
    $total_revenue = $stats['total_revenue'] ?: 0.00;

    // Fetch Visitor Stats
    $today = date('Y-m-d');
    $visitsTodayQuery = $pdo->prepare("SELECT COUNT(*) FROM page_views WHERE visit_date = :today");
    $visitsTodayQuery->execute([':today' => $today]);
    $visits_today = $visitsTodayQuery->fetchColumn();

    $totalVisitsQuery = $pdo->query("SELECT COUNT(*) FROM page_views");
    $total_visits = $totalVisitsQuery->fetchColumn();

    $countriesQuery = $pdo->query("SELECT country, COUNT(*) as count FROM page_views GROUP BY country ORDER BY count DESC LIMIT 10");
    $countries = $countriesQuery->fetchAll(PDO::FETCH_ASSOC);

    // Fetch all orders
    $ordersQuery = $pdo->query("SELECT * FROM orders ORDER BY created_at DESC");
    $orders = $ordersQuery->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Dashboard — Not Nothing</title>
  <link rel="stylesheet" href="../css/admin.css">
  <style>
    .stats-grid.four-cols { grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); }
    .analytics-panel { margin-top: 40px; margin-bottom: 40px; }
    .country-list { list-style: none; padding: 0; margin: 0; }
    .country-item {
        display: flex; justify-content: space-between; align-items: center;
        padding: 12px 16px; border-bottom: 1px solid rgba(255,255,255,0.05);
    }
    .country-item:last-child { border-bottom: none; }
    .country-count { background: rgba(201,168,76,0.1); color: var(--gold); padding: 4px 10px; border-radius: 20px; font-size: 0.85rem; font-weight: 600; }
    .panels-wrapper { display: grid; grid-template-columns: 2fr 1fr; gap: 30px; }
    @media (max-width: 900px) { .panels-wrapper { grid-template-columns: 1fr; } }
  </style>
</head>
<body>

  <nav class="admin-nav">
    <div class="nav-brand">
      <h2>Not Nothing <span class="text-gold">Admin</span></h2>
    </div>
    <div class="nav-actions">
      <span class="text-muted">Logged in as <strong><?= htmlspecialchars($_SESSION['admin_username']) ?></strong></span>
      <a href="../" class="btn-outline" target="_blank">View Site</a>
      <a href="logout.php" class="btn-outline">Logout</a>
    </div>
  </nav>

  <div class="dashboard-container">
    
    <?php if(isset($_GET['msg']) && $_GET['msg'] === 'updated'): ?>
      <div style="background: rgba(46, 204, 113, 0.1); color: #2ecc71; padding: 12px 20px; border-radius: 8px; margin-bottom: 20px; border: 1px solid rgba(46, 204, 113, 0.2);">
        Order status updated successfully!
      </div>
    <?php endif; ?>

    <!-- STATS ROW -->
    <div class="stats-grid four-cols">
      <div class="stat-card">
        <div class="stat-title">Visits Today</div>
        <div class="stat-value text-gold"><?= number_format($visits_today) ?></div>
      </div>
      <div class="stat-card">
        <div class="stat-title">Total Visits</div>
        <div class="stat-value"><?= number_format($total_visits) ?></div>
      </div>
      <div class="stat-card">
        <div class="stat-title">Books Sold</div>
        <div class="stat-value"><?= number_format($total_books) ?></div>
      </div>
      <div class="stat-card">
        <div class="stat-title">Total Revenue</div>
        <div class="stat-value" style="color:#2ecc71">$<?= number_format($total_revenue, 2) ?></div>
      </div>
    </div>

    <!-- MAIN PANELS -->
    <div class="panels-wrapper">
      
      <!-- ORDERS TABLE -->
      <div class="table-container">
        <div class="table-header">
          <h3>Recent Orders</h3>
        </div>
        <table>
          <thead>
            <tr>
              <th>Date</th>
              <th>Buyer</th>
              <th>Format</th>
              <th>Amount</th>
              <th>Status</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <?php if(empty($orders)): ?>
              <tr><td colspan="6" style="text-align:center; padding: 40px; color: var(--text-muted);">No orders found yet. Sales will appear here.</td></tr>
            <?php else: ?>
              <?php foreach ($orders as $order): ?>
                <tr>
                  <td><?= date('M j, Y g:i A', strtotime($order['created_at'])) ?></td>
                  <td><strong><?= htmlspecialchars($order['payer_name']) ?></strong><br><span style="font-size:0.8rem;color:var(--text-muted)"><?= htmlspecialchars($order['buyer_email'] ?: $order['payer_email']) ?></span></td>
                  <td>
                    <span class="badge badge-<?= strtolower($order['format']) ?>">
                      <?= htmlspecialchars(ucfirst($order['format'])) ?>
                    </span>
                  </td>
                  <td>$<?= number_format($order['amount'], 2) ?></td>
                  <td>
                    <?php if ($order['status'] === 'SHIPPED'): ?>
                      <span class="status-badge status-shipped">Shipped</span>
                    <?php else: ?>
                      <span class="status-badge status-completed">Completed</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <?php if ($order['format'] !== 'ebook' && $order['status'] !== 'SHIPPED'): ?>
                      <form method="POST" style="display:inline;" onsubmit="return confirm('Mark this order as shipped?');">
                        <input type="hidden" name="action" value="mark_shipped">
                        <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                        <button type="submit" class="action-btn">Mark Shipped</button>
                      </form>
                    <?php elseif ($order['format'] === 'ebook'): ?>
                      <span class="text-muted" style="font-size:0.8rem">Auto-Delivered</span>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <!-- COUNTRIES PANEL -->
      <div class="table-container">
        <div class="table-header">
          <h3>Top Countries</h3>
        </div>
        <ul class="country-list">
          <?php if(empty($countries)): ?>
            <li class="country-item text-muted">No visitor data yet.</li>
          <?php else: ?>
            <?php foreach ($countries as $c): ?>
              <li class="country-item">
                <span><?= htmlspecialchars($c['country']) ?></span>
                <span class="country-count"><?= number_format($c['count']) ?> visits</span>
              </li>
            <?php endforeach; ?>
          <?php endif; ?>
        </ul>
      </div>

    </div>

  </div>

</body>
</html>
