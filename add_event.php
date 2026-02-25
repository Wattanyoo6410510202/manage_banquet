<?php
include "config.php";
include "header.php";
include "process_function.php"; 

// 1. แก้ไขชื่อ Column ให้ตรงกับที่จะใช้ใน loop (แนะนำให้ใช้ AS เพื่อให้ชื่อสื่อสารง่าย)
$query_companies = "SELECT id AS company_id, company_name, logo_path AS company_logo FROM companies ORDER BY company_name ASC";
$res_companies = $conn->query($query_companies); // ใช้ชื่อตัวแปรนี้ให้ตลอด
?>

<div class="container-fluid">
    <form method="POST" enctype="multipart/form-data">
        <div class="card">
            <div class="card-header main-header p-4">
                <div class="d-flex justify-content-between align-items-center">

                    <h4 class="mb-0 fw-bold">
                        <i class="bi bi-building-check me-2 text-gold"></i> FUNCTION MEETING
                    </h4>

                    <div style="width: 250px;">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-dark border-secondary text-gold small fw-bold">NO.</span>
                            <input type="text" name="function_code"
                                class="form-control border-secondary bg-light fw-bold text-center" placeholder="F-00000"
                                style="letter-spacing: 1px;" required>
                        </div>
                    </div>

                </div>
            </div>
            <div class="card-body p-4 p-lg-5">
                <h5 class="section-title mb-4"><i class="bi bi-person-lines-fill"></i> 1. ข้อมูลการจองทั่วไป (General
                    Information)</h5>

                <div class="row mb-5">
                    <div class="col-md-4 border-end">
                        <div class="p-3 bg-light rounded-3 text-center h-100">
                            <label class="small fw-bold d-block mb-2 text-start">
                                เลือกบริษัท/ลูกค้า (Company Selection)
                            </label>

                            <select name="company_id" class="form-select form-select-sm mb-3" onchange="updateCompanyLogo(this)" required>
    <option value="">-- เลือกบริษัท --</option>
    <?php
    // 2. ตรวจสอบชื่อตัวแปรให้ตรงกับด้านบน ($res_companies)
    if ($res_companies && $res_companies->num_rows > 0):
        while ($row = $res_companies->fetch_assoc()):
            $logo_path = !empty($row['company_logo']) ? $row['company_logo'] : 'assets/img/default-company.png';
            ?>
            <option value="<?php echo $row['company_id']; ?>" data-logo="<?php echo $logo_path; ?>">
                <?php echo htmlspecialchars($row['company_name']); ?>
            </option>
        <?php
        endwhile;
    endif;
    ?>
</select>

                            <div class="company-logo-preview border rounded bg-white d-flex align-items-center justify-content-center mx-auto"
                                style="width: 150px; height: 150px; overflow: hidden;">
                                <img id="companyLogo" src="assets/img/default-company.png" class="img-fluid p-2"
                                    alt="Company Logo">
                            </div>
                            <small class="text-muted mt-2 d-block">โลโก้บริษัทที่เลือก</small>
                        </div>
                    </div>

                    <div class="col-md-8">
                        <div class="row ps-lg-3">
                            <div class="col-md-6 mb-3">
                                <label class="small fw-bold">ชื่องาน (Function)</label>
                                <input name="function_name" class="form-control form-control-sm"
                                    placeholder="Event Name" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="small fw-bold">ผู้จอง (Booking)</label>
                                <input name="booking_name" class="form-control form-control-sm" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="small fw-bold">หน่วยงาน/ที่อยู่</label>
                                <input name="organization" class="form-control form-control-sm">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="small fw-bold">เบอร์โทรศัพท์</label>
                                <input name="phone" class="form-control form-control-sm">
                            </div>

                            <div class="col-md-5 mb-3">
                                <label class="small fw-bold">สถานที่ประชุม</label>
                                <input name="room_name" class="form-control form-control-sm" placeholder="Room Name">
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="small fw-bold">Booking Room</label>
                                <input name="booking_room" class="form-control form-control-sm">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="small fw-bold">Deposit (มัดจำ)</label>
                                <input name="deposit" class="form-control form-control-sm">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row mb-5">
                    <div class="col-md-7 border-end pe-lg-4">
                        <h5 class="section-title mb-4"><i class="bi bi-calendar3"></i> 2. ตารางกำหนดการ (Schedule)</h5>
                        <div class="table-responsive mb-4">
                            <table class="table table-sm table-hover align-middle" id="scheduleTable">
                                <thead class="small text-center text-secondary">
                                    <tr>
                                        <th width="20%">Date</th>
                                        <th width="20%">Hour</th>
                                        <th>Function Detail</th>
                                        <th width="15%">Guar.</th>
                                        <th width="5%"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td><input type="date" name="schedule_date[]"
                                                class="form-control form-control-sm border-0 bg-light"></td>
                                        <td><input type="text" name="schedule_hour[]"
                                                class="form-control form-control-sm border-0 bg-light"
                                                placeholder="00:00 - 00:00"></td>
                                        <td><input type="text" name="schedule_function[]"
                                                class="form-control form-control-sm border-0 bg-light"></td>
                                        <td><input type="number" name="schedule_guarantee[]"
                                                class="form-control form-control-sm border-0 bg-light"></td>
                                        <td><button type="button" class="btn text-danger btn-sm border-0"
                                                onclick="removeRow(this)"><i class="bi bi-dash-circle"></i></button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                            <button type="button" class="btn btn-hotel-outline btn-sm mt-1"
                                onclick="addRow('scheduleTable')"><i class="bi bi-plus-lg me-1"></i> Add Schedule
                                Row</button>
                        </div>

                        <h5 class="section-title mb-4 mt-5"><i class="bi bi-egg-fried"></i> 3. Main Kitchen (ครัว)</h5>
                        <div class="table-responsive">
                            <table class="table table-sm table-hover align-middle" id="kitchenTable">
                                <thead class="small text-center text-secondary">
                                    <tr>
                                        <th width="20%">Date</th>
                                        <th width="35%">Break</th>
                                        <th width="15%">Menu</th>
                                        <th>Gurantee</th>
                                        <th width="5%"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td><input type="text" name="k_type[]"
                                                class="form-control form-control-sm border-0 bg-light"></td>
                                        <td><input type="text" name="k_item[]"
                                                class="form-control form-control-sm border-0 bg-light"></td>
                                        <td><input type="number" name="k_qty[]"
                                                class="form-control form-control-sm border-0 bg-light"></td>
                                        <td><input type="text" name="k_remark[]"
                                                class="form-control form-control-sm border-0 bg-light"></td>
                                        <td><button type="button" class="btn text-danger btn-sm border-0"
                                                onclick="removeRow(this)"><i class="bi bi-dash-circle"></i></button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                            <button type="button" class="btn btn-hotel-outline btn-sm mt-1 mb-3"
                                onclick="addKitchenRow()"><i class="bi bi-plus-lg me-1"></i> Add Kitchen Order</button>
                        </div>
                        <textarea name="main_kitchen_remark" class="form-control form-control-sm mt-2" rows="3"
                            placeholder="Kitchen special instructions & food allergy info..."></textarea>
                    </div>

                    <div class="col-md-5 bg-sidebar p-4 rounded-4">
                        <h5 class="section-title mb-4"><i class="bi bi-gear-wide-connected"></i> 4. Set-up & Technical
                        </h5>
                        <div class="mb-4">
                            <label class="fw-bold small text-muted"><i class="bi bi-info-square me-1"></i> Banquet
                                Arrangement:</label>
                            <textarea name="banquet_style" class="form-control form-control-sm shadow-sm bg-white"
                                rows="7"
                                placeholder="e.g. U-Shape setup with white skirting, VIP table at front..."></textarea>
                        </div>
                        <div class="mb-4">
                            <label class="fw-bold small text-muted"><i class="bi bi-mic me-1"></i> Engineering & Audio
                                Visual:</label>
                            <textarea name="equipment" class="form-control form-control-sm shadow-sm bg-white" rows="5"
                                placeholder="Projector, 2 Wireless mics, HDMI cable, Clicker..."></textarea>
                        </div>
                        <div class="mb-0">
                            <label class="fw-bold small text-muted">Additional Remarks:</label>
                            <textarea name="remark" class="form-control form-control-sm shadow-sm bg-white"
                                rows="2"></textarea>
                        </div>
                    </div>
                </div>

                <h5 class="section-title mb-4"><i class="bi bi-cup-hot-fill"></i> 5. Menu Food & Beverage Details</h5>
                <div class="table-responsive mb-5">
                    <table class="table table-sm table-hover align-middle border" id="menuTable">
                        <thead class="text-center text-secondary">
                            <tr>
                                <th width="10%">Date</th>
                                <th width="15%">TypeMenu</th>
                                <th width="10%">Set</th>
                                <th>Menu Details / Selection</th>
                                <th width="10%">Qty/Unit</th>
                                <th width="12%">Price</th>
                                <th width="5%"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><input type="text" name="menu_time[]"
                                        class="form-control form-control-sm border-0 bg-light" placeholder="e.g. 10:30">
                                </td>
                                <td><input type="text" name="menu_name[]" class="form-control form-control-sm border-0"
                                        placeholder="e.g. Coffee Break"></td>
                                <td><input type="text" name="menu_set[]"
                                        class="form-control form-control-sm border-0 bg-light" placeholder="Set A"></td>
                                <td><textarea name="menu_detail[]" class="form-control form-control-sm border-0"
                                        rows="1" placeholder="Specify food items..."></textarea></td>
                                <td><input type="text" name="menu_qty[]" class="form-control form-control-sm border-0"
                                        placeholder="50 Pax"></td>
                                <td><input type="text" name="menu_price[]" class="form-control form-control-sm border-0"
                                        placeholder="0.00"></td>
                                <td class="text-center">
                                    <button type="button" class="btn text-danger btn-sm border-0"
                                        onclick="removeRow(this)"><i class="bi bi-trash-fill"></i></button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <button type="button" class="btn btn-hotel-outline btn-sm mt-1" onclick="addMenuRow()">
                        <i class="bi bi-plus-lg me-1"></i> Add Food & Beverage Item
                    </button>
                </div>

                <h5 class="section-title mb-4"><i class="bi bi-palette-fill"></i> 6. Decoration & Housekeeping</h5>
                <div class="row g-4">
                    <div class="col-md-6">
                        <div class="p-4 border rounded-4 bg-white shadow-sm h-100">
                            <label class="fw-bold small text-muted mb-3"><i class="bi bi-card-heading me-1"></i>
                                Backdrop & Signage Details:</label>
                            <textarea name="backdrop_detail" class="form-control form-control-sm mb-4" rows="3"
                                placeholder="ระบุข้อความหรือรูปแบบป้ายหน้าห้อง..."></textarea>

                            <div class="p-3 border-dashed text-center bg-light">
                                <label class="small fw-bold text-muted mb-2 d-block"><i class="bi bi-image"></i>
                                    Backdrop / Logo Reference</label>
                                <input type="file" name="backdrop_img" id="backdropInput"
                                    class="form-control form-control-sm mb-2" accept="image/*"
                                    onchange="previewImage(this)">
                                <div id="imagePreviewContainer" class="text-center d-none">
                                    <img id="imagePreview" src="#" alt="Preview" class="img-thumbnail mt-2 shadow-sm"
                                        style="max-height: 150px; border-radius: 8px;">
                                    <button type="button"
                                        class="btn btn-sm btn-link text-danger d-block mx-auto mt-2 text-decoration-none"
                                        onclick="clearPreview()"><i class="bi bi-x-circle"></i> ลบรูปภาพ</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="p-4 border rounded-4 bg-white shadow-sm h-100">
                            <label class="fw-bold small text-muted mb-3"><i class="bi bi-flower1 me-1"></i> Housekeeping
                                & Florist Requirement:</label>
                            <textarea name="hk_florist_detail" class="form-control form-control-sm" rows="8"
                                placeholder="การจัดดอกไม้โพเดียม, การเตรียมผ้าเย็น, การทำความสะอาดพิเศษ..."></textarea>
                        </div>
                    </div>
                </div>

                <div class="text-center mt-5 pt-4 border-top">
                    <button name="save" type="submit" class="btn btn-dark btn-sm px-3 shadow-sm">
                        <i class="bi bi-cloud-check-fill me-2"></i> Save Function Sheet
                    </button>
                    <div class="mt-3 text-muted small"><i class="bi bi-info-circle"></i> Please review all department
                        instructions before saving.</div>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
    // Image Previewer
    function previewImage(input) {
        const container = document.getElementById('imagePreviewContainer');
        const preview = document.getElementById('imagePreview');
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = function (e) {
                preview.src = e.target.result;
                container.classList.remove('d-none');
            }
            reader.readAsDataURL(input.files[0]);
        }
    }

    function clearPreview() {
        const input = document.getElementById('backdropInput');
        const container = document.getElementById('imagePreviewContainer');
        input.value = "";
        container.classList.add('d-none');
    }

    function addMenuRow() {
        let table = document.querySelector("#menuTable tbody");
        let row = table.insertRow();
        row.innerHTML = `
        <td><input type="text" name="menu_time[]" class="form-control form-control-sm border-0 bg-light" placeholder="e.g. 10:30"></td>
        <td><input type="text" name="menu_name[]" class="form-control form-control-sm border-0" placeholder="e.g. Coffee Break"></td>
        <td><input type="text" name="menu_set[]" class="form-control form-control-sm border-0 bg-light" placeholder="Set A"></td>
        <td><textarea name="menu_detail[]" class="form-control form-control-sm border-0" rows="1" placeholder="Specify items..."></textarea></td>
        <td><input type="text" name="menu_qty[]" class="form-control form-control-sm border-0" placeholder="50 Pax"></td>
        <td><input type="text" name="menu_price[]" class="form-control form-control-sm border-0" placeholder="0.00"></td>
        <td class="text-center"><button type="button" class="btn text-danger btn-sm border-0" onclick="removeRow(this)"><i class="bi bi-trash-fill"></i></button></td>
    `;
    }

    function addKitchenRow() {
        let table = document.querySelector("#kitchenTable tbody");
        let row = table.insertRow();
        row.innerHTML = `
        <td><input type="text" name="k_type[]" class="form-control form-control-sm border-0 bg-light"></td>
        <td><input type="text" name="k_item[]" class="form-control form-control-sm border-0 bg-light"></td>
        <td><input type="number" name="k_qty[]" class="form-control form-control-sm border-0 bg-light"></td>
        <td><input type="text" name="k_remark[]" class="form-control form-control-sm border-0 bg-light"></td>
        <td><button type="button" class="btn text-danger btn-sm border-0" onclick="removeRow(this)"><i class="bi bi-dash-circle"></i></button></td>
    `;
    }


    function addRow(tableId) {
    let table = document.querySelector("#" + tableId + " tbody");
    let row = table.insertRow();
    if (tableId === 'scheduleTable') {
        row.innerHTML = `
            <td><input type="date" name="schedule_date[]" class="form-control form-control-sm border-0 bg-light"></td>
            <td><input type="text" name="schedule_hour[]" class="form-control form-control-sm border-0 bg-light" placeholder="00:00 - 00:00"></td>
            <td><input type="text" name="schedule_function[]" class="form-control form-control-sm border-0 bg-light"></td>
            <td><input type="number" name="schedule_guarantee[]" class="form-control form-control-sm border-0 bg-light"></td>
            <td><button type="button" class="btn text-danger btn-sm border-0" onclick="removeRow(this)"><i class="bi bi-dash-circle"></i></button></td>
        `;
    }
}
    function addMenuRow() {
        let table = document.querySelector("#menuTable tbody");
        let row = table.insertRow();
        row.innerHTML = `
        <td><input type="text" name="menu_time[]" class="form-control form-control-sm border-0 bg-light" placeholder="e.g. 10:30"></td>
        <td><input type="text" name="menu_name[]" class="form-control form-control-sm border-0" placeholder="e.g. Coffee Break"></td>
        <td><textarea name="menu_detail[]" class="form-control form-control-sm border-0" rows="1" placeholder="Specify items..."></textarea></td>
        <td><input type="text" name="menu_qty[]" class="form-control form-control-sm border-0" placeholder="50 Pax"></td>
        <td><input type="text" name="menu_location[]" class="form-control form-control-sm border-0" placeholder="Foyer / Room"></td>
        <td class="text-center"><button type="button" class="btn text-danger btn-sm border-0" onclick="removeRow(this)"><i class="bi bi-trash-fill"></i></button></td>
    `;
    }

    function removeRow(btn) {
        if (confirm('Are you sure you want to remove this item?')) {
            btn.closest("tr").remove();
        }
    }

    function updateCompanyLogo(select) {
        const logoImg = document.getElementById('companyLogo');
        const selectedOption = select.options[select.selectedIndex];
        const logoPath = selectedOption.getAttribute('data-logo');

        if (logoPath) {
            logoImg.src = logoPath;
        } else {
            logoImg.src = 'assets/img/default-company.png'; // รูปเริ่มต้นถ้าไม่ได้เลือก
        }
    }

    function updateCompanyLogo(select) {
        const logoImg = document.getElementById('companyLogo');
        // ดึงค่าจาก attribute "data-logo" ของ option ที่ถูกเลือก
        const selectedOption = select.options[select.selectedIndex];
        const logoPath = selectedOption.getAttribute('data-logo');

        if (logoPath) {
            logoImg.src = logoPath;
        } else {
            logoImg.src = 'assets/img/default-company.png';
        }
    }
</script>



<?php include "footer.php"; ?>