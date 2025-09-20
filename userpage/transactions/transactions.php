<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login/login.php");
    exit();
}

$host = '127.0.0.1';
$db   = 'database';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    die("DB Connection failed: " . $e->getMessage());
}

$user_id = $_SESSION['user_id'];

$stmt = $pdo->prepare("SELECT id FROM ACCOUNTS WHERE user_id = ?");
$stmt->execute([$user_id]);
$accountIds = array_column($stmt->fetchAll(), 'id');

$transactions = [];
if (!empty($accountIds)) {
    $in = implode(',', array_fill(0, count($accountIds), '?'));
    $txStmt = $pdo->prepare("
        SELECT id, transaction_type, amount, description, created_at 
        FROM TRANSACTIONS 
        WHERE account_id IN ($in)
        ORDER BY created_at DESC
    ");
    $txStmt->execute($accountIds);
    $transactions = $txStmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Your Transactions</title>
    <link rel="stylesheet" href="../dashboard/style.css">
    <style>
        .transaction-card {
            padding: 1rem;
            margin: 0.5rem 0;
            border: 3px solid var(--border-dark);
            box-shadow: 4px 4px 0 var(--border-dark);
            background: #fefefe;
        }

        .transaction-card.green {
            background: #b2f2bb;
        }

        /* received */
        .transaction-card.red {
            background: #ffa8a8;
        }

        /* sent */
        .transaction-amount {
            font-size: 1.2rem;
            font-weight: bold;
        }

        .transaction-desc {
            margin-top: 0.5rem;
            font-size: 0.9rem;
        }

        .transaction-date {
            margin-top: 0.25rem;
            font-size: 0.75rem;
            color: #333;
        }
    </style>
</head>

<body>
    <header>
        <div class="logo">Reboozt Banking</div>
        <nav>
            <ul>
                <li><a href="../dashboard/dashboard.php">Dashboard</a></li>
                <li><a href="../login/logout.php">Logout</a></li>
            </ul>
        </nav>
    </header>

    <div class="dashboard-container">
        <div class="dashboard-title">Transactions</div>
        <div class="dashboard-content">
            <?php if (empty($transactions)): ?>
                <p>No transactions found.</p>
            <?php else: ?>
                <?php foreach ($transactions as $tx): ?>
                    <?php
                    $isReceived = stripos($tx['description'], 'received') !== false;

                    $cardClass = $isReceived ? 'green' : 'red';
                    $amountSign = $isReceived ? '+' : '-';
                    ?>
                    <div class="transaction-card <?php echo $cardClass; ?>">
                        <div class="transaction-amount">
                            <?php echo $amountSign; ?>$<?php echo number_format((float)$tx['amount'], 2); ?>
                        </div>
                        <div class="transaction-desc">
                            <?php echo htmlspecialchars($tx['description']); ?>
                        </div>
                        <div class="transaction-date">
                            <?php echo date("M d, Y H:i", strtotime($tx['created_at'])); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</body>

</html>