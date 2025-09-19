<?php
session_start();

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
  header("Location: ../login.php");
  exit();
}

// DB connection
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

// -------------------- BALANCE --------------------
$stmt = $pdo->prepare("SELECT balance FROM ACCOUNTS WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$balance = $stmt->fetchColumn();
if ($balance === false) $balance = 0.0;

// -------------------- BUDGET DATA --------------------
$budgetsStmt = $pdo->prepare("
  SELECT category, item, amount
  FROM BUDGETS
  WHERE user_id = :uid
");
$budgetsStmt->execute(['uid' => $_SESSION['user_id']]);
$budgetRows = $budgetsStmt->fetchAll();

$budgetByCategory = [];
$categoryItems = [];
foreach ($budgetRows as $row) {
  $cat = $row['category'];
  $item = $row['item'];
  $amt = (float)$row['amount'];

  if (!isset($budgetByCategory[$cat])) $budgetByCategory[$cat] = 0.0;
  $budgetByCategory[$cat] += $amt;

  if (!isset($categoryItems[$cat])) $categoryItems[$cat] = [];
  if ($item && !in_array($item, $categoryItems[$cat], true)) {
    $categoryItems[$cat][] = $item;
  }
}

if (empty($budgetByCategory)) {
  $budgetByCategory = [
    'Food' => 0, 'Rent' => 0, 'Transport' => 0, 'Entertainment' => 0,
    'Health' => 0, 'Savings' => 0, 'Car' => 0, 'Others' => 0
  ];
  $categoryItems = [
    'Food' => [], 'Rent' => [], 'Transport' => [], 'Entertainment' => [],
    'Health' => [], 'Savings' => [], 'Car' => [], 'Others' => []
  ];
}

// -------------------- SPENDING DATA --------------------
$acctIdsStmt = $pdo->prepare("SELECT id FROM ACCOUNTS WHERE user_id = ?");
$acctIdsStmt->execute([$_SESSION['user_id']]);
$accountIds = array_column($acctIdsStmt->fetchAll(), 'id');

$spendByCategory = array_fill_keys(array_keys($budgetByCategory), 0.0);
$uncategorized = 0.0;

if (!empty($accountIds)) {
  $in = implode(',', array_fill(0, count($accountIds), '?'));
  $txStmt = $pdo->prepare("
    SELECT description, amount
    FROM TRANSACTIONS
    WHERE account_id IN ($in)
      AND transaction_type = 'withdrawal'
      AND status = 'completed'
  ");
  $txStmt->execute($accountIds);
  $txs = $txStmt->fetchAll();

  $needles = [];
  foreach ($budgetByCategory as $cat => $_) {
    $needles[$cat] = [mb_strtolower($cat)];
    foreach ($categoryItems[$cat] as $it) {
      if ($it) $needles[$cat][] = mb_strtolower($it);
    }
  }

  foreach ($txs as $tx) {
    $desc = mb_strtolower((string)$tx['description']);
    $amt  = (float)$tx['amount'];

    $matchedCat = null;
    foreach ($needles as $cat => $words) {
      foreach ($words as $needle) {
        if ($needle !== '' && mb_strpos($desc, $needle) !== false) {
          $matchedCat = $cat;
          break 2;
        }
      }
    }

    if ($matchedCat) {
      $spendByCategory[$matchedCat] += $amt;
    } else {
      $uncategorized += $amt;
    }
  }
}

if ($uncategorized > 0) {
  $budgetByCategory['Uncategorized'] = 0.0;
  $spendByCategory['Uncategorized'] = $uncategorized;
}

// -------------------- STATS --------------------
$totalBudget   = array_sum($budgetByCategory);
$totalSpending = array_sum($spendByCategory);

$topCat = null;
$topAmt = -1;
foreach ($spendByCategory as $cat => $amt) {
  if ($amt > $topAmt) {
    $topAmt = $amt;
    $topCat = $cat;
  }
}

$overBudget = [];
foreach ($budgetByCategory as $cat => $bAmt) {
  $sAmt = $spendByCategory[$cat] ?? 0.0;
  if ($sAmt > $bAmt) {
    $overBudget[] = [
      'category' => $cat,
      'budget'   => $bAmt,
      'spending' => $sAmt,
      'diff'     => $sAmt - $bAmt
    ];
  }
}

$labels = array_keys($budgetByCategory);
$spendSeries  = [];
foreach ($labels as $cat) {
  $spendSeries[]  = round($spendByCategory[$cat] ?? 0, 2);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <link rel="stylesheet" href="style.css" />
  <title>Dashboard</title>
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
  <style>
    .chart-card {
      max-width: 600px;   /* bigger than before */
      margin: 0 auto;
    }
    .chart-wrap {
      position: relative;
      width: 100%;
      height: 360px;      /* taller chart */
    }
    .stats-grid {
      margin-top: 1rem;
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
      gap: 0.75rem;
    }
    .stat-box {
      background: var(--accent);
      color: white;
      border: 3px solid var(--border-dark);
      box-shadow: 4px 4px 0 var(--border-dark);
      padding: 0.75rem;
    }
    .over-budget {
      margin-top: 0.5rem;
      background: #ffecec;
      border: 3px solid var(--border-dark);
      box-shadow: 4px 4px 0 var(--border-dark);
      padding: 0.75rem;
    }
    .over-budget-list { margin-top: 0.5rem; padding-left: 1.2rem; }
    .over-pill {
      display: inline-block;
      padding: 2px 6px;
      border: 2px solid var(--border-dark);
      background: #ff9da1;
      margin-left: 6px;
    }
  </style>
</head>
<body>
  <header>
    <div class="logo">Reboot Banking</div>
    <nav>
      <ul>
        <li><a href="#">About Us</a></li>
        <li><a href="#">Support</a></li>
        <li><a href="#">Dashboard</a></li>
      </ul>
    </nav>
  </header>

  <!-- Balance -->
  <div class="dashboard-container">
    <div class="dashboard-title">Account Balance</div>
    <div class="dashboard-content">
      <div class="balance-container">
        <h3>Account Balance: <?php echo htmlspecialchars(number_format((float)$balance, 2)); ?></h3>
        <p class="balance-amount">$<?php echo htmlspecialchars(number_format((float)$balance, 2)); ?></p>
      </div>
    </div>
  </div>

  <!-- Radar Chart -->
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
        <div class="stat-box">
          <h4>Total Budget</h4>
          <p>$<?php echo number_format((float)$totalBudget, 2); ?></p>
        </div>
      </div>

      <?php if (!empty($overBudget)): ?>
        <div class="over-budget">
          <h4>Over Budget</h4>
          <ul class="over-budget-list">
            <?php foreach ($overBudget as $ob): ?>
              <li>
                <strong><?php echo htmlspecialchars($ob['category']); ?></strong>:
                spent $<?php echo number_format((float)$ob['spending'], 2); ?> /
                budget $<?php echo number_format((float)$ob['budget'], 2); ?>
                <span class="over-pill">+$<?php echo number_format((float)$ob['diff'], 2); ?></span>
              </li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php else: ?>
        <p class="ok-budget">✅ No categories over budget.</p>
      <?php endif; ?>
    </div>
  </div>

  <!-- Other Dashboard -->
  <div class="dashboard-container">
    <div class="dashboard-title">Dashboard</div>
    <div class="dashboard-content">
      <a style="text-decoration: none;" href="../budget/budget.php" class="card-link">
        <div class="card"><h3>Budgets</h3></div>
      </a>
      <a style="text-decoration: none;" href="notifications.html" class="card-link">
        <div class="card"><h3>Notifications</h3></div>
      </a>
    </div>
  </div>

  <script>
    const radarLabels = <?php echo json_encode($labels, JSON_UNESCAPED_UNICODE); ?>;
    const spendData   = <?php echo json_encode($spendSeries, JSON_UNESCAPED_UNICODE); ?>;

    const ctx = document.getElementById('spendRadar').getContext('2d');
    new Chart(ctx, {
      type: 'radar',
      data: {
        labels: radarLabels,
        datasets: [
          {
            label: 'Spending',
            data: spendData,
            fill: true
          }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        animation: false,
        plugins: {
          legend: { display: false } // hide legend completely
        },
        scales: {
          r: { beginAtZero: true, ticks: { showLabelBackdrop: false } }
        }
      }
    });
  </script>
</body>
</html>
