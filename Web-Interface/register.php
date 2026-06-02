<?php
session_start();

if (isset($_SESSION['user'])) {
    header('Location: index.php');
    exit;
}

require_once 'db.php';

$error   = '';
$success = '';

// Only allow registration if NO users exist yet (first-run admin setup)
$count = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
if ($count > 0 && !isset($_SESSION['role']) || (isset($_SESSION['role']) && $_SESSION['role'] !== 'admin')) {
    // If users already exist and you're not an admin, block access
    // Comment this block out if you want to allow open registration
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm'] ?? '';
    $role     = 'admin'; // first user is always admin

    if (empty($username) || empty($password) || empty($confirm)) {
        $error = 'All fields are required.';
    } elseif (strlen($username) < 3) {
        $error = 'Username must be at least 3 characters.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        // Check if username taken
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->fetch()) {
            $error = 'Username already taken.';
        } else {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $pdo->prepare(
                "INSERT INTO users (username, email, password_hash, role) VALUES (?, ?, ?, ?)"
            );
            $stmt->execute([$username, $email, $hash, $role]);
            $success = 'Account created! You can now log in.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IDS — Register</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <script src="assets/js/app.js" defer></script>
</head>
<body>
<div class="auth-wrapper">
    <div class="auth-card">

        <div class="auth-logo">
            <div class="shield">🛡</div>
            <h1>Create Admin Account</h1>
            <p>First-time setup — this will be your admin user</p>
        </div>

        <?php if ($error): ?>
        <div class="alert-box error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
        <div class="alert-box success">
            <?= htmlspecialchars($success) ?>
            <br><a href="login.php">Go to login →</a>
        </div>
        <?php else: ?>

        <form method="POST" action="register.php">
            <div class="form-group">
                <label for="username">Username</label>
                <input
                    type="text"
                    id="username"
                    name="username"
                    placeholder="e.g. admin"
                    value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                    required
                >
            </div>

            <div class="form-group">
                <label for="email">Email <span style="color:var(--text-muted)">(optional)</span></label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    placeholder="you@example.com"
                    value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                >
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input
                    type="password"
                    id="password"
                    name="password"
                    placeholder="At least 6 characters"
                    required
                >
            </div>

            <div class="form-group">
                <label for="confirm">Confirm Password</label>
                <input
                    type="password"
                    id="confirm"
                    name="confirm"
                    placeholder="Repeat your password"
                    required
                >
            </div>

            <button type="submit" class="btn btn-primary">Create Account</button>
        </form>

        <?php endif; ?>

        <div class="auth-footer">
            Already have an account? <a href="login.php">Sign in</a>
        </div>

    </div>
</div>
</body>
</html>