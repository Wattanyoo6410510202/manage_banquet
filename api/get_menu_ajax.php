<?php
include "../config.php"; 

if (isset($_GET['type_id'])) {
    $type_id = $_GET['type_id'];
    
    // Query ดึงรายการเมนู (break_menu) จากตารางที่จารเก็บข้อมูลไว้
    // สมมติชื่อตารางคือ function_breaks และจารจะดึงตาม break_type_id
    $sql = "SELECT break_menu FROM function_breaks WHERE break_type_id = ? AND break_menu IS NOT NULL AND break_menu != ''";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $type_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $menus = [];
    $i = 1;
    while ($row = $result->fetch_assoc()) {
        $menus[] = $i . ". " . trim($row['break_menu']);
        $i++;
    }

    // ถ้าเจอข้อมูล ส่งกลับเป็นรายการต่อกันด้วยขึ้นบรรทัดใหม่
    echo count($menus) > 0 ? implode("\n", $menus) : "1. ";
}
?>