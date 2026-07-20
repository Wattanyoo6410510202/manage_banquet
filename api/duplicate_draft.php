<?php
session_start();
include "../config.php";

if (!isset($_SESSION['user_name'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$function_id = intval($_POST['id'] ?? 0);

if ($function_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid ID']);
    exit;
}

$conn->begin_transaction();

try {
    // 1. ดึงข้อมูล Draft ต้นฉบับ
    $sql = "SELECT * FROM functions WHERE id = $function_id";
    $res = $conn->query($sql);
    if ($res->num_rows == 0) throw new Exception("Draft not found");
    $original = $res->fetch_assoc();

    $project_id = $original['project_id'];
    
    // 2. หา Version ล่าสุดของ Project นี้
    $ver_sql = "SELECT MAX(version_no) as max_ver FROM functions WHERE project_id = $project_id";
    $ver_res = $conn->query($ver_sql);
    $ver_row = $ver_res->fetch_assoc();
    $new_version = intval($ver_row['max_ver'] ?? 0) + 1;

    // 3. คัดลอกข้อมูล (ยกเว้น ID และข้อมูลบางอย่างที่ต้องรันใหม่)
    $columns_sql = "SHOW COLUMNS FROM functions";
    $cols_res = $conn->query($columns_sql);
    $cols = [];
    while ($c = $cols_res->fetch_assoc()) {
        if ($c['Field'] != 'id' && $c['Field'] != 'modify' && $c['Field'] != 'created_at') {
            $cols[] = $c['Field'];
        }
    }

    $col_names = implode(", ", $cols);
    $new_draft_name = "Draft V" . $new_version . " (Copy)";
    
    // เตรียม Query สำหรับ Insert
    // เราจะระบุค่าบางตัวเอง เช่น version_no, is_approved, draft_name, approve, function_code
    $insert_sql = "INSERT INTO functions ($col_names) SELECT $col_names FROM functions WHERE id = $function_id";
    $conn->query($insert_sql);
    $new_id = $conn->insert_id;

    // 4. Update ค่าที่ต้องเปลี่ยน
    $conn->query("UPDATE functions SET 
                  version_no = $new_version, 
                  is_approved = 0, 
                  approve = 0, 
                  draft_name = '$new_draft_name',
                  function_code = CONCAT(LPAD($new_id, 5, '0'), '/', DATE_FORMAT(NOW(), '%d%m'))
                  WHERE id = $new_id");

    // 5. คัดลอกตารางลูก (Schedules, Kitchens, Menus)
    $conn->query("INSERT INTO function_schedules (function_id, schedule_date, schedule_hour, schedule_function, schedule_guarantee)
                  SELECT $new_id, schedule_date, schedule_hour, schedule_function, schedule_guarantee FROM function_schedules WHERE function_id = $function_id");
                  
    $conn->query("INSERT INTO function_kitchens (function_id, k_date, k_type_id, k_item, k_qty, k_price, k_cost, k_remark)
                  SELECT $new_id, k_date, k_type_id, k_item, k_qty, k_price, k_cost, k_remark FROM function_kitchens WHERE function_id = $function_id");
                  
    $conn->query("INSERT INTO function_menus (function_id, menu_time, menu_set_id, menu_detail, menu_qty, menu_price, menu_cost)
                  SELECT $new_id, menu_time, menu_set_id, menu_detail, menu_qty, menu_price, menu_cost FROM function_menus WHERE function_id = $function_id");

    $conn->commit();

    // LINE แจ้งเตือนมี Draft ใหม่ (Flex Message)
    include_once __DIR__ . "/../line_helper.php";
    $origin = $_SERVER['HTTP_ORIGIN'] ?? (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
    $draftFlex = buildDraftFlex($original['function_name'], $new_draft_name, $origin);
    $lineSent = 0;
    $lineFailed = 0;
    foreach (['admin', 'gm'] as $role) {
        $result = sendLineFlexToRole($conn, $role, $draftFlex, '📝 มี Draft ใหม่: ' . $new_draft_name);
        $lineSent += $result['sent'];
        $lineFailed += $result['failed'];
    }

    echo json_encode(['status' => 'success', 'new_id' => $new_id, 'line_sent' => $lineSent, 'line_failed' => $lineFailed]);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>