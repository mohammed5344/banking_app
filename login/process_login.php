<?php
session_start();

// DB connection (SQLite for your db/DATABASE.sql)
try {
    $pdo = new PDO("sqlite:db/DATABASE.sql");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    die("DB Connection failed: " . $e->getMessage());
}

// Handle login form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    // Find user by email or account number
    $stmt = $pdo->prepare("
        SELECT u.*, a.account_number 
        FROM USERS u
        LEFT JOIN ACCOUNTS a ON u.id = a.user_id
        WHERE u.email = :username OR a.account_number = :username
        LIMIT 1
    ");
    $stmt->execute(['username' => $username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        $_SESSION['error'] = "Invalid login credentials.";
        header("Location: login.php");
        exit();
    }

    // Check if account is active
    if ($user['is_active'] == 0) {
        $_SESSION['error'] = "Your account is locked. Please contact support.";
        header("Location: login.php");
        exit();
    }

    // Track login attempts in session
    if (!isset($_SESSION['attempts'][$user['id']])) {
        $_SESSION['attempts'][$user['id']] = 0;
    }

    // Verify password
    if (password_verify($password, $user['password'])) {
        // Reset attempts
        $_SESSION['attempts'][$user['id']] = 0;

        // Redirect to dashboard
        header("Location: dashboard/dashboard.html");
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

        header("Location: login.php");
        exit();
    }
}
