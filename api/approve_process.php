<?php
include "../config.php";

if (isset($_GET['id'])) {
    // Check role — only admin/gm/manager can approve quotations
    $role = strtolower($_SESSION['role'] ?? '');
    if (!in_array($role, ['admin', 'gm', 'manager'])) {
        header("Location: ../quotation_list.php?status=forbidden");
        exit();
    }

    $id = intval($_GET['id']);
    $admin_id = intval($_SESSION['user_id'] ?? 0);

    if ($id <= 0) {
        header("Location: ../quotation_list.php?status=error");
        exit();
    }

    try {
        $sql = "UPDATE quotations SET 
                status = 'Approved', 
                approved_by = ?, 
                approved_at = NOW(),
                updated_at = NOW()
                WHERE id = ?";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $admin_id, $id);
        $stmt->execute();
        header("Location: ../quotation_list.php");
    } catch (Exception $e) {
        header("Location: ../quotation_list.php?status=db_error&msg=" . urlencode($e->getMessage()));
    }
    exit();
}