<?php
session_start();

if (!isset($_SESSION['user_id'])) {
  header("Location: ../login.php");
  exit();
}

$host = '127.0.0.1';
$db   = 'database';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
try {
  $pdo = new PDO($dsn, $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  ]);
} catch (PDOException $e) {
  die("DB Connection failed: " . $e->getMessage());
}

$stmt = $pdo->prepare("SELECT balance FROM ACCOUNTS WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$balance = $stmt->fetchColumn();
if ($balance === false) $balance = 0.0;

$acctIdsStmt = $pdo->prepare("SELECT id FROM ACCOUNTS WHERE user_id = ?");
$acctIdsStmt->execute([$_SESSION['user_id']]);
$accountIds = array_column($acctIdsStmt->fetchAll(), 'id');

$categories = ['Food', 'Rent', 'Transport', 'Entertainment', 'Health', 'Savings', 'Car', 'Others', 'Uncategorized'];
$spendByCategory = array_fill_keys($categories, 0.0);

if (!empty($accountIds)) {
  $in = implode(',', array_fill(0, count($accountIds), '?'));
  $txStmt = $pdo->prepare("
    SELECT amount, category
    FROM TRANSACTIONS
    WHERE account_id IN ($in)
      AND transaction_type = 'withdrawal'
      AND status = 'completed'
  ");
  $txStmt->execute($accountIds);
  $txs = $txStmt->fetchAll();

  foreach ($txs as $tx) {
    $cat = $tx['category'] ?? 'Uncategorized';
    if (!in_array($cat, $categories, true)) $cat = 'Uncategorized';
    $spendByCategory[$cat] += (float)$tx['amount'];
  }
}

$totalSpending = array_sum($spendByCategory);
$topCat = null;
$topAmt = -1;
foreach ($spendByCategory as $cat => $amt) {
  if ($amt > $topAmt) {
    $topAmt = $amt;
    $topCat = $cat;
  }
}

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

$overBudget = [];
foreach ($spendByCategory as $cat => $amt) {
  $budget = $budgetByCategory[$cat] ?? 0;
  if ($amt > $budget) {
    $overBudget[$cat] = $amt;
  }
}

$labels = array_keys($spendByCategory);
$spendSeries  = [];
foreach ($labels as $cat) {
  $spendSeries[]  = round($spendByCategory[$cat] ?? 0, 2);
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <link rel="stylesheet" href="style.css" />
  <link rel="icon" href="../assets/logo.jpg">

  <title>Dashboard</title>
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
  <style>
    .chart-card {
      max-width: 640px;
      margin: 0 auto;
      padding: 1rem;
      border: 4px solid #000;
      box-shadow: 6px 6px 0 #000;
      background: #fff;
    }

    .chart-wrap {
      position: relative;
      width: 100%;
      height: 380px;
    }

    #spendRadar {
      image-rendering: pixelated;
      image-rendering: crisp-edges;
    }

    .stats-grid {
      margin-top: 1rem;
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
      gap: 0.75rem;
    }

    .stat-box {
      background: #64b5f6;
      color: white;
      border: 3px solid #000;
      box-shadow: 4px 4px 0 #000;
      padding: 0.75rem;
    }

    .over-budget-grid {
      margin-top: 1rem;
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
      gap: 0.75rem;
    }

    .over-budget-box {
      background: #f44336;
      color: white;
      border: 3px solid #000;
      box-shadow: 4px 4px 0 #000;
      padding: 0.75rem;
    }
  </style>
</head>

<body>
  <header>
    <div class="logo">Reboot Banking</div>
    <nav>
      <ul>
        <li><a href="../dashboard/dashboard.php">Dashboard</a></li>
        <li><a href="../login/logout.php">Logout</a></li>
      </ul>
    </nav>
  </header>

  <div class="dashboard-container">
    <div class="dashboard-title">Account Balance</div>
    <div class="dashboard-content">
      <div class="balance-container">
        <h3>Account Balance: <?php echo htmlspecialchars(number_format((float)$balance, 2)); ?></h3>
        <p class="balance-amount">$<?php echo htmlspecialchars(number_format((float)$balance, 2)); ?></p>
      </div>
    </div>
  </div>

  <!-- Other Dashboard -->
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
    <div class="dashboard-title">Spending by Category</div>
    <div class="dashboard-content">
      <div class="chart-card">
        <div class="chart-wrap">
          <canvas id="spendRadar"></canvas>
        </div>
      </div>

      <div class="stats-grid">
        <div class="stat-box">
          <h4>Top Spending Category</h4>
          <p><strong><?php echo htmlspecialchars($topCat ?? '—'); ?></strong></p>
          <p>$<?php echo number_format((float)$topAmt, 2); ?></p>
        </div>
        <div class="stat-box">
          <h4>Total Spending</h4>
          <p>$<?php echo number_format((float)$totalSpending, 2); ?></p>
        </div>
      </div>

      <?php if (!empty($overBudget)): ?>
        <div class="over-budget-grid">
          <?php foreach ($overBudget as $cat => $amt): ?>
            <div class="over-budget-box">
              <h4><?php echo htmlspecialchars($cat); ?> Over Budget</h4>
              <p>Spent: $<?php echo number_format((float)$amt, 2); ?></p>
              <p>Budget: $<?php echo number_format((float)$budgetByCategory[$cat], 2); ?></p>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

    </div>
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
        animation: false,
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