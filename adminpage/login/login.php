<?php
session_start();

// --- DB Connection ---
$host = '127.0.0.1';
$db   = 'database'; // replace with your DB name
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

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    $stmt = $pdo->prepare("SELECT * FROM USERS WHERE email = :username LIMIT 1");
    $stmt->execute(['username' => $username]);
    $user = $stmt->fetch();

    if (!$user || $user['is_admin'] != 1) {
        $_SESSION['error'] = "Invalid admin credentials.";
        header("Location: login.php");
        exit();
    }

    // Verify password (replace with password_verify if hashed)
    if ($password === $user['password']) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['first_name'];
        $_SESSION['is_admin'] = true;

        // Redirect to admin dashboard
        header("Location: ../dashboard/dashboard.php");
        exit();
    } else {
        $_SESSION['error'] = "Wrong password.";
        header("Location: login.php");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - Reboot Banking</title>
    <link rel="stylesheet" href="style.css">
    <link rel="icon" href="../assets/logo.jpg">
</head>
<body>
    <div class="background-animation"></div>

    <div class="login-container">
        <div class="bank-logo">
            Reboot Banking - Admin
        </div>

        <?php if (isset($_SESSION['error'])): ?>
            <div style="color:red; text-align:center;">
                <?= $_SESSION['error']; ?>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <form action="login.php" method="POST">
            <div class="input-group">
                <label for="username">Email</label>
                <input type="text" name="username" id="username" required autocomplete="username">
            </div>
            
            <div class="input-group">
                <label for="password">Password</label>
                <input type="password" name="password" id="password" required>
            </div>
            
            
            <button type="submit" class="login-button">Login</button>
        </form>
    </div>

    <script src="script.js"></script>
</body>
</html>
