<?php session_start(); ?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Banking</title>
    <link rel="stylesheet" href="style.css">
    <link rel="icon" href="../assets/logo.jpg">
</head>

<body>
    <div class="background-animation"></div>

    <div class="login-container">
        <div class="bank-logo">
            Reboot Banking
        </div>

        <?php if (isset($_SESSION['error'])): ?>
            <div style="color:red; text-align:center;">
                <?= $_SESSION['error']; ?>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <form action="process_login.php" method="POST">
            <div class="input-group">
                <label for="username">Email</label>
                <input type="text" name="username" id="username" required autocomplete="username">
            </div>

            <div class="input-group">
                <label for="password">Password</label>
                <input type="password" name="password" id="password" required>
            </div>

            <div class="remember-forgot">
                <label class="remember-me">
                    <input type="checkbox" name="remember" id="remember">
                    Remember me
                </label>
                <a href="#" class="forgot-password">Forgot Password?</a>
            </div>

            <button type="submit" class="login-button">Login</button>
        </form>
    </div>
    <script src="script.js"></script>
</body>

</html>