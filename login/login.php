<?php
// login.php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/config.php';

try {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        // also accept form-encoded fallback
        $input = $_POST;
    }

    $username = isset($input['username']) ? trim($input['username']) : '';
    $password = isset($input['password']) ? (string)$input['password'] : '';

    if ($username === '' || $password === '') {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Missing username or password']);
        exit;
    }

    // username is USERS.id
    if (!ctype_digit($username)) {
        echo json_encode(['ok' => false, 'error' => 'Username must be your numeric User ID']);
        exit;
    }
    $userId = (int)$username;

    $pdo = db();

    // Ensure an attempts table exists (no change to original schema required)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS LOGIN_ATTEMPTS (
            user_id INTEGER PRIMARY KEY,
            attempts INTEGER NOT NULL DEFAULT 0,
            last_attempt TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );
    ");

    // Get user
    $stmt = $pdo->prepare("SELECT * FROM USERS WHERE id = :id");
    $stmt->execute([':id' => $userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        echo json_encode(['ok' => false, 'error' => 'User not found']);
        exit;
    }

    if ((int)$user['is_active'] === 0) {
        echo json_encode(['ok' => false, 'error' => 'Account is locked. Contact support.']);
        exit;
    }

    // Fetch attempts
    $stmt = $pdo->prepare("SELECT attempts FROM LOGIN_ATTEMPTS WHERE user_id = :id");
    $stmt->execute([':id' => $userId]);
    $attemptRow = $stmt->fetch(PDO::FETCH_ASSOC);
    $attempts = $attemptRow ? (int)$attemptRow['attempts'] : 0;

    // Compare password (plaintext per your schema; hash in real apps)
    if ($user['password'] !== $password) {
        $attempts++;
        if ($attemptRow) {
            $pdo->prepare("UPDATE LOGIN_ATTEMPTS SET attempts = :a, last_attempt = CURRENT_TIMESTAMP WHERE user_id = :id")
                ->execute([':a' => $attempts, ':id' => $userId]);
        } else {
            $pdo->prepare("INSERT INTO LOGIN_ATTEMPTS (user_id, attempts) VALUES (:id, :a)")
                ->execute([':id' => $userId, ':a' => $attempts]);
        }

        if ($attempts >= 3) {
            // Lock account
            $pdo->prepare("UPDATE USERS SET is_active = 0, updated_at = CURRENT_TIMESTAMP WHERE id = :id")
                ->execute([':id' => $userId]);
            echo json_encode(['ok' => false, 'error' => 'Too many failed attempts. Account locked.']);
            exit;
        }

        echo json_encode(['ok' => false, 'error' => 'Invalid credentials', 'remaining' => (3 - $attempts)]);
        exit;
    }

    // Correct password → reset attempts
    $pdo->prepare("DELETE FROM LOGIN_ATTEMPTS WHERE user_id = :id")->execute([':id' => $userId]);

    // (Optional) also ensure user is_active if previously locked and manually reactivated
    if ((int)$user['is_active'] === 0) {
        echo json_encode(['ok' => false, 'error' => 'Account is locked.']);
        exit;
    }

    // Set session (optional for your project)
    $_SESSION['user_id'] = $user['id'];

    echo json_encode([
        'ok' => true,
        'redirect' => '/dashboard/dashboard.html',
        'user' => [
            'id' => $user['id'],
            'first_name' => $user['first_name'],
            'last_name' => $user['last_name'],
            'email' => $user['email'],
            'is_admin' => (int)$user['is_admin'] === 1
        ]
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Server error', 'detail' => $e->getMessage()]);
}
