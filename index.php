<?php
// 1. การจัดการเซสชัน: ลูกเล่นจัดเต็มด้านความปลอดภัย
// **********************************************
session_start([
    'cookie_lifetime' => 86400, // เซสชันอยู่ได้ 1 วัน
    'cookie_httponly' => true,  // ป้องกันการเข้าถึงผ่าน JavaScript (คูลมาก!)
    'cookie_secure'   => true,  // ต้องใช้ HTTPS เท่านั้น (ถ้าใช้ใน production)
    'use_strict_mode' => true   // เปิดใช้งานโหมดเข้มงวด
]);

// 2. การกำหนดค่า (Configuration)
// ******************************
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', 'your_super_secret_password');
define('DB_NAME', 'cool_database_app');

// ตั้งค่า Header ให้เป็น UTF-8 เสมอ
header('Content-Type: text/html; charset=utf-8');

// 3. ฟังก์ชันตัวช่วย "สุดโหด" สำหรับการเชื่อมต่อฐานข้อมูล (ใช้ PDO ที่ปลอดภัยกว่า)
// *****************************************************************************
function get_db_connection() {
    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // ให้ throw exception เมื่อเกิด error (ดีต่อการ debug)
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // ดึงข้อมูลเป็น Array แบบ Associative
        PDO::ATTR_EMULATE_PREPARES   => false,                  // ปิดการจำลอง Prepare Statements
    ];
    try {
        return new PDO($dsn, DB_USER, DB_PASS, $options);
    } catch (\PDOException $e) {
        // ใน Production ควร log error นี้ ไม่ใช่แสดงให้ผู้ใช้เห็น
        exit("Database connection failed: " . $e->getMessage());
    }
}


// 4. ลูกเล่นจัดเต็ม: Flash Messages (การแจ้งเตือนที่แสดงครั้งเดียวแล้วหายไป)
// ***********************************************************************
function flash_message($name = '', $message = '', $class = 'success') {
    if (!empty($name)) {
        if (!empty($message)) {
            // ตั้งค่าข้อความแจ้งเตือนใหม่
            $_SESSION[$name] = $message;
            $_SESSION[$name . '_class'] = $class;
        } else if (empty($message) && !empty($_SESSION[$name])) {
            // แสดงข้อความและลบทิ้ง (ลูกเล่นที่คูลมาก)
            $message = $_SESSION[$name];
            $class = $_SESSION[$name . '_class'];
            echo '<div class="alert alert-' . $class . '">' . htmlspecialchars($message) . '</div>';
            unset($_SESSION[$name]);
            unset($_SESSION[$name . '_class']);
        }
    }
}


// 5. การจัดการ Routing (ลูกเล่นหลักของแอปพลิเคชัน)
// ***********************************************
// ดึง path ที่ผู้ใช้ร้องขอ เช่น /about หรือ /user/123
$request_uri = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
$segments = explode('/', $request_uri);
$controller_name = !empty($segments[0]) ? $segments[0] : 'home'; // default เป็น 'home'
$action_name = !empty($segments[1]) ? $segments[1] : 'index'; // default เป็น 'index'
$param_id = !empty($segments[2]) ? (int)$segments[2] : null;

// Routing Map สุดโหด
$routes = [
    // [Controller, Action, ต้องการ Login ไหม]
    'home' => ['HomeController', 'index', false],
    'products' => ['ProductController', 'index', false],
    'login' => ['AuthController', 'login', false],
    'dashboard' => ['UserController', 'dashboard', true], // ต้อง Login ถึงเข้าได้
];

// ตรวจสอบ Route และความถูกต้อง
if (array_key_exists($controller_name, $routes)) {
    $route = $routes[$controller_name];
    $controller_class = $route[0];
    $controller_action = $route[1];
    $requires_auth = $route[2];

    // ลูกเล่น: ตรวจสอบการ Login
    if ($requires_auth && !isset($_SESSION['user_id'])) {
        flash_message('auth_error', 'คุณต้องเข้าสู่ระบบเพื่อเข้าถึงหน้านี้', 'danger');
        header('Location: /login');
        exit();
    }
    
    // จำลองการเรียก Controller Class (ในโปรเจกต์จริงจะใช้ Autoload)
    // *************************************************************
    if ($controller_class === 'HomeController' && $controller_action === 'index') {
        // *** Controller Action จำลอง ***
        $db = get_db_connection();
        $stmt = $db->query("SELECT COUNT(*) FROM users");
        $user_count = $stmt->fetchColumn();
        
        $title = "เว็บไซต์สุดโหดพลัง PHP";
        $header = "ยินดีต้อนรับสู่ PHP Backend ที่สุดยอด!";
        $content = "เรามีผู้ใช้งานแล้ว $user_count คน และนี่คือระบบ Session และ Routing ที่คูลมาก!";
        
        // *******************************
    } else {
        $title = "404 Not Found";
        $header = "404 - ไม่พบเส้นทาง";
        $content = "ไม่พบหน้าที่คุณร้องขอใน Routing Map สุดโหดนี้";
        http_response_code(404);
    }
    
} else {
    // ไม่มี Route นี้
    $title = "404 Not Found";
    $header = "404 - ไม่พบเส้นทาง";
    $content = "ไม่พบหน้าที่คุณร้องขอใน Routing Map สุดโหดนี้";
    http_response_code(404);
}

?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $title; ?></title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <style>
        .container { margin-top: 50px; }
        .alert { animation: fadeOut 5s forwards; } /* ลูกเล่นสุดโหด: แจ้งเตือนจะเฟดหายไปเอง */
        @keyframes fadeOut {
            0% { opacity: 1; }
            80% { opacity: 1; }
            100% { opacity: 0; display: none; }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1><?= $header; ?></h1>
        <hr>

        <?php flash_message('auth_error'); ?> 
        
        <p><?= $content; ?></p>

        <h2>ลูกเล่น Session:</h2>
        <?php
            if (!isset($_SESSION['view_count'])) {
                $_SESSION['view_count'] = 0;
            }
            $_SESSION['view_count']++;
            echo "<p>คุณเข้าชมหน้านี้แล้ว: **{$_SESSION['view_count']}** ครั้งในเซสชันนี้ (ข้อมูลจะหายไปเมื่อปิดเบราว์เซอร์)</p>";
        ?>
        
        <div class="card mt-4">
            <div class="card-header">รายละเอียด Route ที่เรียก</div>
            <ul class="list-group list-group-flush">
                <li class="list-group-item">Controller: **<?= $controller_name; ?>** (Class: **<?= $controller_class; ?>**)</li>
                <li class="list-group-item">Action: **<?= $action_name; ?>** (Method: **<?= $controller_action; ?>**)</li>
                <?php if ($param_id): ?>
                    <li class="list-group-item">Parameter ID: **<?= $param_id; ?>**</li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</body>
</html>
