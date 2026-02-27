<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Access Denied - ปฏิเสธการเข้าถึง</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body {
            background-color: #1a1a1a;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .error-card {
            max-width: 500px;
            width: 100%;
            text-align: center;
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            background: white;
        }

        .icon-box {
            font-size: 80px;
            color: #dc3545;
            margin-bottom: 20px;
        }
    </style>
</head>

<body>

    <div class="error-card">
        <div class="icon-box">
            <i class="bi bi-shield-lock-fill"></i>
        </div>
        <h1 class="fw-bold text-dark">เข้าถึงไม่ได้!</h1>
        <p class="text-muted mb-4">ขออภัยครับ หน้าเอกสารนี้จำกัดเฉพาะสิทธิ์ที่ได้รับอนุญาตเท่านั้น
            คุณไม่มีสิทธิ์เข้าดูในส่วนนี้</p>

        <div class="alert alert-warning border-0 small">
            ระบบจะพาคุณกลับไปหน้าหลักภายใน <span id="countdown" class="fw-bold">5</span> วินาที...
        </div>
    </div>

    <script>
        // ระบบนับถอยหลัง 5 วินาที
        let timeLeft = 5;
        const countdownEl = document.getElementById('countdown');

        const timer = setInterval(() => {
            timeLeft--;
            countdownEl.textContent = timeLeft;
            if (timeLeft <= 0) {
                clearInterval(timer);
                // --- แก้ตรงนี้ครับจาร ---
                window.history.back(); // ถอยหลังกลับไปหน้าก่อนหน้าที่เขามา
            }
        }, 1000);
    </script>

</body>

</html>