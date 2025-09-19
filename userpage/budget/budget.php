<?php
session_start();

// For testing, set a user_id. In production, get this from login session
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1; // example user id
}
$user_id = $_SESSION['user_id'];

// Database connection
$host = 'localhost';
$db = 'database'; // your database name
$user = 'root';
$pass = ''; // your MySQL password
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

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $budgets = $_POST['budget'] ?? [];

    foreach ($budgets as $category => $items) {
        foreach ($items as $item => $amount) {
            $amount = floatval($amount);

            // Check if budget already exists
            $stmt = $pdo->prepare("SELECT id FROM BUDGETS WHERE user_id = :uid AND category = :cat AND item = :item");
            $stmt->execute(['uid' => $user_id, 'cat' => $category, 'item' => $item]);
            $exists = $stmt->fetch();

            if ($exists) {
                // Update
                $update = $pdo->prepare("UPDATE BUDGETS SET amount = :amt WHERE id = :id");
                $update->execute(['amt' => $amount, 'id' => $exists['id']]);
            } else {
                // Insert
                $insert = $pdo->prepare("INSERT INTO BUDGETS (user_id, category, item, amount) VALUES (:uid, :cat, :item, :amt)");
                $insert->execute(['uid' => $user_id, 'cat' => $category, 'item' => $item, 'amt' => $amount]);
            }
        }
    }

    $message = "Budget saved successfully!";
}

// Fetch existing budgets
$stmt = $pdo->prepare("SELECT category, item, amount FROM BUDGETS WHERE user_id = :uid");
$stmt->execute(['uid' => $user_id]);
$existing = $stmt->fetchAll();
$budget_data = [];
foreach ($existing as $row) {
    $budget_data[$row['category']][$row['item']] = $row['amount'];
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>budget</title>
    <link rel="stylesheet" href="style.css">
    <link rel="icon" href="../assets/logo.jpg">
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
        <h2 class="dashboard-title">Set Your Budget</h2>
        <?php if (!empty($message)) echo "<p style='color:green;'>$message</p>"; ?>
        <form method="POST">
            <?php
            $categories = [
                'Food' => ['Groceries', 'Restaurants', 'Snacks', 'Drinks'],
                'Rent' => ['Apartment', 'House', 'Utilities'],
                'Transport' => ['Public Transport', 'Taxi/Rideshare', 'Fuel', 'Parking'],
                'Entertainment' => ['Movies', 'Games', 'Events', 'Subscriptions'],
                'Health' => ['Medicines', 'Doctor Visits', 'Gym', 'Therapy'],
                'Savings' => ['Emergency Fund', 'Investments', 'Retirement', 'Other Savings'],
                'Car' => ['Fuel', 'Maintenance', 'Insurance', 'Registration'],
                'Others' => ['Gifts', 'Travel', 'Clothing', 'Miscellaneous']
            ];

            $grand_total = 0;
            foreach ($categories as $cat => $items) {
                echo "<div class='card'><h3>$cat</h3><div class='card-content'>";
                $cat_total = 0;
                foreach ($items as $item) {
                    $value = $budget_data[$cat][$item] ?? '';
                    echo "<label>$item <input type='number' name='budget[$cat][$item]' value='$value'></label>";
                    $cat_total += floatval($value);
                }
                echo "<p><strong>Total $cat: </strong>" . number_format($cat_total, 2) . "</p>";
                $grand_total += $cat_total;
                echo "</div></div>";
            }
            ?>
            <p><strong>Grand Total: </strong><?php echo number_format($grand_total, 2); ?></p>
            <button class="confirm-button" type="submit">Save Budget</button>
        </form>
    </div>
    <script src="script.js"></script>
</body>

</html>