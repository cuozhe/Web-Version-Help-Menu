<?php
session_start();
require_once __DIR__ . '/db.php';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $stmt = $pdo->prepare('SELECT * FROM admin WHERE username=? AND password=MD5(?)');
    $stmt->execute([$username, $password]);
    $user = $stmt->fetch();
    if ($user) {
        $_SESSION['admin'] = $user['username'];
        header('Location: index.php');
        exit;
    } else {
        $error = '用户名或密码错误';
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>后台登录</title>
    <link rel="stylesheet" href="../styles.css">
    <style>
        body { background: #f5f7fa; font-family: 'Segoe UI', 'PingFang SC', 'Microsoft YaHei', Arial, sans-serif; }
        .login-box {
            max-width: 400px;
            margin: 120px auto 0 auto;
            padding: 36px 32px 28px 32px;
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 4px 24px #0001;
            text-align: center;
        }
        .login-box h2 { color: #2196f3; margin-bottom: 28px; }
        .login-box label { display: block; color: #333; font-size: 16px; margin-bottom: 12px; text-align: left; }
        .login-box input[type=text], .login-box input[type=password] {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #d0d7de;
            border-radius: 8px;
            font-size: 16px;
            margin-bottom: 18px;
            background: #f8fafc;
            transition: border 0.2s;
        }
        .login-box input[type=text]:focus, .login-box input[type=password]:focus {
            border-color: #2196f3;
            outline: none;
        }
        .login-box button {
            width: 100%;
            background: #2196f3;
            color: #fff;
            border: none;
            border-radius: 8px;
            padding: 12px 0;
            font-size: 18px;
            cursor: pointer;
            margin-top: 8px;
            transition: background 0.2s;
        }
        .login-box button:hover { background: #1769aa; }
        .login-box .error { color: #f44336; margin-bottom: 16px; }
        @media (max-width: 600px) { .login-box { max-width: 98vw; padding: 18px 4vw; } }
    </style>
</head>
<body>
<div class="login-box">
    <h2>后台登录</h2>
    <?php if($error): ?><div class="error"><?= $error ?></div><?php endif; ?>
    <form method="post">
        <label>用户名：<input type="text" name="username" required autocomplete="username"></label>
        <label>密码：<input type="password" name="password" required autocomplete="current-password"></label>
        <button type="submit">登录</button>
    </form>
</div>
</body>
</html> 