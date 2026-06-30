<?php
include "config.php";

$id = intval($_GET['id'] ?? 0);
$compare_id = intval($_GET['compare_id'] ?? 0);

if (!$id || !$compare_id) {
    die("Missing IDs");
}

function getData($conn, $id) {
    $sql = "SELECT f.*, r.room_name, ft.type_name 
            FROM functions f 
            LEFT JOIN meeting_rooms r ON f.room_id = r.id
            LEFT JOIN function_types ft ON f.function_type_id = ft.id
            WHERE f.id = $id";
    $res = $conn->query($sql);
    return $res ? $res->fetch_assoc() : null;
}

function getSchedules($conn, $id) {
    $res = $conn->query("SELECT * FROM function_schedules WHERE function_id = $id ORDER BY id ASC");
    $data = [];
    while ($r = $res->fetch_assoc()) $data[] = $r;
    return $data;
}

function getKitchens($conn, $id) {
    $res = $conn->query("SELECT * FROM function_kitchens WHERE function_id = $id ORDER BY id ASC");
    $data = [];
    while ($r = $res->fetch_assoc()) $data[] = $r;
    return $data;
}

function getMenus($conn, $id) {
    $res = $conn->query("SELECT * FROM function_menus WHERE function_id = $id ORDER BY id ASC");
    $data = [];
    while ($r = $res->fetch_assoc()) $data[] = $r;
    return $data;
}

$a = getData($conn, $id);
$b = getData($conn, $compare_id);

if (!$a || !$b) {
    die("Draft not found");
}

$compare_fields = [
    'function_name' => 'ชื่องาน',
    'type_name' => 'ประเภทงาน',
    'room_name' => 'ห้อง',
    'start_time' => 'เวลาเริ่ม',
    'end_time' => 'เวลาสิ้นสุด',
    'total_amount' => 'มูลค่างาน',
    'deposit' => 'มัดจำ',
    'booking_name' => 'ผู้จอง',
    'phone' => 'เบอร์โทร',
    'organization' => 'ที่อยู่',
    'draft_name' => 'ชื่อ Draft',
    'booking_room' => 'Booking No.',
    'pax' => 'จำนวน (PAX)',
    'banquet_style' => 'การจัดงานเลี้ยง',
    'equipment' => 'งานช่างและภาพเสียง',
    'remark' => 'หมายเหตุ',
    'backdrop_detail' => 'ฉากหลัง',
    'hk_florist_detail' => 'ทำความสะอาด/ดอกไม้',
    'lead_source' => 'ที่มา Lead',
    'result' => 'ผลการดำเนินงาน',
    'inspection_date' => 'Inspection',
    'follow_up_date' => 'Follow Up',
];

$differences = [];

foreach ($compare_fields as $field => $label) {
    $val_a = $a[$field] ?? '';
    $val_b = $b[$field] ?? '';
    if ((string)$val_a !== (string)$val_b) {
        $differences[] = [
            'label' => $label,
            'old_value' => $val_b,
            'new_value' => $val_a,
        ];
    }
}

$sched_a = getSchedules($conn, $id);
$sched_b = getSchedules($conn, $compare_id);
if (json_encode($sched_a) !== json_encode($sched_b)) {
    $differences[] = [
        'label' => 'ตารางกำหนดการ',
        'old_value' => count($sched_b) . ' รายการ',
        'new_value' => count($sched_a) . ' รายการ',
    ];
}

$kit_a = getKitchens($conn, $id);
$kit_b = getKitchens($conn, $compare_id);
if (json_encode($kit_a) !== json_encode($kit_b)) {
    $differences[] = [
        'label' => 'รายการครัว',
        'old_value' => count($kit_b) . ' รายการ',
        'new_value' => count($kit_a) . ' รายการ',
    ];
}

$menu_a = getMenus($conn, $id);
$menu_b = getMenus($conn, $compare_id);
if (json_encode($menu_a) !== json_encode($menu_b)) {
    $differences[] = [
        'label' => 'รายการอาหาร',
        'old_value' => count($menu_b) . ' รายการ',
        'new_value' => count($menu_a) . ' รายการ',
    ];
}
// ── Excel Export ──
if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="compare_drafts_' . date('Ymd') . '.xls"');
    header('Pragma: no-cache');
    header('Expires: 0');
    ?>
    <html xmlns:o="urn:schemas-microsoft-com:office:office"
          xmlns:x="urn:schemas-microsoft-com:office:excel"
          xmlns="http://www.w3.org/TR/REC-html40">
    <head><meta charset="utf-8">
    <!--[if gte mso 9]>
    <xml>
        <x:ExcelWorkbook>
            <x:ExcelWorksheets>
                <x:ExcelWorksheet>
                    <x:Name>เปรียบเทียบ Draft</x:Name>
                    <x:WorksheetOptions><x:DisplayGridlines/></x:WorksheetOptions>
                </x:ExcelWorksheet>
            </x:ExcelWorksheets>
        </x:ExcelWorkbook>
    </xml>
    <![endif]-->
    <style>
        table { border-collapse: collapse; font-family: 'Sarabun', 'Tahoma', sans-serif; font-size: 12px; }
        th, td { border: 1px solid #000; padding: 6px 8px; vertical-align: top; }
        th { background: #4472C4; color: #fff; font-weight: bold; text-align: center; }
        .hdr { font-size: 16px; font-weight: bold; text-align: center; border: none; padding: 4px; }
        .sub { font-size: 11px; text-align: center; border: none; padding: 2px; }
        .label-col { font-weight: bold; background: #f0f0f0; }
        .old-col { background: #fff5f5; color: #b91c1c; }
        .new-col { background: #f0fff4; color: #166534; }
        .sign-th { background: #e0e0e0; font-weight: bold; text-align: center; }
        .sign-td { text-align: center; height: 50px; }
    </style>
    </head><body>
    <table>
        <tr><td colspan="4" class="hdr">รายงานเปรียบเทียบการเปลี่ยนแปลง</td></tr>
        <tr><td colspan="4" class="sub">วันที่พิมพ์: <?= date('d/m/Y H:i') ?></td></tr>
        <tr><td colspan="4" class="sub">ก่อน: <?= htmlspecialchars($b['draft_name']) ?> (v<?= $b['version_no'] ?? '-' ?>) → หลัง: <?= htmlspecialchars($a['draft_name']) ?> (v<?= $a['version_no'] ?? '-' ?>)</td></tr>
        <tr><td colspan="4" style="height:8px;border:none;"></td></tr>
        <tr>
            <th style="width:120px;">รายการ</th>
            <th>ก่อน</th>
            <th>หลัง</th>
        </tr>
        <?php if (empty($differences)): ?>
            <tr><td colspan="3" style="text-align:center;">ไม่พบการเปลี่ยนแปลง</td></tr>
        <?php else: ?>
            <?php foreach ($differences as $diff): ?>
            <tr>
                <td class="label-col"><?= htmlspecialchars($diff['label']) ?></td>
                <td class="old-col"><?= htmlspecialchars($diff['old_value'] ?: '-') ?></td>
                <td class="new-col"><?= htmlspecialchars($diff['new_value'] ?: '-') ?></td>
            </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        <tr><td colspan="3" style="height:8px;border:none;"></td></tr>
        <tr>
            <th class="sign-th">แผนก</th>
            <th class="sign-th">ชื่อ-นามสกุล</th>
            <th class="sign-th">วันที่รับทราบ</th>
        </tr>
        <?php $depts = ['ครัว (Kitchen)', 'ฝ่ายช่างเทคนิค', 'แม่บ้าน (Housekeeping)', 'ฝ่ายขาย (Sale)', 'ผู้จัดการ (Manager)']; ?>
        <?php foreach ($depts as $dept): ?>
        <tr>
            <td class="sign-td"><?= $dept ?></td>
            <td class="sign-td"></td>
            <td class="sign-td"></td>
        </tr>
        <?php endforeach; ?>
    </table>
    </body></html>
    <?php
    exit;
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>เปรียบเทียบ Draft</title>
    <style>
        @page { margin: 15mm; size: A4; }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Sarabun', 'Tahoma', 'Segoe UI', sans-serif;
            font-size: 12px;
            color: #222;
            padding: 20px;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #333;
        }
        .header h2 { font-size: 18px; margin-bottom: 5px; }
        .header .sub { font-size: 13px; color: #555; }
        .info-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #f5f5f5;
            padding: 10px 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 13px;
        }
        .info-bar .badge-old {
            background: #dc3545;
            color: #fff;
            padding: 3px 10px;
            border-radius: 4px;
            font-weight: bold;
            font-size: 12px;
        }
        .info-bar .badge-new {
            background: #198754;
            color: #fff;
            padding: 3px 10px;
            border-radius: 4px;
            font-weight: bold;
            font-size: 12px;
        }
        .info-bar .arrow {
            font-size: 20px;
            color: #888;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        th, td {
            border: 1px solid #ccc;
            padding: 8px 10px;
            text-align: left;
            vertical-align: top;
        }
        th {
            background: #f0f0f0;
            font-weight: bold;
            font-size: 11px;
        }
        .label-col { width: 120px; font-weight: bold; background: #fafafa; }
        .old-col { background: #fff5f5; color: #b91c1c; }
        .new-col { background: #f0fff4; color: #166534; }
        .no-change { text-align: center; color: #888; padding: 30px; }
        .sign-table { margin-top: 40px; }
        .sign-table th { background: #e0e0e0; text-align: center; }
        .sign-table td { text-align: center; height: 70px; vertical-align: middle; }
        .footer {
            text-align: center;
            margin-top: 30px;
            font-size: 10px;
            color: #999;
            border-top: 1px solid #ddd;
            padding-top: 10px;
        }
        @media print {
            body { padding: 0; }
            .no-print { display: none !important; }
        }
    </style>
</head>
<body>

    <div class="no-print" style="text-align:right; margin-bottom:15px;">
        <a href="?id=<?= $id ?>&compare_id=<?= $compare_id ?>&export=excel" style="padding:8px 20px; font-size:14px; cursor:pointer; text-decoration:none; background:#198754; color:#fff; border:none; border-radius:4px; margin-right:5px;">
            <i class="bi bi-file-earmark-excel"></i> Export Excel
        </a>
        <button onclick="window.print()" style="padding:8px 20px; font-size:14px; cursor:pointer;">
            <i class="bi bi-printer"></i> พิมพ์ (Print)
        </button>
        <button onclick="window.close()" style="padding:8px 20px; font-size:14px; cursor:pointer; margin-left:5px;">
            ปิด
        </button>
    </div>

    <div class="header">
        <h2>รายงานเปรียบเทียบการเปลี่ยนแปลง</h2>
        <div class="sub">วันที่พิมพ์: <?= date('d/m/Y H:i') ?></div>
    </div>

    <div class="info-bar">
        <div>
            <span class="badge-old">ก่อน</span>
            <strong style="margin-left:5px;"><?= htmlspecialchars($b['draft_name']) ?></strong>
            <div style="font-size:11px;color:#888;margin-top:2px;">v<?= $b['version_no'] ?? '-' ?></div>
        </div>
        <div class="arrow">→</div>
        <div>
            <span class="badge-new">หลัง</span>
            <strong style="margin-left:5px;"><?= htmlspecialchars($a['draft_name']) ?></strong>
            <div style="font-size:11px;color:#888;margin-top:2px;">v<?= $a['version_no'] ?? '-' ?></div>
        </div>
    </div>

    <?php if (empty($differences)): ?>
        <div class="no-change">ไม่พบการเปลี่ยนแปลงระหว่าง Draft ทั้งสอง</div>
    <?php else: ?>
        <div style="margin-bottom:10px;font-size:13px;">
            พบการเปลี่ยนแปลงทั้งสิ้น <strong><?= count($differences) ?></strong> รายการ
        </div>
        <table>
            <thead>
                <tr>
                    <th class="label-col">รายการ</th>
                    <th style="width:40%;">ก่อน</th>
                    <th style="width:40%;">หลัง</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($differences as $diff): ?>
                    <tr>
                        <td class="label-col"><?= htmlspecialchars($diff['label']) ?></td>
                        <td class="old-col"><?= htmlspecialchars($diff['old_value'] ?: '-') ?></td>
                        <td class="new-col"><?= htmlspecialchars($diff['new_value'] ?: '-') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <table class="sign-table">
        <thead>
            <tr>
                <th style="width:20%;">แผนก</th>
                <th style="width:25%;">ชื่อ-นามสกุล</th>
                <th style="width:30%;">ลายเซ็น</th>
                <th style="width:25%;">วันที่รับทราบ</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>ครัว (Kitchen)</td>
                <td></td>
                <td></td>
                <td></td>
            </tr>
            <tr>
                <td>ฝ่ายช่างเทคนิค</td>
                <td></td>
                <td></td>
                <td></td>
            </tr>
            <tr>
                <td>แม่บ้าน (Housekeeping)</td>
                <td></td>
                <td></td>
                <td></td>
            </tr>
            <tr>
                <td>ฝ่ายขาย (Sale)</td>
                <td></td>
                <td></td>
                <td></td>
            </tr>
            <tr>
                <td>ผู้จัดการ (Manager)</td>
                <td></td>
                <td></td>
                <td></td>
            </tr>
        </tbody>
    </table>

    <div class="footer">
        ระบบจัดการงานจัดเลี้ยง - รายงานเปรียบเทียบ Draft
    </div>

</body>
</html>