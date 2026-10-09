<?php
include "config.php";
// เริ่ม session หากยังไม่ได้เริ่ม
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (isset($_POST['login'])) {
    $u = mysqli_real_escape_string($conn, $_POST['username']);
    $p_input = $_POST['password'];

    $sql = "SELECT * FROM users WHERE username = '$u' LIMIT 1";
    $q = mysqli_query($conn, $sql);

    if ($q && mysqli_num_rows($q) > 0) {
        $user_data = mysqli_fetch_assoc($q);
        $stored_password = $user_data['password'];

        $is_valid = false;
        // เช็ค MD5 (สำหรับข้อมูลเก่า) หรือ Password Hash (สำหรับข้อมูลใหม่)
        if (md5($p_input) === $stored_password || password_verify($p_input, $stored_password)) {
            $is_valid = true;
        }

        if ($is_valid) {
            // สร้าง Session ID ใหม่ทุกครั้งที่ Login สำเร็จ (ปลอดภัยกว่า)
            session_regenerate_id(true);

            $_SESSION['user_id'] = $user_data['id'];
            $_SESSION['user'] = $user_data['username'];
            $_SESSION['user_name'] = $user_data['name'];
            $_SESSION['role'] = $user_data['role']; // ค่าใน DB ควรเป็น Admin, GM, Staff
            $_SESSION['login_time'] = time();

            header("Location: calendar.php");
            exit;
        } else {
            $error = "รหัสผ่านไม่ถูกต้อง";
        }
    } else {
        $error = "ไม่พบชื่อผู้ใช้งานนี้ในระบบ";
    }
}

if (!isset($error)) {
    $error_messages = [
        'pls_login'      => 'กรุณาเข้าสู่ระบบก่อนใช้งาน',
        'access_denied'  => 'คุณไม่มีสิทธิ์เข้าถึงหน้านั้น กรุณาเข้าสู่ระบบด้วยบัญชีที่มีสิทธิ์',
    ];
    $error_code = $_GET['error'] ?? '';
    if (isset($error_messages[$error_code])) {
        $error = $error_messages[$error_code];
    }
}
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>เข้าสู่ระบบ · Banquet Management</title>
    <link rel="icon" type="image/png" href="logo.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Thai:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/theme.css?v=<?php echo @filemtime(__DIR__ . '/assets/css/theme.css'); ?>">
    <style>
        body {
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 24px 16px;
            background:
                radial-gradient(900px 500px at 85% -10%, var(--gold-100), transparent 60%),
                radial-gradient(700px 420px at -10% 110%, #efe9dc, transparent 60%),
                var(--bg);
        }
        .login-card {
            width: 100%;
            max-width: 400px;
            background: var(--surface);
            border: 1px solid var(--line);
            border-radius: var(--r-xl);
            box-shadow: var(--shadow-md);
            padding: 36px 32px 28px;
        }
        .login-brand { display: flex; flex-direction: column; align-items: center; gap: 12px; margin-bottom: 26px; text-align: center; }
        .login-brand .mark {
            width: 52px; height: 52px; border-radius: 14px;
            display: grid; place-items: center;
            background: linear-gradient(140deg, #cfae5d, #9a7a2e);
            color: #fff; font-size: 24px;
        }
        .login-brand h1 { font-size: var(--fs-xl); margin: 0; }
        .login-brand p { margin: 0; color: var(--muted); }
        .login-card .form-control { min-height: 44px; padding-left: 40px; }
        .field { position: relative; }
        .field > i { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: var(--faint); font-size: 16px; pointer-events: none; }
        .toggle-pass { position: absolute; right: 6px; top: 50%; transform: translateY(-50%); border: 0; background: transparent; color: var(--muted); width: 34px; height: 34px; border-radius: var(--r); }
        .toggle-pass:hover { background: var(--sunken); }
        .login-foot { text-align: center; margin-top: 22px; font-size: var(--fs-xs); color: var(--faint); }
    </style>
</head>

<body>
    <main class="login-card">
        <div class="login-brand">
            <span class="mark"><i class="bi bi-building"></i></span>
            <div>
                <h1>Banquet Management</h1>
                <p>เข้าสู่ระบบเพื่อจัดการงานจัดเลี้ยงและห้องประชุม</p>
            </div>
        </div>

        <?php if (isset($error)): ?>
            <div class="alert alert-danger d-flex align-items-center gap-2 py-2" role="alert">
                <i class="bi bi-exclamation-circle"></i><span><?php echo $error; ?></span>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="mb-3">
                <label class="form-label" for="username">ชื่อผู้ใช้</label>
                <div class="field">
                    <i class="bi bi-person"></i>
                    <input id="username" name="username" class="form-control" placeholder="Username" autocomplete="username" required autofocus>
                </div>
            </div>
            <div class="mb-4">
                <label class="form-label" for="password">รหัสผ่าน</label>
                <div class="field">
                    <i class="bi bi-lock"></i>
                    <input id="password" type="password" name="password" class="form-control" placeholder="Password" autocomplete="current-password" required>
                    <button type="button" class="toggle-pass" aria-label="แสดงรหัสผ่าน"
                        onclick="const p=document.getElementById('password');p.type=p.type==='password'?'text':'password';this.firstElementChild.className=p.type==='password'?'bi bi-eye':'bi bi-eye-slash';">
                        <i class="bi bi-eye"></i>
                    </button>
                </div>
            </div>
            <button name="login" class="btn btn-primary btn-lg w-100">
                เข้าสู่ระบบ <i class="bi bi-arrow-right"></i>
            </button>
        </form>

        <div class="login-foot">&copy; 2024 HMS. Wattanyoo641051020 All rights reserved.</div>
    </main>
</body>

</html>
