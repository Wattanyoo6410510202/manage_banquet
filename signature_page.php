<?php
include "config.php";
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id <= 0) {
    die("ไม่พบรหัสเอกสาร (Invalid ID)");
}

$sql = "SELECT function_name, function_code FROM functions WHERE id = $id";
$res = $conn->query($sql);
$data = $res->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Signature - <?php echo $data['function_code']; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600&display=swap" rel="stylesheet">

    <style>
        body {
            background: #1a1a1a;
            font-family: 'Sarabun', sans-serif;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
        }

        /* Card Container */
        .sig-card {
            background: white;
            width: 95%;
            max-width: 600px;
            /* บีบขนาดให้เล็กลง ไม่เต็มจอ */
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(212, 175, 55, 0.3);
            /* ขอบทองจางๆ */
        }

        .sig-header {
            background: #212529;
            /* สีดำเข้ม */
            color: #d4af37;
            /* สีทอง */
            padding: 20px;
            text-align: center;
        }

        .sig-body {
            padding: 25px;
            text-align: center;
            background: #fff;
        }

        /* Canvas Area */
        .canvas-container {
            position: relative;
            background: #fafafa;
            border: 2px solid #eee;
            border-radius: 12px;
            margin-bottom: 20px;
            line-height: 0;
        }

        #signature-pad {
            width: 100%;
            height: 250px;
            /* ล็อกความสูงให้พอดีๆ */
            cursor: crosshair;
            touch-action: none;
        }

        .hint-text {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            color: #ddd;
            font-size: 1rem;
            pointer-events: none;
            user-select: none;
        }

        /* Buttons */
        .btn-gold {
            background: #d4af37;
            color: #fff;
            border: none;
            font-weight: 600;
            padding: 10px 25px;
            border-radius: 10px;
            transition: 0.3s;
        }

        .btn-gold:hover {
            background: #b8952e;
            color: white;
            transform: translateY(-2px);
        }

        .btn-outline-secondary {
            border-radius: 10px;
            border: 1px solid #ddd;
        }

        .info-box {
            font-size: 0.85rem;
            color: #666;
            margin-bottom: 15px;
            padding: 10px;
            background: #fff9e6;
            border-radius: 8px;
            border-left: 4px solid #d4af37;
        }

        /* ปรับปุ่มให้ยืดหยุ่น */
        .d-flex.gap-2 {
            flex-wrap: wrap;
        }

        @media (max-width: 576px) {
            .btn {
                flex: 1 1 auto;
                font-size: 0.9rem;
            }
        }
    </style>
</head>

<body>

    <div class="sig-card">
        <div class="sig-header">
            <i class="bi bi-pen-fill fs-4"></i>
            <div class="fw-bold mt-1">E-Signature Confirmation</div>
        </div>

        <div class="sig-body">
            <div class="info-box text-start">
                <strong>โครงการ:</strong> <?php echo $data['function_name']; ?><br>
                <strong>ID:</strong> <?php echo $data['function_code']; ?>
            </div>

            <div class="canvas-container">
                <div class="hint-text" id="hint">ลงลายมือชื่อภายในกรอบนี้</div>
                <canvas id="signature-pad"></canvas>
            </div>

            <input type="file" id="file-input" accept="image/*" style="display: none;">

            <div class="d-flex gap-2 justify-content-center flex-wrap">
                <button type="button" class="btn btn-outline-secondary px-3"
                    onclick="document.getElementById('file-input').click()">
                    <i class="bi bi-upload"></i> นำเข้าภาพ
                </button>
                <button type="button" class="btn btn-outline-secondary px-3" onclick="clearPad()">
                    <i class="bi bi-arrow-counterclockwise"></i> ล้าง
                </button>
                <button type="button" class="btn btn-gold px-4" onclick="savePad()">
                    <i class="bi bi-check2-circle"></i> บันทึกลายเซ็น
                </button>
            </div>


            <div class="mt-4">
                <a href="javascript:history.back()" class="text-muted small text-decoration-none">
                    <i class="bi bi-x-circle"></i> ยกเลิกและกลับไปหน้าเดิม
                </a>
            </div>
        </div>
    </div>

    <script>
        const canvas = document.getElementById('signature-pad');
        const hint = document.getElementById('hint');
        const ctx = canvas.getContext('2d');
        let drawing = false;
        let hasSigned = false;

        function initCanvas() {
            const ratio = window.devicePixelRatio || 1;
            // ใช้ความกว้างจริงของ Container
            canvas.width = canvas.offsetWidth * ratio;
            canvas.height = canvas.offsetHeight * ratio;

            ctx.scale(ratio, ratio);
            ctx.lineWidth = 3;
            ctx.lineCap = 'round';
            ctx.strokeStyle = '#222';
        }

        // เรียกครั้งเดียวตอนโหลด
        window.onload = initCanvas;

        function getPos(e) {
            const rect = canvas.getBoundingClientRect();
            const clientX = e.clientX || (e.touches ? e.touches[0].clientX : 0);
            const clientY = e.clientY || (e.touches ? e.touches[0].clientY : 0);
            return { x: clientX - rect.left, y: clientY - rect.top };
        }

        function startDrawing(e) {
            drawing = true;
            hasSigned = true;
            hint.style.opacity = '0';
            ctx.beginPath();
            const pos = getPos(e);
            ctx.moveTo(pos.x, pos.y);
            if (e.cancelable) e.preventDefault();
        }

        function draw(e) {
            if (!drawing) return;
            const pos = getPos(e);
            ctx.lineTo(pos.x, pos.y);
            ctx.stroke();
            if (e.cancelable) e.preventDefault();
        }

        canvas.addEventListener('mousedown', startDrawing);
        canvas.addEventListener('mousemove', draw);
        window.addEventListener('mouseup', () => drawing = false);

        canvas.addEventListener('touchstart', startDrawing, { passive: false });
        canvas.addEventListener('touchmove', draw, { passive: false });
        canvas.addEventListener('touchend', () => drawing = false);

        function clearPad() {
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            hint.style.opacity = '1';
            hasSigned = false;
        }

        function savePad() {
            if (!hasSigned) {
                alert("กรุณาเซ็นชื่อก่อนครับ");
                return;
            }

            const dataURL = canvas.toDataURL('image/png');
            const btn = document.querySelector('.btn-gold');
            btn.disabled = true;
            btn.innerHTML = 'กำลังบันทึก...';

            fetch('api/save_signature.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ image: dataURL, id: <?php echo $id; ?> })
            })
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'success') {
                        // ✅ ใช้ location.replace เพื่อไม่ให้กดย้อนกลับมาหน้าเซ็นชื่อนี้ได้อีก
                        window.location.replace('view.php?id=<?php echo $id; ?>');
                    } else {
                        alert("Error: " + data.message);
                        btn.disabled = false;
                        btn.innerHTML = 'บันทึกลายเซ็น';
                    }
                })
                .catch(err => {
                    console.error(err);
                    alert("เกิดข้อผิดพลาดในการเชื่อมต่อ");
                    btn.disabled = false;
                });
        }

        window.onpageshow = function (event) {
            if (event.persisted) {
                window.location.reload();
            }
        };

        const fileInput = document.getElementById('file-input');

        fileInput.addEventListener('change', function (e) {
            const file = e.target.files[0];
            if (!file) return;

            const reader = new FileReader();
            reader.onload = function (event) {
                const img = new Image();
                img.onload = function () {
                    // ล้างหน้าจอก่อนวาดภาพที่นำเข้า
                    clearPad();

                    // คำนวณขนาดภาพให้พอดีกับ Canvas (Maintain Aspect Ratio)
                    const canvasRatio = canvas.offsetWidth / canvas.offsetHeight;
                    const imgRatio = img.width / img.height;

                    let drawW, drawH;
                    if (imgRatio > canvasRatio) {
                        drawW = canvas.offsetWidth * 0.8; // ให้เล็กลงหน่อยไม่ชิดขอบ
                        drawH = drawW / imgRatio;
                    } else {
                        drawH = canvas.offsetHeight * 0.8;
                        drawW = drawH * imgRatio;
                    }

                    const x = (canvas.offsetWidth - drawW) / 2;
                    const y = (canvas.offsetHeight - drawH) / 2;

                    // วาดภาพลง Canvas
                    ctx.drawImage(img, x, y, drawW, drawH);

                    hint.style.opacity = '0';
                    hasSigned = true; // ถือว่าเซ็นแล้ว
                }
                img.src = event.target.result;
            }
            reader.readAsDataURL(file);
        });
    </script>

</body>

</html>