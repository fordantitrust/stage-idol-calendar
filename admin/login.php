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
        $error = "login_rate:{$waitMins}";
    } elseif (admin_login($username, $password)) {
        clear_login_attempts($clientIp);
        header('Location: index.php');
        exit;
    } else {
        record_failed_login($clientIp);
        $error = 'login_invalid';
    }
}

$csrfToken = csrf_token();
?>
<!DOCTYPE html>
<html lang="th" id="loginHtml">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Admin <?php echo htmlspecialchars(get_site_title()); ?></title>
    <link rel="stylesheet" href="<?php echo asset_url('../styles/common.css'); ?>">
    <script src="<?php echo asset_url('js/admin-i18n.js'); ?>"></script>
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

        /* Language toggle */
        .login-lang-toggle {
            display: flex;
            justify-content: flex-end;
            margin-bottom: 16px;
            gap: 4px;
        }

        .login-lang-btn {
            background: none;
            border: 1px solid #ddd;
            border-radius: 6px;
            padding: 4px 10px;
            font-size: 0.8rem;
            cursor: pointer;
            color: #666;
            transition: all 0.15s;
        }

        .login-lang-btn.active {
            background: var(--sakura-medium);
            border-color: var(--sakura-medium);
            color: white;
            font-weight: 600;
        }

        .login-lang-btn:hover:not(.active) {
            border-color: var(--sakura-medium);
            color: var(--sakura-dark);
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-lang-toggle">
            <button class="login-lang-btn active" data-lang="th" onclick="changeAdminLang('th')">🇹🇭 TH</button>
            <button class="login-lang-btn" data-lang="en" onclick="changeAdminLang('en')">🇬🇧 EN</button>
        </div>

        <div class="login-header">
            <h1 data-i18n="login.title">Admin Login</h1>
            <p><?php echo htmlspecialchars(get_site_title()); ?></p>
        </div>

        <?php if ($error): ?>
            <div class="error-message" id="loginError">
                <?php if ($error === 'login_invalid'): ?>
                    <span data-i18n="login.errInvalid">Username หรือ Password ไม่ถูกต้อง</span>
                <?php elseif (strpos($error, 'login_rate:') === 0): ?>
                    <?php $waitMins = substr($error, strlen('login_rate:')); ?>
                    <span id="loginRateMsg" data-wait="<?php echo (int)$waitMins; ?>"></span>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">

            <div class="form-group">
                <label for="username" data-i18n="login.username">Username</label>
                <input type="text" id="username" name="username" required autofocus>
            </div>

            <div class="form-group">
                <label for="password" data-i18n="login.password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>

            <button type="submit" class="btn-login" data-i18n="login.submit">เข้าสู่ระบบ</button>
        </form>

        <a href="../index.php" class="back-link" data-i18n="login.backToMain">&larr; กลับหน้าหลัก</a>
    </div>

    <script>
        // Sync lang toggle buttons with current lang
        (function () {
            var lang = localStorage.getItem('admin_lang') || 'th';
            document.querySelectorAll('.login-lang-btn').forEach(function (btn) {
                btn.classList.toggle('active', btn.getAttribute('data-lang') === lang);
            });

            // Patch rate-limit message with wait minutes
            var rateEl = document.getElementById('loginRateMsg');
            if (rateEl) {
                var mins = rateEl.getAttribute('data-wait');
                var tpl = (window.adminT && adminT('login.errTooMany')) ||
                    'พยายาม login หลายครั้งเกินไป กรุณารอ {min} นาทีแล้วลองใหม่';
                rateEl.textContent = tpl.replace('{min}', mins);
            }

            // Override changeAdminLang to also sync login-lang-btn
            var _origChange = window.changeAdminLang;
            window.changeAdminLang = function (lang) {
                if (_origChange) _origChange(lang);
                document.querySelectorAll('.login-lang-btn').forEach(function (btn) {
                    btn.classList.toggle('active', btn.getAttribute('data-lang') === lang);
                });
                // Re-render rate-limit message if present
                var rateEl2 = document.getElementById('loginRateMsg');
                if (rateEl2) {
                    var mins2 = rateEl2.getAttribute('data-wait');
                    var tpl2 = adminT('login.errTooMany');
                    rateEl2.textContent = tpl2.replace('{min}', mins2);
                }
            };
        })();
    </script>
</body>
</html>
