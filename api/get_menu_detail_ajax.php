<?php
include "../config.php";

if (isset($_GET['set_id'])) {
    $set_id = $_GET['set_id'];
    // Query รายละเอียดเมนูออกมาเป็นบรรทัดๆ
    $sql = "SELECT menu_items FROM function_menu_details WHERE menu_type_id = ? ORDER BY id ASC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $set_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $items = [];
    while ($row = $result->fetch_assoc()) {
        $items[] = "- " . $row['menu_items'];
    }
    echo implode("\n", $items);
}
?>