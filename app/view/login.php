<?php
require(__DIR__ . '/../../config/database.php');

session_start();

$error = '';
$email = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } else {
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        if (empty($email) || empty($password)) {
            $error = 'Please fill in all fields.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } else {
            $stmt = $conn->prepare('SELECT  password,role FROM users WHERE email = ? LIMIT 1');
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $result = $stmt->get_result();
            $user   = $result->fetch_assoc();
            $stmt->close();

            if ($user && password_verify($password, $user['password'])) {
                session_regenerate_id(true);
                $_SESSION['user_id']   = $user['id'];
                $_SESSION['user_name'] = $user['name'];

                if (!empty($_POST['remember'])) {
                    $token = bin2hex(random_bytes(32));
                    setcookie('remember_token', $token, time() + 60 * 60 * 24 * 30, '/', '', true, true);
                }
                if ($user['role'] == 'admin') {

                    header('Location: /analyseM/dashboard');
                } else {
                    header('Location: /analyseM/home.php');
                }


                exit;
            } else {
                $error = 'Invalid email or password.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign in</title>
    <link href="../../node_modules/bootstrap/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../../style/login.css">
</head>

<body>

    <div class="login-card">
        <!-- Visual panel -->
        <div class="login-visual">
            <img src="../../assets/img/login.jpg" alt="Login illustration">
            <p class="tagline">Welcome back</p>
            <p class="sub">Sign in to continue to your account</p>
        </div>

        <!-- Form panel -->
        <div class="login-form-wrap">
            <h1>Sign in</h1>
            <p class="subtitle">Enter your credentials below</p>

            <?php if ($error): ?>
                <div class="alert-error" role="alert">
                    <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10" />
                        <line x1="12" y1="8" x2="12" y2="12" />
                        <line x1="12" y1="16" x2="12.01" y2="16" />
                    </svg>
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="" novalidate>
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ??= bin2hex(random_bytes(32))) ?>">

                <div class="mb-3">
                    <label class="form-label" for="email">Email address</label>
                    <input
                        type="email"
                        id="email"
                        name="email"
                        class="form-control <?= $error ? 'is-invalid' : '' ?>"
                        value="<?= htmlspecialchars($email) ?>"
                        autocomplete="email"
                        required
                        autofocus>
                </div>

                <div class="mb-3">
                    <label class="form-label" for="password">Password</label>
                    <input
                        type="password"
                        id="password"
                        name="password"
                        class="form-control <?= $error ? 'is-invalid' : '' ?>"
                        autocomplete="current-password"
                        required>
                </div>

                <div class="meta-row">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="remember" id="remember" value="1">
                        <label class="form-check-label" for="remember">Remember me</label>
                    </div>
                    <a href="/forgot-password" class="forgot-link">Forgot password?</a>
                </div>

                <button type="submit" class="btn-signin">Sign in</button>
            </form>

            <div class="register-row">
                Don't have an account? <a href="../view/sign_up.php">Create one</a>
            </div>
        </div>
    </div>

    <script src="../../node_modules/bootstrap/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>