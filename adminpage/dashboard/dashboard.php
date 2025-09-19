<?php
session_start();

// --- Admin check ---
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || $_SESSION['is_admin'] != 1) {
    header("Location: ../userpage/login/login.php");
    exit();
}

// --- DB Connection ---
$host = '127.0.0.1';
$db   = 'database';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';
$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];
try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    die("DB Connection failed: " . $e->getMessage());
}

// --- Categories & Keywords ---
$categories = [
    'Food' => ['amazon', 'starbucks', 'mcdonalds', 'burgerking', 'kfc', 'subway', 'grocery'],
    'Transport' => ['uber', 'taxi', 'metro', 'bus', 'train', 'fuel', 'gas'],
    'Health' => ['gym', 'clinic', 'pharmacy', 'hospital'],
    'Entertainment' => ['netflix', 'spotify', 'cinema', 'youtube', 'hulu', 'disney']
];

// --- Fetch all withdrawal transactions ---
$stmt = $pdo->query("
    SELECT account_id, amount, description
    FROM TRANSACTIONS
    WHERE transaction_type='withdrawal'
");
$transactions = $stmt->fetchAll();

// --- Map transactions ---
$topSpending = [];
$userCategorySpending = [];
foreach ($categories as $cat => $keywords) {
    $topSpending[$cat] = [];
    $userCategorySpending[$cat] = [];
}

foreach ($transactions as $trans) {
    $desc = strtolower(trim($trans['description']));
    $amount = $trans['amount'];
    $accountId = $trans['account_id'];

    foreach ($categories as $cat => $keywords) {
        foreach ($keywords as $kw) {
            if (strpos($desc, $kw) !== false) {
                if (!isset($topSpending[$cat][$desc])) $topSpending[$cat][$desc] = 0;
                $topSpending[$cat][$desc] += $amount;

                if (!isset($userCategorySpending[$cat][$accountId])) $userCategorySpending[$cat][$accountId] = 0;
                $userCategorySpending[$cat][$accountId] += $amount;

                break 2;
            }
        }
    }
}

// --- Sort top 3 stores ---
foreach ($topSpending as $cat => $stores) {
    arsort($stores);
    $topSpending[$cat] = array_slice($stores, 0, 3, true);
}

// --- Fetch budgets ---
$stmt = $pdo->query("SELECT * FROM BUDGETS");
$budgets = $stmt->fetchAll();
$userBudgets = [];
foreach ($budgets as $b) $userBudgets[$b['user_id']][$b['category']] = $b['amount'];

// --- Determine users over budget ---
$usersOverBudget = [];
foreach ($userCategorySpending as $cat => $users) {
    foreach ($users as $accountId => $spent) {
        $stmt = $pdo->prepare("SELECT user_id FROM ACCOUNTS WHERE id=?");
        $stmt->execute([$accountId]);
        $user = $stmt->fetch();
        if (!$user) continue;
        $userId = $user['user_id'];

        $budget = $userBudgets[$userId][$cat] ?? null;
        if ($budget !== null && $spent > $budget) {
            $stmt = $pdo->prepare("SELECT first_name, last_name FROM USERS WHERE id=?");
            $stmt->execute([$userId]);
            $userData = $stmt->fetch();
            $userName = $userData ? $userData['first_name'].' '.$userData['last_name'] : 'User '.$userId;

            $usersOverBudget[$cat][] = [
                'user_id' => $userId,
                'user' => $userName,
                'spent' => $spent,
                'budget' => $budget
            ];
        }
    }
}

// --- Add hard-coded fake users over budget ---
$usersOverBudget['Food'][] = [
    'user_id' => 1,
    'user' => 'John Doe',
    'spent' => 150,
    'budget' => 100
];
$usersOverBudget['Transport'][] = [
    'user_id' => 2,
    'user' => 'Jane Smith',
    'spent' => 75,
    'budget' => 50
];
$usersOverBudget['Health'][] = [
    'user_id' => 3,
    'user' => 'Alice Johnson',
    'spent' => 200,
    'budget' => 150
];
$usersOverBudget['Entertainment'][] = [
    'user_id' => 4,
    'user' => 'Bob Brown',
    'spent' => 120,
    'budget' => 60
];

// --- Fetch all users for sending notifications ---
$stmt = $pdo->query("SELECT id, first_name, last_name FROM USERS");
$allUsers = $stmt->fetchAll();

// --- Send Notification ---
$successMsg = $errorMsg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'])) {
    $msgUserId = (int)$_POST['user_id'];
    $message = trim($_POST['message']);
    if ($msgUserId > 0 && !empty($message)) {
        $stmt = $pdo->prepare("INSERT INTO NOTIFICATIONS (user_id, message, created_at) VALUES (?, ?, NOW())");
        $stmt->execute([$msgUserId, $message]);
        $successMsg = "Notification sent to user ID $msgUserId";
    } else {
        $errorMsg = "Provide valid user and message";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Admin Dashboard</title>
<link rel="stylesheet" href="style.css">
<style>
body{font-family:sans-serif;background:#f5f5f5;margin:0;padding:0;}
header{background:#1a365d;color:#fff;padding:0.5rem 1rem;display:flex;justify-content:space-between;align-items:center;}
header nav ul{list-style:none;display:flex;gap:0.5rem;margin:0;padding:0;}
header nav ul li a{color:#fff;text-decoration:none;padding:0.25rem 0.5rem;background:#64b5f6;border:2px solid #000;}
.dashboard-container{padding:1rem;max-width:1000px;margin:1rem auto;background:#fff;border:3px solid #000;}
.cards-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:1rem;}
.category-card,.over-budget-box{padding:1rem;border:3px solid #000;box-shadow:4px 4px 0 #000;}
.over-budget-box{background:#f44336;color:#fff;}
form{margin-top:1rem;padding:1rem;border:3px solid #000;}
input,textarea,select,button{width:100%;margin-bottom:0.5rem;padding:0.5rem;border:2px solid #000;}
button{background:#64b5f6;color:#fff;cursor:pointer;}
.success{color:green;}
.error{color:red;}
</style>
</head>
<body>
<header>
<div class="logo">Admin Dashboard</div>

</header>

<div class="dashboard-container">
<h2>Top Spending Stores</h2>
<div class="cards-grid">
<?php foreach($topSpending as $cat => $stores): ?>
<div class="category-card">
<h3><?php echo htmlspecialchars($cat); ?></h3>
<?php if(empty($stores)): ?>
<p>No data</p>
<?php else: ?>
<ul>
<?php foreach($stores as $store=>$amt): ?>
<li><?php echo htmlspecialchars($store); ?>: $<?php echo number_format($amt,2); ?></li>
<?php endforeach; ?>
</ul>
<?php endif; ?>
</div>
<?php endforeach; ?>
</div>

<h2>Users Over Budget</h2>
<div class="cards-grid">
<?php foreach($usersOverBudget as $cat => $users): ?>
<?php foreach($users as $u): ?>
<div class="over-budget-box">
<p><?php echo htmlspecialchars($u['user']); ?> exceeded <?php echo htmlspecialchars($cat); ?> budget</p>
<p>Spent: $<?php echo number_format($u['spent'],2); ?> / Budget: $<?php echo number_format($u['budget'],2); ?></p>
</div>
<?php endforeach; ?>
<?php endforeach; ?>
</div>

<h2>Send Notification</h2>
<?php if($successMsg) echo "<p class='success'>".htmlspecialchars($successMsg)."</p>"; ?>
<?php if($errorMsg) echo "<p class='error'>".htmlspecialchars($errorMsg)."</p>"; ?>
<form method="POST">
<label>User</label>
<select name="user_id">
<?php foreach($allUsers as $u): ?>
<option value="<?php echo $u['id']; ?>"><?php echo htmlspecialchars($u['first_name'].' '.$u['last_name']).' (ID: '.$u['id'].')'; ?></option>
<?php endforeach; ?>
</select>
<label>Message</label>
<textarea name="message" rows="3" placeholder="Type message..."></textarea>
<button type="submit" name="send_message">Send</button>
</form>
</div>
</body>
</html>
