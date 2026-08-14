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

        // LINE แจ้งคนที่สร้างใบเสนอราคาว่าอนุมัติแล้ว + แจ้ง admin ไว้เป็นสำเนา
        try {
            include_once __DIR__ . "/../line_helper.php";

            $q = $conn->prepare("SELECT q.quote_no, q.event_name, q.event_date, q.grand_total, q.created_by,
                                        cust.cust_name
                                 FROM quotations q
                                 LEFT JOIN customers cust ON q.customer_id = cust.id
                                 WHERE q.id = ?");
            $q->bind_param("i", $id);
            $q->execute();
            $qt = $q->get_result()->fetch_assoc();

            if ($qt) {
                $origin = lineOriginUrl();
                $approveName = $_SESSION['user_name'] ?? 'GM';
                $flex = buildNotifyFlex([
                    'title'    => '✅ ใบเสนอราคาอนุมัติแล้ว',
                    'subtitle' => 'Quotation Approved',
                    'color'    => '#1DB446',
                    'rows'     => [
                        ['📌', $qt['event_name'], '#111111'],
                        ['🧾', $qt['quote_no'], '#111111'],
                        ['👤', $qt['cust_name']],
                        ['📅 วันจัดงาน', !empty($qt['event_date']) ? date('d/m/Y', strtotime($qt['event_date'])) : '-'],
                        ['💰 ยอดรวม', number_format($qt['grand_total'], 2) . ' บาท', '#111111'],
                        ['👨‍💼 อนุมัติโดย', $approveName, '#1DB446'],
                    ],
                    'note'     => 'ส่งให้ลูกค้าได้แล้ว และเปิดเป็น EO ต่อได้เลย',
                    'buttons'  => [
                        ['ดูใบเสนอราคา', $origin . '/manage_banquet/quotation_view.php?id=' . $id],
                        ['📆 เปิดเป็น EO', $origin . '/manage_banquet/add_event.php?quote_id=' . $id],
                    ],
                ]);
                $alt = '✅ ใบเสนอราคาอนุมัติแล้ว: ' . $qt['event_name'];

                sendLineFlexToUserId($conn, $qt['created_by'], $flex, $alt);
                sendLineFlexToRoles($conn, ['admin'], $flex, $alt);
            }
        } catch (Throwable $e) {
            error_log('[LINE] quotation approved notify failed: ' . $e->getMessage());
        }

        header("Location: ../quotation_list.php");
    } catch (Exception $e) {
        header("Location: ../quotation_list.php?status=db_error&msg=" . urlencode($e->getMessage()));
    }
    exit();
}