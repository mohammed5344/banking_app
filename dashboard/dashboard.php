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
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
  ]);
} catch (PDOException $e) {
  die("DB Connection failed: " . $e->getMessage());
}

// Get user balance
$stmt = $pdo->prepare("SELECT balance FROM ACCOUNTS WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$account = $stmt->fetch(PDO::FETCH_ASSOC);
$balance = $account ? $account['balance'] : 0;

$stmt = $pdo->prepare("SELECT balance FROM ACCOUNTS WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$balance = $stmt->fetchColumn();
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="style.css">
  <title>Dashboard</title>
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

  <!-- Balance Container -->
  <div class="dashboard-container">
    <div class="dashboard-title">Account Balance</div>
    <div class="dashboard-content">
      <div class="balance-container">
        <h3>Account Balance: <?php echo $balance; ?></h3>

        <p class="balance-amount">$<?php echo number_format($balance, 2); ?></p>
      </div>
    </div>
  </div>

  <!-- Other Dashboard Cards -->
  <div class="dashboard-container">
    <div class="dashboard-title">Dashboard</div>
    <div class="dashboard-content">
      <a style="text-decoration: none;" href="../budget/budget.php" class="card-link">
        <div class="card">
          <h3>Budgets</h3>
        </div>
      </a>
      <a style="text-decoration: none;" href="notifications.html" class="card-link">
        <div class="card">
          <h3>Notifications</h3>
        </div>
      </a>
    </div>
  </div>
</body>

</html>