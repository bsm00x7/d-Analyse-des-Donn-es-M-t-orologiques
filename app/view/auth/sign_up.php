<?php
require(__DIR__ . '/../../../config/database.php');

session_start();

// CSRF
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$error   = '';
$fields  = ['name' => '', 'email' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // CSRF check
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request.';
    } else {

        $name     = trim($_POST['name'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm  = $_POST['password_confirm'] ?? '';

        $fields['name']  = $name;
        $fields['email'] = $email;

        // VALIDATION
        if (!$name || !$email || !$password || !$confirm) {
            $error = 'All fields are required.';
        } elseif (strlen($name) < 2) {
            $error = 'Name too short.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Invalid email.';
        } elseif (strlen($password) < 8) {
            $error = 'Password must be 8+ chars.';
        } elseif (!preg_match('/[A-Z]/', $password) || !preg_match('/[0-9]/', $password)) {
            $error = 'Password must contain uppercase + number.';
        } elseif ($password !== $confirm) {
            $error = 'Passwords do not match.';
        } elseif (empty($_POST['terms'])) { // ✅ FIX مهم
            $error = 'You must accept terms.';
        } else {

            // CHECK EMAIL
            $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $stmt->store_result();

            if ($stmt->num_rows > 0) {
                $error = 'Email already exists.';
                $stmt->close();
            } else {
                $stmt->close();

                // INSERT
                $hash = password_hash($password, PASSWORD_DEFAULT);

                $stmt = $conn->prepare("INSERT INTO users (name, email, password) VALUES (?, ?, ?)");
                $stmt->bind_param("sss", $name, $email, $hash);

                if ($stmt->execute()) {

                    // SESSION
                    session_regenerate_id(true);

                    $_SESSION['user_id']    = $stmt->insert_id;
                    $_SESSION['user_name']  = $name;
                    $_SESSION['user_email'] = $email;

                    // regenerate CSRF (optional but better)
                    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

                    header("Location: /");
                    exit;
                } else {
                    $error = 'Database error.';
                }

                $stmt->close();
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
    <title>Create account</title>
    <link href="../../../node_modules/bootstrap/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../../../style/signup.css">
</head>

<body>

    <div class="auth-card">

        <!-- Visual panel -->
        <div class="auth-visual">
            <img src="../../../assets/img/login.jpg" alt="Sign up illustration">
            <p class="tagline">Join us today</p>
            <p class="sub">Create your free account in seconds</p>
        </div>

        <!-- Form panel -->
        <div class="auth-form-wrap">
            <h1>Create account</h1>
            <p class="subtitle">Fill in the details below to get started</p>

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
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

                <!-- Name + Email -->
                <div class="field-row mb-3">
                    <div>
                        <label class="form-label" for="name">Full name</label>
                        <input
                            type="text"
                            id="name"
                            name="name"
                            class="form-control <?= ($error && empty(trim($_POST['name'] ?? ''))) ? 'is-invalid' : '' ?>"
                            value="<?= htmlspecialchars($fields['name']) ?>"
                            autocomplete="name"
                            required
                            autofocus>
                    </div>
                    <div>
                        <label class="form-label" for="email">Email address</label>
                        <input
                            type="email"
                            id="email"
                            name="email"
                            class="form-control <?= ($error && empty(trim($_POST['email'] ?? ''))) ? 'is-invalid' : '' ?>"
                            value="<?= htmlspecialchars($fields['email']) ?>"
                            autocomplete="email"
                            required>
                    </div>
                </div>

                <!-- Password -->
                <div class="mb-2">
                    <label class="form-label" for="password">Password</label>
                    <div class="pw-wrap">
                        <input
                            type="password"
                            id="password"
                            name="password"
                            class="form-control"
                            autocomplete="new-password"
                            required
                            style="padding-right: 2.5rem;"
                            oninput="updateStrength(this.value)">
                        <button type="button" class="pw-toggle" onclick="togglePw('password', this)" aria-label="Toggle password visibility">
                            <svg id="eye-password" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
                                <circle cx="12" cy="12" r="3" />
                            </svg>
                        </button>
                    </div>
                    <div class="strength-wrap">
                        <div class="strength-bar" id="bar1"></div>
                        <div class="strength-bar" id="bar2"></div>
                        <div class="strength-bar" id="bar3"></div>
                        <div class="strength-bar" id="bar4"></div>
                        <span class="strength-label" id="strength-label"></span>
                    </div>
                </div>

                <!-- Confirm Password -->
                <div class="mb-3">
                    <label class="form-label" for="password_confirm">Confirm password</label>
                    <div class="pw-wrap">
                        <input
                            type="password"
                            id="password_confirm"
                            name="password_confirm"
                            class="form-control"
                            autocomplete="new-password"
                            required
                            style="padding-right: 2.5rem;">
                        <button type="button" class="pw-toggle" onclick="togglePw('password_confirm', this)" aria-label="Toggle password visibility">
                            <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
                                <circle cx="12" cy="12" r="3" />
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Terms -->
                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" name="terms" id="terms" value="1" required>
                    <label class="form-check-label" for="terms">
                        I agree to the <a href="/terms">Terms of Service</a> and <a href="/privacy">Privacy Policy</a>
                    </label>
                </div>

                <button type="submit" class="btn-signup">Create account</button>
            </form>

            <div class="signin-row">
                Already have an account? <a href="/login">Sign in</a>
            </div>
        </div>
    </div>

    <script src="../../../node_modules/bootstrap/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function togglePw(fieldId, btn) {
            var input = document.getElementById(fieldId);
            var isText = input.type === 'text';
            input.type = isText ? 'password' : 'text';
            btn.innerHTML = isText ?
                '<svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>' :
                '<svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>';
        }

        function updateStrength(val) {
            var score = 0;
            if (val.length >= 8) score++;
            if (/[A-Z]/.test(val)) score++;
            if (/[0-9]/.test(val)) score++;
            if (/[^A-Za-z0-9]/.test(val) && val.length >= 10) score++;

            var colors = ['', '#ef4444', '#f97316', '#eab308', '#22c55e'];
            var labels = ['', 'Weak', 'Fair', 'Good', 'Strong'];
            var textCol = ['', '#ef4444', '#f97316', '#ca8a04', '#16a34a'];

            for (var i = 1; i <= 4; i++) {
                var bar = document.getElementById('bar' + i);
                bar.style.background = i <= score ? colors[score] : '#e2e8f0';
            }

            var lbl = document.getElementById('strength-label');
            lbl.textContent = val.length ? labels[score] : '';
            lbl.style.color = val.length ? textCol[score] : '#94a3b8';
        }
    </script>
</body>

</html>