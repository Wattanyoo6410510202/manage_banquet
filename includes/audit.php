<?php
// includes/audit.php
// บันทึกประวัติการใช้งาน (Audit Log) — ดูได้ที่ ตั้งค่า > Audit Log
// ห้ามทำให้งานหลักพัง: ถ้าบันทึกไม่ได้ (เช่นยังไม่ได้รัน migration) ให้เงียบไว้แล้วเขียน error_log แทน
function audit_log($conn, $action, $entity_type = null, $entity_id = null, $detail = '')
{
    try {
        $user_id   = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : null;
        $user_name = $_SESSION['user_name'] ?? ($_SESSION['user'] ?? null);
        $role      = $_SESSION['role'] ?? null;
        $ip        = $_SERVER['REMOTE_ADDR'] ?? null;
        if (is_array($entity_id)) $entity_id = implode(',', $entity_id);
        $entity_id = $entity_id === null ? null : (string)$entity_id;

        $stmt = $conn->prepare("INSERT INTO audit_logs (user_id, user_name, role, action, entity_type, entity_id, detail, ip_address)
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        if (!$stmt) {
            error_log('[AUDIT] prepare failed: ' . $conn->error);
            return;
        }
        $stmt->bind_param("isssssss", $user_id, $user_name, $role, $action, $entity_type, $entity_id, $detail, $ip);
        $stmt->execute();
    } catch (Throwable $e) {
        error_log('[AUDIT] ' . $e->getMessage());
    }
}
