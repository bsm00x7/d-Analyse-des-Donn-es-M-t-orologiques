<?php
session_start();



$user_email = isset($_SESSION['user_email']) ? htmlspecialchars($_SESSION['user_email']) : '';
$support_email = 'support@ysupport.com';

$submitted = false;
$error     = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name    = trim($_POST['name']    ?? '');
    $email   = trim($_POST['email']   ?? '');
    $message = trim($_POST['message'] ?? '');

    if (empty($name) || empty($email) || empty($message)) {
        $error = 'Please fill in all fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        $to      = $support_email;
        $subject = "Account Reactivation Request – $name";
        $body    = "Name: $name\nEmail: $email\n\nMessage:\n$message";
        $headers = "From: $email\r\nReply-To: $email";

        if (mail($to, $subject, $body, $headers)) {
            $submitted = true;
        } else {
            $error = 'Failed to send your request. Please email us directly at ' . $support_email;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Account Suspended</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../../style/userSuspended.css">
</head>

<body>

    <div class="card">

        <!-- Icon -->
        <div class="icon-wrap">
            <svg viewBox="0 0 24 24" fill="none" stroke="#c0392b" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10" />
                <line x1="4.93" y1="4.93" x2="19.07" y2="19.07" />
            </svg>
        </div>

        <h1>Account Suspended</h1>
        <p class="subtitle">Your account has been temporarily suspended.<br>Please review the information below.</p>

        <!-- Info box -->
        <div class="info-box">
            <strong>Why was my account suspended?</strong><br>
            Your account may have been suspended due to a violation of our terms of service, suspicious activity, or a security concern.<br><br>
            To learn the exact reason, please contact our support team at
            <a href="mailto:<?= $support_email ?>"><?= $support_email ?></a>.
            We will respond within <strong>24–48 hours</strong>.
        </div>

        <hr class="divider">

        <!-- Request form -->
        <div class="form-section">
            <h2>Request Reactivation</h2>

            <?php if ($submitted): ?>
                <div class="success-box">
                    <div class="check">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#50c878" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="20 6 9 17 4 12" />
                        </svg>
                    </div>
                    <p>Your request has been sent successfully.<br>Our team will get back to you shortly at <strong><?= htmlspecialchars($_POST['email']) ?></strong>.</p>
                </div>

            <?php else: ?>

                <?php if ($error): ?>
                    <div class="error-msg"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <form method="POST" action="">
                    <div class="field">
                        <label for="name">Full Name</label>
                        <input type="text" id="name" name="name" placeholder="Your name"
                            value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" required>
                    </div>

                    <div class="field">
                        <label for="email">Email Address</label>
                        <input type="email" id="email" name="email"
                            placeholder="The email linked to your account"
                            value="<?= $user_email ?: htmlspecialchars($_POST['email'] ?? '') ?>" required>
                    </div>

                    <div class="field">
                        <label for="message">Your Message</label>
                        <textarea id="message" name="message" placeholder="Explain why you believe your account should be reactivated…" required><?= htmlspecialchars($_POST['message'] ?? '') ?></textarea>
                    </div>

                    <button type="submit" class="btn">Submit Reactivation Request</button>
                </form>

            <?php endif; ?>
        </div>

        <hr class="divider">
        <p class="footer-note">If you believe this is a mistake, email us at <a href="mailto:<?= $support_email ?>" style="color:var(--accent)"><?= $support_email ?></a></p>
    </div>

</body>

</html>