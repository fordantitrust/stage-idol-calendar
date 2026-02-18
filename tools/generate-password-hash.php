<?php
/**
 * Password Hash Generator
 *
 * วิธีใช้:
 * php tools/generate-password-hash.php yourpassword
 *
 * หรือแก้ไข $password ด้านล่างแล้วรัน:
 * php tools/generate-password-hash.php
 */

// ========================================
// ใส่รหัสผ่านที่ต้องการที่นี่
// ========================================
$password = 'admin'; // <-- เปลี่ยนตรงนี้

// หรือรับจาก command line argument
if (isset($argv[1])) {
    $password = $argv[1];
}

// สร้าง hash
$hash = password_hash($password, PASSWORD_DEFAULT);

echo "==============================================\n";
echo "Password Hash Generator\n";
echo "==============================================\n\n";

echo "Password: {$password}\n";
echo "Hash: {$hash}\n\n";

echo "Option 1 (Recommended): Change password via Admin UI\n";
echo "  Login to Admin panel -> Click 'Change Password'\n\n";
echo "Option 2 (Config fallback): Paste in config/admin.php:\n";
echo "  define('ADMIN_PASSWORD_HASH', '{$hash}');\n\n";
echo "Option 3 (Direct DB update):\n";
echo "  UPDATE admin_users SET password_hash = '{$hash}' WHERE username = 'admin';\n\n";

echo "==============================================\n";
?>
