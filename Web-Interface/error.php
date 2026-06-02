<?php
session_start();
$code    = intval($_GET['code'] ?? 500);
$message = htmlspecialchars($_GET['message'] ?? 'An unexpected error occurred.');

$titles = [
    400 => 'Bad Request',
    403 => 'Forbidden',
    404 => 'Not Found',
    500 => 'Server Error',
    503 => 'Service Unavailable',
];

$title = $titles[$code] ?? 'Error';
http_response_code($code);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>RAKSHAKAI — <?= $title ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <script src="assets/js/app.js" defer></script>
</head>
<body>
<div style="min-height:100vh;display:flex;align-items:center;
            justify-content:center;background:var(--bg)">
    <div style="text-align:center;padding:40px">
        <div style="font-size:72px;font-weight:700;color:var(--border);
                    line-height:1;margin-bottom:16px">
            <?= $code ?>
        </div>
        <h1 style="font-size:22px;color:var(--text);margin-bottom:8px">
            <?= $title ?>
        </h1>
        <p style="color:var(--text-muted);font-size:14px;margin-bottom:24px">
            <?= $message ?>
        </p>
        <a href="index.php"
           style="background:var(--accent);color:#000;padding:10px 24px;
                  border-radius:8px;text-decoration:none;font-weight:500;
                  font-size:14px">
            Go to dashboard
        </a>
    </div>
</div>
</body>
</html>