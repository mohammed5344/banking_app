<?php
session_start();

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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    $stmt = $pdo->prepare("
        SELECT u.*, a.account_number 
        FROM USERS u
        LEFT JOIN ACCOUNTS a ON u.id = a.user_id
        WHERE u.email = :username OR a.account_number = :username
        LIMIT 1
    ");
    $stmt->execute(['username' => $username]);
    $user = $stmt->fetch();

    if (!$user) {
        $_SESSION['error'] = "Invalid login credentials.";
        header("Location: userpage/login/login.php");
        exit();
    }

    if ($user['is_active'] == 0) {
        $_SESSION['error'] = "Your account is locked. Please contact support.";
        header("Location: userpage/login/login.php");
        exit();
    }

    if (!isset($_SESSION['attempts'][$user['id']])) {
        $_SESSION['attempts'][$user['id']] = 0;
    }

    if ($password === $user['password']) {
        $_SESSION['attempts'][$user['id']] = 0;

        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['first_name'];

        header("Location: ../dashboard/dashboard.php");
        exit();
    } else {
        $_SESSION['attempts'][$user['id']]++;

        if ($_SESSION['attempts'][$user['id']] >= 3) {
            // Lock account in DB
            $update = $pdo->prepare("UPDATE USERS SET is_active = 0 WHERE id = :id");
            $update->execute(['id' => $user['id']]);

            $_SESSION['error'] = "Account locked due to too many failed attempts.";
        } else {
            $_SESSION['error'] = "Wrong password. Attempt {$_SESSION['attempts'][$user['id']]} of 3.";
        }

        header("Location: userpage/login/login.php");
        exit();
    }
} else {
    // Redirect if accessed directly
    header("Location: userpage/login/login.php");
    exit();
}
