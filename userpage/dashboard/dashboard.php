<?php
session_start();
if (!isset($_SESSION['user_id'])) {
  header("Location: ../login.php");
  exit();
}

$host = '127.0.0.1';
$db = 'database';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$pdo = new PDO($dsn, $user, $pass, [
  PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
  PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
]);

// Get user accounts
$acctIdsStmt = $pdo->prepare("SELECT id FROM ACCOUNTS WHERE user_id = ?");
$acctIdsStmt->execute([$_SESSION['user_id']]);
$accountIds = array_column($acctIdsStmt->fetchAll(), 'id');

// Categories
$categories = ['Food', 'Rent', 'Transport', 'Entertainment', 'Health', 'Savings', 'Car', 'Others', 'Uncategorized'];
$spendByCategory = array_fill_keys($categories, 0.0);

if (!empty($accountIds)) {
  $in = implode(',', array_fill(0, count($accountIds), '?'));
  $txStmt = $pdo->prepare("
        SELECT amount, description
        FROM TRANSACTIONS
        WHERE account_id IN ($in)
          AND transaction_type = 'withdrawal'
          AND status = 'completed'
    ");
  $txStmt->execute($accountIds);
  $txs = $txStmt->fetchAll();

  // Categorize based on description
  foreach ($txs as $tx) {
    $desc = strtolower($tx['description']);
    if (str_contains($desc, 'amazon') || str_contains($desc, 'starbucks') || str_contains($desc, 'food')) $cat = 'Food';
    elseif (str_contains($desc, 'rent') || str_contains($desc, 'apartment')) $cat = 'Rent';
    elseif (str_contains($desc, 'uber') || str_contains($desc, 'taxi') || str_contains($desc, 'bus')) $cat = 'Transport';
    elseif (str_contains($desc, 'netflix') || str_contains($desc, 'disney') || str_contains($desc, 'movie')) $cat = 'Entertainment';
    elseif (str_contains($desc, 'pharmacy') || str_contains($desc, 'doctor') || str_contains($desc, 'gym')) $cat = 'Health';
    elseif (str_contains($desc, 'savings') || str_contains($desc, 'deposit')) $cat = 'Savings';
    elseif (str_contains($desc, 'car') || str_contains($desc, 'fuel')) $cat = 'Car';
    elseif (!empty($desc)) $cat = 'Others';
    else $cat = 'Uncategorized';

    $spendByCategory[$cat] += (float)$tx['amount'];
  }
}

// Totals
$totalSpending = array_sum($spendByCategory);
$topCat = null;
$topAmt = -1;
foreach ($spendByCategory as $cat => $amt) {
  if ($amt > $topAmt) {
    $topAmt = $amt;
    $topCat = $cat;
  }
}

// Sample budgets
$budgetByCategory = [
  'Food' => 100,
  'Rent' => 1000,
  'Transport' => 50,
  'Entertainment' => 60,
  'Health' => 150,
  'Savings' => 300,
  'Car' => 80,
  'Others' => 120,
  'Uncategorized' => 0
];

// Over budget categories
$overBudget = [];
foreach ($spendByCategory as $cat => $amt) {
  $budget = $budgetByCategory[$cat] ?? 0;
  if ($amt > $budget) $overBudget[$cat] = $amt;
}

// Heart system
$heartsTotal = 10;
$heartsLost = count($overBudget);
$fullHearts = max(0, $heartsTotal - $heartsLost);
$emptyHearts = $heartsTotal - $fullHearts;

// Chart data
$labels = array_keys($spendByCategory);
$spendSeries = [];
foreach ($labels as $cat) $spendSeries[] = round($spendByCategory[$cat], 2);

// Account balance
$balanceStmt = $pdo->prepare("SELECT SUM(balance) FROM ACCOUNTS WHERE user_id = ?");
$balanceStmt->execute([$_SESSION['user_id']]);
$balance = (float)$balanceStmt->fetchColumn();

// Fetch notifications
$notifStmt = $pdo->prepare("SELECT * FROM NOTIFICATIONS WHERE user_id=? ORDER BY created_at DESC");
$notifStmt->execute([$_SESSION['user_id']]);
$notifications = $notifStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>User Dashboard</title>
  <link rel="stylesheet" href="style.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
  <style>
    body {
      font-family: sans-serif;
      background: #f5f5f5;
      margin: 0;
      padding: 0;
    }

    header {
      background: #1a365d;
      color: #fff;
      padding: 0.5rem 1rem;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }

    header nav a {
      color: #fff;
      text-decoration: none;
      padding: 0.25rem 0.5rem;
      background: #64b5f6;
      border: 2px solid #000;
    }

    .dashboard-container {
      padding: 1rem;
      max-width: 1000px;
      margin: 1rem auto;
      background: #fff;
      border: 3px solid #000;
    }

    .chart-card {
      max-width: 640px;
      margin: 1rem auto;
      padding: 1rem;
      border: 4px solid #000;
      background: #fff;
      box-shadow: 6px6px0 #000;
    }

    .stats-grid,
    .over-budget-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
      gap: 0.75rem;
      margin-top: 1rem;
    }

    .stat-box,
    .over-budget-box {
      padding: 0.75rem;
      border: 3px solid #000;
      box-shadow: 4px4px0 #000;
      color: #fff;
    }

    .stat-box {
      background: #64b5f6;
    }

    .over-budget-box {
      background: #f44336;
    }

    .notification {
      padding: 0.5rem;
      margin-bottom: 0.5rem;
      border: 2px solid #000;
      background: #e0f7fa;
    }
  </style>
</head>

<body>
  <header>
    <div class="logo">Reboot Banking</div>
    <nav><a href="../login/logout.php">Logout</a></nav>
  </header>

  <div class="dashboard-container">
    <h2>Account Balance</h2>
    <h3>$<?php echo number_format($balance, 2); ?></h3>
  </div>

  <div class="dashboard-container">
    <div class="dashboard-title">Dashboard</div>
    <div class="dashboard-content">
      <a style="text-decoration: none;" href="../budget/budget.php" class="card-link">
        <div class="card">
          <h3>Budgets</h3>
        </div>
      </a>
      <a style="text-decoration: none;" href="../transactions/transactions.php" class="card-link">
        <div class="card">
          <h3>Transactions</h3>
        </div>
      </a>
    </div>
  </div>

  <div class="dashboard-container">
    <h2>Spending by Category</h2>
    <div class="chart-card">
      <canvas id="spendRadar"></canvas>
    </div>

    <div class="stats-grid">
      <div class="stat-box">
        <h4>Top Category</h4>
        <p><?php echo htmlspecialchars($topCat); ?></p>
        <p>$<?php echo number_format($topAmt, 2); ?></p>
      </div>
      <div class="stat-box">
        <h4>Total Spending</h4>
        <p>$<?php echo number_format($totalSpending, 2); ?></p>
      </div>
    </div>

    <?php if (!empty($overBudget)): ?>
      <div class="over-budget-grid">
        <?php foreach ($overBudget as $cat => $amt): ?>
          <div class="over-budget-box">
            <h4><?php echo htmlspecialchars($cat); ?> Over Budget</h4>
            <p>Spent: $<?php echo number_format($amt, 2); ?></p>
            <p>Budget: $<?php echo number_format($budgetByCategory[$cat], 2); ?></p>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <div class="dashboard-container">
    <h2>Budget Health</h2>
    <div class="hearts-row" aria-label="Budget Health Hearts">
      <?php for ($i = 0; $i < $fullHearts; $i++): ?>
        <svg class="heart full" viewBox="0 0 32 32" width="24" height="24">
          <path d="M16 29s-9-6.2-13-11C-0.2 11.6 3 4 9 4c3 0 5 2 7 4 2-2 4-4 7-4 6 0 9.2 7.6 6 14-4 4.8-13 11-13 11z" />
        </svg>
      <?php endfor; ?>
      <?php for ($i = 0; $i < $emptyHearts; $i++): ?>
        <svg class="heart empty" viewBox="0 0 32 32" width="24" height="24">
          <path d="M16 29s-9-6.2-13-11C-0.2 11.6 3 4 9 4c3 0 5 2 7 4 2-2 4-4 7-4 6 0 9.2 7.6 6 14-4 4.8-13 11-13 11z" fill="none" stroke="#000" stroke-width="2" />
        </svg>
      <?php endfor; ?>
      <p>Hearts show your budget health</p>
    </div>
  </div>

  <div class="dashboard-container">
    <h2>suggestions</h2>
    <?php if (empty($notifications)): ?>
      <p>No suggestions yet.</p>
    <?php else: ?>
      <?php foreach ($notifications as $n): ?>
        <div class="notification">
          <?php echo htmlspecialchars($n['message']); ?>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <script>
    const radarLabels = <?php echo json_encode($labels, JSON_UNESCAPED_UNICODE); ?>;
    const spendData = <?php echo json_encode($spendSeries, JSON_UNESCAPED_UNICODE); ?>;
    const ctx = document.getElementById('spendRadar').getContext('2d');
    new Chart(ctx, {
      type: 'radar',
      data: {
        labels: radarLabels,
        datasets: [{
          label: 'Spending',
          data: spendData,
          fill: true,
          backgroundColor: 'rgba(100,181,246,0.25)',
          borderColor: '#000',
          borderWidth: 4,
          pointStyle: 'rect',
          pointRadius: 6,
          pointBackgroundColor: '#1a365d'
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            display: false
          }
        },
        scales: {
          r: {
            beginAtZero: true
          }
        }
      }
    });
  </script>
</body>

</html>