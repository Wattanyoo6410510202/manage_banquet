<?php
include "config.php";
require_once "header.php";

// --- 1. ส่วนการจัดการข้อมูล (API) ---
if (isset($_POST['action'])) {
    $id = intval($_POST['id'] ?? 0);
    $menu_type_id = intval($_POST['menu_type_id'] ?? 0); 
    $menu_items = $conn->real_escape_string($_POST['menu_items'] ?? '');
    $beverage_detail = $conn->real_escape_string($_POST['beverage_detail'] ?? '');
    $guarantee_pax = intval($_POST['guarantee_pax'] ?? 0);
    $price_per_pax = floatval($_POST['price_per_pax'] ?? 0);

    // กรณี: บันทึก หรือ แก้ไข
    if ($_POST['action'] == 'save') {
        if ($id > 0) {
            $sql = "UPDATE function_menu_details SET 
                    menu_type_id=$menu_type_id, 
                    menu_items='$menu_items', 
                    beverage_detail='$beverage_detail', 
                    guarantee_pax=$guarantee_pax, 
                    price_per_pax=$price_per_pax 
                    WHERE id=$id";
        } else {
            $sql = "INSERT INTO function_menu_details (menu_type_id, menu_items, beverage_detail, guarantee_pax, price_per_pax) 
                    VALUES ($menu_type_id, '$menu_items', '$beverage_detail', $guarantee_pax, $price_per_pax)";
        }

        if ($conn->query($sql)) {
            // ใช้ JavaScript เด้งกลับพร้อมส่ง Parameter ไปโชว์ Alert สำเร็จ (ถ้ามีระบบ alert)
            echo "<script>window.location.href='food_management.php?msg=success';</script>";
        } else {
            echo "Error: " . $conn->error;
        }
        exit;
    }

    // กรณี: ลบข้อมูล (AJAX เรียกมา)
    if ($_POST['action'] == 'delete') {
        if ($conn->query("DELETE FROM function_menu_details WHERE id=$id")) {
            echo "success";
        } else {
            echo "error";
        }
        exit;
    }
}

// ส่วนดึงข้อมูล (Query)
$menus = $conn->query("SELECT m.*, t.type_name 
                       FROM function_menu_details m 
                       LEFT JOIN master_menu_types t ON m.menu_type_id = t.id 
                       ORDER BY m.id DESC");
?>

<div class="container-fluid p-0">
    <div id="alert-container"><?php include "assets/alert.php"; ?></div>

    <div class="row">
        <div class="col-md-4 mb-4">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-success text-white fw-bold">
                    <i class="bi bi-journal-plus me-2"></i>บันทึกเมนูอาหารมาตรฐาน
                </div>
                <div class="card-body">
                    <form id="menuForm" method="POST">
                        <input type="hidden" name="action" value="save">
                        <input type="hidden" name="id" id="m_id" value="0">

                        <div class="mb-3">
                            <label class="small fw-bold">ประเภทอาหาร / ชื่อแพ็กเกจ</label>
                            <select name="menu_type_id" id="m_type_id" class="form-select" required>
                                <option value="">-- เลือกประเภทอาหาร --</option>
                                <?php
                                // ดึงประเภทอาหารจากตาราง Master
                                $types = $conn->query("SELECT * FROM master_menu_types ORDER BY id ASC");
                                while ($t = $types->fetch_assoc()):
                                ?>
                                    <option value="<?= $t['id'] ?>"><?= $t['type_name'] ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="small fw-bold">การันตี (Pax)</label>
                                <input type="number" name="guarantee_pax" id="m_pax" class="form-control" placeholder="100">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="small fw-bold">ราคา/หัว (บาท)</label>
                                <input type="number" step="0.01" name="price_per_pax" id="m_price" class="form-control" placeholder="450.00">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="small fw-bold">รายการอาหาร (ระบุเป็นข้อๆ)</label>
                            <textarea name="menu_items" id="m_items" class="form-control" rows="6" placeholder="1. ต้มยำกุ้ง&#10;2. ไก่ผัดเม็ดมะม่วง..."></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="small fw-bold">รายละเอียดเครื่องดื่ม</label>
                            <textarea name="beverage_detail" id="m_bev" class="form-control" rows="3" placeholder="น้ำสมุนไพร, น้ำดื่มสะอาด..."></textarea>
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-success fw-bold shadow-sm">บันทึกข้อมูลเมนู</button>
                            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="resetMenuForm()">ล้างข้อมูล</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-8">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white fw-bold">
                    <i class="bi bi-table me-2"></i>รายการเมนูในคลังข้อมูล
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0 align-middle">
                            <thead class="table-dark">
                                <tr class="small">
                                    <th width="20%">ประเภทอาหาร</th>
                                    <th width="10%" class="text-center">จำนวน</th>
                                    <th width="15%" class="text-center">ราคา/หัว</th>
                                    <th>รายการเมนู/เครื่องดื่ม</th>
                                    <th width="12%" class="text-center">จัดการ</th>
                                </tr>
                            </thead>
                            <tbody class="small">
                                <?php while ($row = $menus->fetch_assoc()): ?>
                                    <tr>
                                        <td class="fw-bold text-success"><?= htmlspecialchars($row['type_name'] ?? 'ไม่ระบุ') ?></td>
                                        <td class="text-center"><?= number_format($row['guarantee_pax']) ?></td>
                                        <td class="text-center fw-bold text-primary"><?= number_format($row['price_per_pax'], 2) ?></td>
                                        <td>
                                            <div class="mb-1"><strong>อาหาร:</strong> <br>
                                                <small class="text-muted"><?= nl2br(htmlspecialchars($row['menu_items'])) ?></small>
                                            </div>
                                            <div><strong>เครื่องดื่ม:</strong> <br>
                                                <small class="text-muted"><?= nl2br(htmlspecialchars($row['beverage_detail'])) ?></small>
                                            </div>
                                        </td>
                                        <td class="text-center">
                                            <div class="btn-group">
                                                <button class="btn btn-sm btn-outline-primary border-0" title="แก้ไข" onclick='editMenu(<?= json_encode($row) ?>)'>
                                                    <i class="bi bi-pencil-square"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-danger border-0" title="ลบ" onclick="deleteMenu(<?= $row['id'] ?>)">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // ฟังก์ชันส่งค่าไปที่ฟอร์มเพื่อแก้ไข
    function editMenu(data) {
        document.getElementById('m_id').value = data.id;
        // เซ็ตค่าให้ Dropdown โดยใช้ ID
        document.getElementById('m_type_id').value = data.menu_type_id; 
        document.getElementById('m_pax').value = data.guarantee_pax;
        document.getElementById('m_price').value = data.price_per_pax;
        document.getElementById('m_items').value = data.menu_items;
        document.getElementById('m_bev').value = data.beverage_detail;
        
        // เลื่อนหน้าจอขึ้นไปที่ฟอร์มสวยๆ
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    // ฟังก์ชันล้างฟอร์ม
    function resetMenuForm() {
        document.getElementById('menuForm').reset();
        document.getElementById('m_id').value = 0;
    }

    // ฟังก์ชันลบข้อมูลผ่าน AJAX
    function deleteMenu(id) {
        if (confirm('ยืนยันการลบรายการเมนูนี้ใช่ไหมจาร? ข้อมูลจะหายถาวรเลยนะ!')) {
            let fd = new FormData();
            fd.append('action', 'delete');
            fd.append('id', id);
            fetch('food_management.php', {
                method: 'POST',
                body: fd
            }).then(res => res.text()).then(data => {
                if(data.trim() === 'success') {
                    window.location.reload();
                } else {
                    alert('เกิดข้อผิดพลาดในการลบข้อมูลครับ');
                }
            });
        }
    }
</script>

<?php include "footer.php"; ?>