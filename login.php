<?php
include "config.php";
// เริ่ม session หากยังไม่ได้เริ่ม
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if(isset($_POST['login'])){
    $u = mysqli_real_escape_string($conn, $_POST['username']);
    $p_input = $_POST['password']; // รับรหัสผ่านตรงๆ มาก่อนเพื่อเช็ค format

    // 1. ค้นหา User จาก username
    $sql = "SELECT * FROM users WHERE username = '$u'";
    $q = mysqli_query($conn, $sql);

    if(mysqli_num_rows($q) > 0){
        $user_data = mysqli_fetch_assoc($q);
        $stored_password = $user_data['password'];

        // 2. ตรวจสอบรหัสผ่าน (รองรับทั้ง MD5 เดิม และ Password Hash ใหม่)
        $is_valid = false;
        if (md5($p_input) === $stored_password) {
            $is_valid = true;
        } elseif (password_verify($p_input, $stored_password)) {
            $is_valid = true;
        }

        if($is_valid){
            // 3. เก็บข้อมูลทุกอย่างลง Session
            $_SESSION['user_id']   = $user_data['id'];       // สำคัญ: ใช้บันทึก created_by
            $_SESSION['user']      = $user_data['username']; // ชื่อสำหรับ Login
            $_SESSION['user_name'] = $user_data['name'];     // ชื่อจริง (เช่น นางสาว ดวงพร)
            $_SESSION['role']      = $user_data['role'];     // ระดับสิทธิ์ (Admin/User)
            
            // เก็บเวลาที่ Login ไว้ด้วย (เผื่อใช้ตรวจสอบ Session Timeout)
            $_SESSION['login_time'] = time();

            header("Location: dashboard.php");
            exit;
        } else {
            $error = "รหัสผ่านไม่ถูกต้อง";
        }
    } else {
        $error = "ไม่พบชื่อผู้ใช้งานนี้ในระบบ";
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login - SALE SYSTEM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&family=Sarabun:wght@300;400;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --hotel-dark: #1a1a1a;
            --hotel-gold: #b89441;
            --hotel-gold-light: #d4af37;
        }

        body {
            background: radial-gradient(circle at center, #2c2c2c 0%, #121212 100%);
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Inter', 'Sarabun', sans-serif;
            margin: 0;
            color: #fff;
        }

        .login-card {
            width: 100%;
            max-width: 400px;
            padding: 40px;
            background: rgba(26, 26, 26, 0.9);
            border: 1px solid rgba(184, 148, 65, 0.3);
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(10px);
        }

        .login-logo {
            font-size: 2.5rem;
            color: var(--hotel-gold);
            text-align: center;
            margin-bottom: 10px;
        }

        .login-title {
            text-align: center;
            font-weight: 600;
            letter-spacing: 2px;
            margin-bottom: 30px;
            color: #fff;
            text-transform: uppercase;
        }

        .form-control {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: #fff;
            padding: 12px 15px;
            border-radius: 10px;
            transition: all 0.3s;
        }

        .form-control:focus {
            background: rgba(255, 255, 255, 0.1);
            border-color: var(--hotel-gold);
            box-shadow: 0 0 0 0.25 margin-left rgba(184, 148, 65, 0.25);
            color: #fff;
        }

        .form-control::placeholder {
            color: rgba(255, 255, 255, 0.4);
        }

        .btn-login {
            background: linear-gradient(45deg, var(--hotel-gold), var(--hotel-gold-light));
            border: none;
            color: #000;
            font-weight: 600;
            padding: 12px;
            border-radius: 10px;
            margin-top: 20px;
            text-transform: uppercase;
            letter-spacing: 1px;
            transition: all 0.3s;
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(184, 148, 65, 0.4);
            background: var(--hotel-gold-light);
            color: #000;
        }

        .error-msg {
            background: rgba(255, 0, 0, 0.1);
            color: #ff6b6b;
            padding: 10px;
            border-radius: 8px;
            text-align: center;
            font-size: 0.9rem;
            margin-bottom: 20px;
            border: 1px solid rgba(255, 0, 0, 0.2);
        }

        .footer-text {
            text-align: center;
            margin-top: 20px;
            font-size: 0.8rem;
            color: rgba(255, 255, 255, 0.4);
        }
    </style>
</head>
<body>

<div class="login-card">
    <div class="login-logo">
        <i class="bi bi-building"></i>
    </div>
    <h4 class="login-title">Sale System</h4>

    <?php if(isset($error)): ?>
        <div class="error-msg">
            <i class="bi bi-exclamation-circle me-2"></i><?php echo $error; ?>
        </div>
    <?php endif; ?>

    <form method="POST">
        <div class="mb-3">
            <label class="form-label small text-white-50">Username</label>
            <input name="username" class="form-control" placeholder="ระบุชื่อผู้ใช้งาน" required autofocus>
        </div>
        <div class="mb-4">
            <label class="form-label small text-white-50">Password</label>
            <input type="password" name="password" class="form-control" placeholder="ระบุรหัสผ่าน" required>
        </div>
        <button name="login" class="btn btn-login w-100">
            Sign In <i class="bi bi-arrow-right-short ms-1"></i>
        </button>
    </form>

    <div class="footer-text">
        &copy; 2024 Hotel Management System. All rights reserved.
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>