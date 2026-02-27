<?php
/**
 * Admin Login Page
 */
require_once __DIR__ . '/../config.php';
send_security_headers();

// IP Whitelist check - ต้องผ่านก่อนแสดงหน้า login
require_allowed_ip();

$error = '';

// Handle logout
if (isset($_GET['logout'])) {
    admin_logout();
    header('Location: login.php');
    exit;
}

// If already logged in, redirect to admin
if (is_logged_in()) {
    header('Location: index.php');
    exit;
}

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $clientIp = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

    $rateCheck = check_login_rate_limit($clientIp);
    if ($rateCheck['blocked']) {
        $waitMins = ceil($rateCheck['wait'] / 60);
        $error = "พยายาม login หลายครั้งเกินไป กรุณารอ {$waitMins} นาทีแล้วลองใหม่";
    } elseif (admin_login($username, $password)) {
        clear_login_attempts($clientIp);
        header('Location: index.php');
        exit;
    } else {
        record_failed_login($clientIp);
        $error = 'Username หรือ Password ไม่ถูกต้อง';
    }
}

$csrfToken = csrf_token();
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Admin Idol Stage Timetable</title>
    <link rel="stylesheet" href="<?php echo asset_url('../styles/common.css'); ?>">
    <style>
        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #fce4ec 0%, #f8bbd9 100%);
        }

        .login-container {
            background: white;
            padding: 40px;
            border-radius: 16px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 400px;
        }

        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .login-header h1 {
            color: var(--sakura-dark);
            font-size: 1.5rem;
            margin: 0 0 10px 0;
        }

        .login-header p {
            color: #666;
            margin: 0;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #333;
        }

        .form-group input {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 1rem;
            box-sizing: border-box;
            transition: border-color 0.2s;
        }

        .form-group input:focus {
            outline: none;
            border-color: var(--sakura-medium);
        }

        .btn-login {
            width: 100%;
            padding: 14px;
            background: var(--sakura-gradient);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(233, 30, 99, 0.3);
        }

        .btn-login:disabled {
            opacity: 0.7;
            cursor: not-allowed;
            transform: none;
        }

        .error-message {
            background: #ffebee;
            color: #c62828;
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
        }

        .back-link {
            display: block;
            text-align: center;
            margin-top: 20px;
            color: var(--sakura-medium);
            text-decoration: none;
        }

        .back-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <h1>Admin Login</h1>
            <p>Idol Stage Timetable</p>
        </div>

        <?php if ($error): ?>
            <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">

            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" required autofocus>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>

            <button type="submit" class="btn-login">Login</button>
        </form>

        <a href="../index.php" class="back-link">&larr; กลับหน้าหลัก</a>
    </div>
</body>
</html>
