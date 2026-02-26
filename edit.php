<?php
include "config.php";
require_once "header.php";
access_control('all_staff');

// --- 🛡️ เพิ่มส่วนควบคุมสิทธิ์เข้มข้น ---
$current_user = $_SESSION['user_name'] ?? '';
$user_role = $_SESSION['role'] ?? 'viewer';

// 1. รับ ID และดึงข้อมูลหลัก
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$query = "SELECT * FROM functions WHERE id = $id";
$res = $conn->query($query);
$data = $res->fetch_assoc();

if (!$data) {
    echo "<div class='container mt-5'><div class='alert alert-danger'>ไม่พบข้อมูลรายการนี้ครับจาร!</div></div>";
    exit;
}

// 🛡️ ด่านที่ 1: ถ้าไม่ใช่ Admin และไม่ใช่เจ้าของงาน ห้ามแก้
// (สมมติว่าในตาราง functions มี column ชื่อ created_by นะครับ)
if ($user_role !== 'admin' && trim($data['created_by']) !== trim($current_user)) {
    echo "<script>
        alert('จารไม่มีสิทธิ์แก้ไขงานของคนอื่นนะครับ!');
        window.location.href='manage_banquet.php';
    </script>";
    exit;
}

// 🛡️ ด่านที่ 2: ถ้างานอนุมัติแล้ว (approve != 0) ห้ามแก้ ยกเว้น Admin
if (isset($data['approve']) && $data['approve'] != 0 && $user_role !== 'admin') {
    echo "<script>
        alert('งานนี้ถูกอนุมัติแล้ว ห้ามแก้ไขครับ! ติดต่อ Admin ถ้าจำเป็น');
        window.location.href='manage_banquet.php';
    </script>";
    exit;
}
// --- 🛡️ จบส่วนควบคุมสิทธิ์ ---

// 2. ดึงข้อมูลบริษัทสำหรับ Dropdown (ใช้แบบเดิมที่จารบอกว่าภาพขึ้น)
$query_companies = "SELECT id AS company_id, company_name, logo_path AS company_logo FROM companies ORDER BY company_name ASC";
$res_companies = $conn->query($query_companies);

// 3. ดึงข้อมูลจากตารางลูกทั้งหมด
$schedules = $conn->query("SELECT * FROM function_schedules WHERE function_id = $id ORDER BY id ASC");
$kitchens = $conn->query("SELECT * FROM function_kitchens WHERE function_id = $id ORDER BY id ASC");
$menus = $conn->query("SELECT * FROM function_menus WHERE function_id = $id ORDER BY id ASC");
?>



<div class="container-fluid py-4">
    <form action="api/update_function.php" method="POST" enctype="multipart/form-data">
        <input type="hidden" name="action" value="update">
        <input type="hidden" name="function_id" value="<?php echo $id; ?>">

        <div class="card shadow-sm border-0">
            <div class="card-header main-header p-4">
                <div class="d-flex justify-content-between align-items-center">
                    <h4 class="mb-0 fw-bold"><i class="bi bi-pencil-square me-2 text-gold"></i> EDIT FUNCTION MEETING
                    </h4>
                    <div style="width: 100%; max-width: 450px;">
                        <div class="d-flex align-items-center gap-2">
                            <button name="update" type="submit" class="btn btn-primary btn-sm px-3 flex-shrink-0">
                                <i class="bi bi-cloud-upload-fill me-2"></i> อัปเดตข้อมูล (Update)
                            </button>
                            <div class="input-group input-group-sm">
                                <span
                                    class="input-group-text bg-dark border-secondary text-gold small fw-bold">NO.</span>
                                <input type="text" name="function_code"
                                    class="form-control border-secondary bg-light fw-bold text-center"
                                    value="<?php echo $data['function_code']; ?>" readonly>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card-body p-4 p-lg-5">
                <h5 class="section-title mb-4"><i class="bi bi-person-lines-fill"></i> 1. ข้อมูลการจองทั่วไป</h5>
                <div class="row mb-5">
                    <div class="col-md-4 border-end">
                        <div class="p-3 bg-light rounded-3 text-center h-100">
                            <label class="small fw-bold d-block mb-2 text-start">เลือกบริษัท/ลูกค้า</label>
                            <select name="company_id" class="form-select form-select-sm mb-3"
                                onchange="updateCompanyLogo(this)" required>
                                <?php
                                $current_logo = 'assets/img/default-company.png';
                                if ($res_companies):
                                    while ($c = $res_companies->fetch_assoc()):
                                        $selected = ($c['company_id'] == $data['company_id']) ? 'selected' : '';
                                        $logo = !empty($c['company_logo']) ? $c['company_logo'] : 'assets/img/default-company.png';
                                        if ($selected)
                                            $current_logo = $logo;
                                        ?>
                                        <option value="<?php echo $c['company_id']; ?>" data-logo="<?php echo $logo; ?>" <?php echo $selected; ?>>
                                            <?php echo htmlspecialchars($c['company_name']); ?>
                                        </option>
                                    <?php endwhile; endif; ?>
                            </select>
                            <div class="company-logo-preview border rounded bg-white d-flex align-items-center justify-content-center mx-auto"
                                style="width: 150px; height: 150px; overflow: hidden;">
                                <img id="companyLogo" src="<?php echo $current_logo; ?>" class="img-fluid p-2">
                            </div>
                        </div>
                    </div>

                    <div class="col-md-8">
                        <div class="row ps-lg-3">
                            <div class="col-md-6 mb-3">
                                <label class="small fw-bold">ชื่องาน (Function)</label>
                                <input name="function_name" class="form-control form-control-sm"
                                    value="<?php echo htmlspecialchars($data['function_name']); ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="small fw-bold">ผู้จอง (Booking)</label>
                                <input name="booking_name" class="form-control form-control-sm"
                                    value="<?php echo htmlspecialchars($data['booking_name']); ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="small fw-bold">หน่วยงาน/ที่อยู่</label>
                                <input name="organization" class="form-control form-control-sm"
                                    value="<?php echo htmlspecialchars($data['organization']); ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="small fw-bold">เบอร์โทรศัพท์</label>
                                <input name="phone" class="form-control form-control-sm"
                                    value="<?php echo htmlspecialchars($data['phone']); ?>">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="small fw-bold">สถานที่ประชุม</label>
                                <input name="room_name" class="form-control form-control-sm"
                                    value="<?php echo htmlspecialchars($data['room_name']); ?>">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="small fw-bold">Booking Room</label>
                                <input name="booking_room" class="form-control form-control-sm"
                                    value="<?php echo htmlspecialchars($data['booking_room'] ?? ''); ?>">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="small fw-bold">Deposit (มัดจำ)</label>
                                <input name="deposit" class="form-control form-control-sm"
                                    value="<?php echo $data['deposit']; ?>">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row mb-5">
                    <div class="col-md-7 border-end pe-lg-4">
                        <h5 class="section-title mb-4"><i class="bi bi-calendar3"></i> 2. ตารางกำหนดการ</h5>
                        <div class="table-responsive mb-4">
                            <table class="table table-sm table-hover align-middle" id="scheduleTable">
                                <tbody>
                                    <?php if ($schedules->num_rows > 0):
                                        while ($s = $schedules->fetch_assoc()): ?>
                                            <tr>
                                                <td><input type="date" name="schedule_date[]"
                                                        class="form-control form-control-sm border-0 bg-light"
                                                        value="<?php echo $s['schedule_date']; ?>"></td>
                                                <td><input type="text" name="schedule_hour[]"
                                                        class="form-control form-control-sm border-0 bg-light"
                                                        value="<?php echo $s['schedule_hour']; ?>"></td>
                                                <td><textarea name="schedule_function[]"
                                                        class="form-control form-control-sm border-0 bg-light"
                                                        rows="2"><?php echo $s['schedule_function']; ?></textarea></td>
                                                <td><input type="number" name="schedule_guarantee[]"
                                                        class="form-control form-control-sm border-0 bg-light"
                                                        value="<?php echo $s['schedule_guarantee']; ?>"></td>
                                                <td><button type="button" class="btn text-danger btn-sm border-0"
                                                        onclick="removeRow(this)"><i class="bi bi-dash-circle"></i></button>
                                                </td>
                                            </tr>
                                        <?php endwhile; endif; ?>
                                </tbody>
                            </table>
                            <button type="button" class="btn btn-hotel-outline btn-sm" onclick="addScheduleRow()"><i
                                    class="bi bi-plus-lg"></i> Add Row</button>
                        </div>

                        <h5 class="section-title mb-4 mt-5"><i class="bi bi-egg-fried"></i> 3. Main Kitchen</h5>
                        <div class="table-responsive">
                            <table class="table table-sm table-hover align-middle" id="kitchenTable">
                                <tbody>
                                    <?php if ($kitchens->num_rows > 0):
                                        while ($k = $kitchens->fetch_assoc()): ?>
                                            <tr>
                                                <td><input type="date" name="k_date[]"
                                                        class="form-control form-control-sm border-0 bg-light"
                                                        value="<?php echo $k['k_date']; ?>"></td>
                                                <td><input type="text" name="k_type[]"
                                                        class="form-control form-control-sm border-0 bg-light"
                                                        value="<?php echo $k['k_type']; ?>"></td>
                                                <td><textarea name="k_item[]"
                                                        class="form-control form-control-sm border-0 bg-light"
                                                        rows="2"><?php echo $k['k_item']; ?></textarea></td>
                                                <td><input type="number" name="k_qty[]"
                                                        class="form-control form-control-sm border-0 bg-light text-center"
                                                        value="<?php echo $k['k_qty']; ?>"></td>
                                                <td><button type="button" class="btn text-danger btn-sm border-0"
                                                        onclick="removeRow(this)"><i class="bi bi-dash-circle"></i></button>
                                                </td>
                                            </tr>
                                        <?php endwhile; endif; ?>
                                </tbody>
                            </table>
                            <button type="button" class="btn btn-hotel-outline btn-sm" onclick="addKitchenRow()"><i
                                    class="bi bi-plus-lg"></i> Add Kitchen</button>
                        </div>
                        <textarea name="main_kitchen_remark" class="form-control form-control-sm mt-2"
                            rows="3"><?php echo $data['main_kitchen_remark'] ?? ''; ?></textarea>
                    </div>

                    <div class="col-md-5 bg-sidebar p-4 rounded-4">
                        <h5 class="section-title mb-4"><i class="bi bi-gear-wide-connected"></i> 4. Set-up & Technical
                        </h5>
                        <div class="mb-4">
                            <label class="fw-bold small text-muted">Banquet Arrangement:</label>
                            <textarea name="banquet_style" class="form-control form-control-sm bg-white"
                                rows="6"><?php echo $data['banquet_style']; ?></textarea>
                        </div>
                        <div class="mb-4">
                            <label class="fw-bold small text-muted">Engineering & Audio Visual:</label>
                            <textarea name="equipment" class="form-control form-control-sm bg-white"
                                rows="5"><?php echo $data['equipment']; ?></textarea>
                        </div>
                        <div class="mb-0">
                            <label class="fw-bold small text-muted">Additional Remarks:</label>
                            <textarea name="remark" class="form-control form-control-sm bg-white"
                                rows="2"><?php echo $data['remark']; ?></textarea>
                        </div>
                    </div>
                </div>

                <h5 class="section-title mb-4"><i class="bi bi-cup-hot-fill"></i> 5. Menu Food & Beverage Details</h5>
                <div class="table-responsive mb-5">
                    <table class="table table-sm table-hover align-middle border" id="menuTable">
                        <tbody>
                            <?php if ($menus->num_rows > 0):
                                while ($m = $menus->fetch_assoc()): ?>
                                    <tr>
                                        <td><input type="date" name="menu_time[]" class="form-control form-control-sm border-0"
                                                value="<?php echo $m['menu_time']; ?>"></td>
                                        <td><input type="text" name="menu_name[]" class="form-control form-control-sm border-0"
                                                value="<?php echo $m['menu_name']; ?>"></td>
                                        <td><input type="text" name="menu_set[]" class="form-control form-control-sm border-0"
                                                value="<?php echo $m['menu_set']; ?>"></td>
                                        <td><textarea name="menu_detail[]" class="form-control form-control-sm border-0"
                                                rows="1"><?php echo $m['menu_detail']; ?></textarea></td>
                                        <td><input type="text" name="menu_qty[]" class="form-control form-control-sm border-0"
                                                value="<?php echo $m['menu_qty']; ?>"></td>
                                        <td><input type="text" name="menu_price[]" class="form-control form-control-sm border-0"
                                                value="<?php echo $m['menu_price']; ?>"></td>
                                        <td class="text-center"><button type="button" class="btn text-danger btn-sm border-0"
                                                onclick="removeRow(this)"><i class="bi bi-trash-fill"></i></button></td>
                                    </tr>
                                <?php endwhile; endif; ?>
                        </tbody>
                    </table>
                    <button type="button" class="btn btn-hotel-outline btn-sm" onclick="addMenuRow()"><i
                            class="bi bi-plus-lg"></i> Add Menu</button>
                </div>

                <h5 class="section-title mb-4"><i class="bi bi-palette-fill"></i> 6. Decoration & Housekeeping</h5>
                <div class="row g-4">
                    <div class="col-md-6">
                        <div class="p-4 border rounded-4 bg-white shadow-sm h-100">
                            <label class="fw-bold small text-muted mb-3">Backdrop & Signage Details:</label>
                            <textarea name="backdrop_detail" class="form-control form-control-sm mb-4"
                                rows="3"><?php echo $data['backdrop_detail'] ?? ''; ?></textarea>

                            <div class="p-3 border-dashed text-center bg-light">
                                <label class="small d-block mb-2">รูปภาพปัจจุบัน:</label>
                                <div id="imagePreviewContainer"
                                    class="<?php echo !empty($data['backdrop_img']) ? '' : 'd-none'; ?>">
                                    <img id="imagePreview" src="<?php echo $data['backdrop_img'] ?: '#'; ?>"
                                        class="img-thumbnail mb-2" style="max-height: 150px;">
                                </div>
                                <input type="file" name="backdrop_img" class="form-control form-control-sm"
                                    accept="image/*" onchange="previewImage(this)">
                                <input type="hidden" name="old_backdrop_img"
                                    value="<?php echo $data['backdrop_img']; ?>">
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="p-4 border rounded-4 bg-white shadow-sm h-100">
                            <label class="fw-bold small text-muted mb-3">Housekeeping & Florist Requirement:</label>
                            <textarea name="hk_florist_detail" class="form-control form-control-sm"
                                rows="8"><?php echo $data['hk_florist_detail']; ?></textarea>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
    function previewImage(input) {
        const preview = document.getElementById('imagePreview');
        const container = document.getElementById('imagePreviewContainer');
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = e => {
                preview.src = e.target.result;
                container.classList.remove('d-none');
            }
            reader.readAsDataURL(input.files[0]);
        }
    }

    function updateCompanyLogo(select) {
        const logoImg = document.getElementById('companyLogo');
        const logoPath = select.options[select.selectedIndex].getAttribute('data-logo');
        logoImg.src = logoPath || 'assets/img/default-company.png';
    }

    function addScheduleRow() {
        const table = document.querySelector("#scheduleTable tbody");
        const row = table.insertRow();
        row.innerHTML = `<td><input type="date" name="schedule_date[]" class="form-control form-control-sm border-0 bg-light"></td><td><input type="text" name="schedule_hour[]" class="form-control form-control-sm border-0 bg-light"></td><td><textarea name="schedule_function[]" class="form-control form-control-sm border-0 bg-light" rows="2"></textarea></td><td><input type="number" name="schedule_guarantee[]" class="form-control form-control-sm border-0 bg-light"></td><td><button type="button" class="btn text-danger btn-sm border-0" onclick="removeRow(this)"><i class="bi bi-dash-circle"></i></button></td>`;
    }

    function addKitchenRow() {
        const table = document.querySelector("#kitchenTable tbody");
        const row = table.insertRow();
        row.innerHTML = `<td><input type="date" name="k_date[]" class="form-control form-control-sm border-0 bg-light"></td><td><input type="text" name="k_type[]" class="form-control form-control-sm border-0 bg-light"></td><td><textarea name="k_item[]" class="form-control form-control-sm border-0 bg-light" rows="2"></textarea></td><td><input type="number" name="k_qty[]" class="form-control form-control-sm border-0 bg-light text-center"></td><td><button type="button" class="btn text-danger btn-sm border-0" onclick="removeRow(this)"><i class="bi bi-dash-circle"></i></button></td>`;
    }

    function addMenuRow() {
        const table = document.querySelector("#menuTable tbody");
        const row = table.insertRow();
        row.innerHTML = `<td><input type="date" name="menu_time[]" class="form-control form-control-sm border-0"></td><td><input type="text" name="menu_name[]" class="form-control form-control-sm border-0"></td><td><input type="text" name="menu_set[]" class="form-control form-control-sm border-0 bg-light"></td><td><textarea name="menu_detail[]" class="form-control form-control-sm border-0" rows="1"></textarea></td><td><input type="text" name="menu_qty[]" class="form-control form-control-sm border-0"></td><td><input type="text" name="menu_price[]" class="form-control form-control-sm border-0"></td><td class="text-center"><button type="button" class="btn text-danger btn-sm border-0" onclick="removeRow(this)"><i class="bi bi-trash-fill"></i></button></td>`;
    }

    function removeRow(btn) {
        btn.closest("tr").remove();
    }
</script>

<?php include "footer.php"; ?>