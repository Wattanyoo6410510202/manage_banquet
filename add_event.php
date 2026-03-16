<?php
include "config.php";
include "api/process_function.php";
include "header.php";
access_control(['Admin', 'GM', 'Staff']);

// ดึงข้อมูลบริษัทสำหรับ Dropdown
$query_companies = "SELECT id AS company_id, company_name, logo_path AS company_logo FROM companies ORDER BY company_name ASC";
$res_companies = $conn->query($query_companies);
?>



<div style="position: fixed; bottom: 80px; right: 30px; z-index: 9999;">
    <button type="button" id="aiMagicFill" class="btn btn-warning shadow-lg fw-bold p-3 border-3 border-white"
        style="border-radius: 50px; min-width: 180px;">
        <i class="bi bi-robot me-2"></i> AI สุ่มให้ครับจาร!
    </button>
</div>

<script src="assets/ai_random_fill.js"></script>

<div class="container-fluid">
    <form method="POST" enctype="multipart/form-data">
        <div class="card shadow-sm border-0">
            <div class="card-header main-header p-4">
                <div class="d-flex justify-content-between align-items-center">
                    <h4 class="mb-0 fw-bold">
                        <i class="bi bi-building-check me-2 text-gold"></i> FUNCTION MEETING
                    </h4>

                    <div style="width: 100%; max-width: 450px;">
                        <div class="d-flex align-items-center justify-content-end">
                            <button name="save" type="submit" class="btn btn-success btn-sm px-3  flex-shrink-0">
                                <i class="bi bi-cloud-check-fill me-2"></i> บันทึกข้อมูลฟังชั่น
                            </button>
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
                            <label class="small fw-bold d-block mb-2 text-start">เลือกโรงแรม/ลูกค้า (Company
                                Selection)</label>
                            <select name="company_id" class="form-select form-select-sm mb-3"
                                onchange="updateCompanyLogo(this)" required>
                                <option value="">-- เลือกโรงแรม --</option>
                                <?php if ($res_companies && $res_companies->num_rows > 0):
                                    while ($row = $res_companies->fetch_assoc()):
                                        $logo_path = !empty($row['company_logo']) ? $row['company_logo'] : 'assets/img/default-company.png'; ?>
                                        <option value="<?php echo $row['company_id']; ?>" data-logo="<?php echo $logo_path; ?>">
                                            <?php echo htmlspecialchars($row['company_name']); ?>
                                        </option>
                                    <?php endwhile; endif; ?>
                            </select>
                            <div class="company-logo-preview border rounded bg-white d-flex align-items-center justify-content-center mx-auto"
                                style="width: 150px; height: 150px; overflow: hidden;">
                                <img id="companyLogo" src="assets/img/default-company.png" class="img-fluid p-2"
                                    alt="Company Logo">
                            </div>
                        </div>
                    </div>

                    <div class="col-md-8">
                        <div class="row ps-lg-3">
                            <div class="col-md-6 mb-3">
                                <label class="small fw-bold">ชื่องาน (Function)</label>
                                <input name="function_name" class="form-control form-control-sm"
                                    placeholder="งาน......." required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="small fw-bold">ลูกค้า</label>
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
                            <div class="col-md-6 mb-3">
                                <label class="small fw-bold">สถานที่ประชุม</label>
                                <input name="room_name" class="form-control form-control-sm">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="small fw-bold">Booking Room</label>
                                <input name="booking_room" class="form-control form-control-sm">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="small fw-bold">Deposit (มัดจำ)</label>
                                <input name="deposit" class="form-control form-control-sm">
                            </div>
                              <div class="col-md-6 mb-3">
                                <label class="small fw-bold">จำนวนผู้เข้าร่วม (PAX)</label>
                                <input name="pax" class="form-control form-control-sm">
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
                                        <th width="20%">วันที่</th>
                                        <th width="20%">เวลา</th>
                                        <th>รายละเอียด</th>
                                        <th width="15%">จำนวน</th>
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
                                        <td><textarea name="schedule_function[]"
                                                class="form-control form-control-sm border-0 bg-light"
                                                rows="2"></textarea></td>
                                        <td><input type="number" name="schedule_guarantee[]"
                                                class="form-control form-control-sm border-0 bg-light"></td>
                                        <td><button type="button" class="btn text-danger btn-sm border-0"
                                                onclick="removeRow(this)"><i class="bi bi-dash-circle"></i></button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                            <button type="button" class="btn btn-hotel-outline btn-sm mt-1"
                                onclick="addScheduleRow()"><i class="bi bi-plus-lg me-1"></i> เพิ่มกำหนดการ</button>
                        </div>

                        <h5 class="section-title mb-4 mt-5"><i class="bi bi-egg-fried"></i> 3. Main Kitchen (ครัว)</h5>
                        <div class="table-responsive">
                            <table class="table table-sm table-hover align-middle" id="kitchenTable">
                                <thead class="small text-center text-secondary">
                                    <tr>
                                        <th width="20%">วันที่</th>
                                        <th width="20%">ประเภท</th>
                                        <th>รายการเมนู</th>
                                        <th width="15%">จำนวน</th>
                                        <th width="5%"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td><input type="date" name="k_date[]"
                                                class="form-control form-control-sm border-0 bg-light"></td>
                                        <td><input type="text" name="k_type[]"
                                                class="form-control form-control-sm border-0 bg-light"
                                                placeholder="e.g. AM Break"></td>
                                        <td><textarea name="k_item[]"
                                                class="form-control form-control-sm border-0 bg-light" rows="2"
                                                placeholder="ชื่อรายการ"></textarea></td>
                                        <td><input type="number" name="k_qty[]"
                                                class="form-control form-control-sm border-0 bg-light text-center"></td>
                                        <td><button type="button" class="btn text-danger btn-sm border-0"
                                                onclick="removeRow(this)"><i class="bi bi-dash-circle"></i></button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                            <button type="button" class="btn btn-hotel-outline btn-sm mt-1" onclick="addKitchenRow()"><i
                                    class="bi bi-plus-lg me-1"></i> เพิ่มรายการครัว</button>
                        </div>
                        <textarea name="main_kitchen_remark" class="form-control form-control-sm mt-2" rows="3"
                            placeholder="หมายเหตุเพิ่มเติม..."></textarea>
                    </div>

                    <div class="col-md-5 bg-sidebar p-4 rounded-4">
                        <h5 class="section-title mb-4"><i class="bi bi-gear-wide-connected"></i> 4. ด้านเทคนิคและงานช่าง
                        </h5>
                        <div class="mb-4">
                            <label class="fw-bold small text-muted">การจัดงานเลี้ยง:</label>
                            <textarea name="banquet_style" class="form-control form-control-sm bg-white"
                                rows="6"></textarea>
                        </div>
                        <div class="mb-4">
                            <label class="fw-bold small text-muted">งานช่างและภาพเสียง:</label>
                            <textarea name="equipment" class="form-control form-control-sm bg-white"
                                rows="5"></textarea>
                        </div>
                        <div class="mb-0">
                            <label class="fw-bold small text-muted">หมายเหตุเพิ่มเติม:</label>
                            <textarea name="remark" class="form-control form-control-sm bg-white" rows="2"></textarea>
                        </div>
                    </div>
                </div>

                <h5 class="section-title mb-4"><i class="bi bi-cup-hot-fill"></i> 5. รายละเอียดเมนูอาหารและเครื่องดื่ม
                </h5>
                <div class="table-responsive mb-5">
                    <table class="table table-sm table-hover align-middle border" id="menuTable">
                        <thead class="text-center text-secondary bg-light">
                            <tr>
                                <th width="10%">เวลา</th>
                                <th width="15%">ประเภทเมนู</th>
                                <th width="10%">Set</th>
                                <th>รายละเอียด</th>
                                <th width="10%">จำนวน</th>
                                <th width="12%">ราคา</th>
                                <th width="5%"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><input type="date" name="menu_time[]" class="form-control form-control-sm border-0"
                                        placeholder="10:30"></td>
                                <td><input type="text" name="menu_name[]" class="form-control form-control-sm border-0">
                                </td>
                                <td><input type="text" name="menu_set[]" class="form-control form-control-sm border-0">
                                </td>
                                <td><textarea name="menu_detail[]" class="form-control form-control-sm border-0"
                                        rows="1"></textarea></td>
                                <td><input type="text" name="menu_qty[]" class="form-control form-control-sm border-0">
                                </td>
                                <td><input type="text" name="menu_price[]" class="form-control form-control-sm border-0"
                                        placeholder="0.00"></td>
                                <td class="text-center"><button type="button" class="btn text-danger btn-sm border-0"
                                        onclick="removeRow(this)"><i class="bi bi-dash-circle"></i></button></td>
                            </tr>
                        </tbody>
                    </table>
                    <button type="button" class="btn btn-hotel-outline btn-sm mt-1" onclick="addMenuRow()"><i
                            class="bi bi-plus-lg me-1"></i> เพิ่มรายการอาหาร</button>
                </div>

                <h5 class="section-title mb-4"><i class="bi bi-palette-fill"></i> 6. การตกแต่งและการดูแลทำความสะอาด</h5>
                <div class="row g-4">
                    <div class="col-md-6">
                        <div class="p-4 border rounded-4 bg-white shadow-sm h-100">
                            <label class="fw-bold small text-muted mb-3">รายละเอียดฉากหลังและป้าย:</label>
                            <textarea name="backdrop_detail" class="form-control form-control-sm mb-4"
                                rows="3"></textarea>
                            <div class="p-3 border-dashed text-center bg-light">
                                <input type="file" name="backdrop_img" id="backdropInput"
                                    class="form-control form-control-sm mb-2" accept="image/*"
                                    onchange="previewImage(this)">
                                <input type="hidden" name="backdrop_img_path_ai" id="backdrop_img_path_ai">
                                <div id="imagePreviewContainer" class="text-center d-none">
                                    <img id="imagePreview" src="#" class="img-thumbnail mt-2"
                                        style="max-height: 150px;">
                                    <button type="button" class="btn btn-sm btn-link text-danger d-block mx-auto mt-2"
                                        onclick="clearPreview()">ลบรูปภาพ</button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="p-4 border rounded-4 bg-white shadow-sm h-100">
                            <label class="fw-bold small text-muted mb-3">พนักงานทำความสะอาดและพนักงานจัดดอกไม้:</label>
                            <textarea name="hk_florist_detail" class="form-control form-control-sm" rows="8"></textarea>
                        </div>
                    </div>
                </div>


            </div>
        </div>
    </form>
</div>

<script>
    // --- Image Preview Logic ---
    function previewImage(input) {
        const container = document.getElementById('imagePreviewContainer');
        const preview = document.getElementById('imagePreview');
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = e => {
                preview.src = e.target.result;
                container.classList.remove('d-none');
            }
            reader.readAsDataURL(input.files[0]);
        }
    }

    function clearPreview() {
        document.getElementById('backdropInput').value = "";
        document.getElementById('imagePreviewContainer').classList.add('d-none');
    }

    function updateCompanyLogo(select) {
        const logoImg = document.getElementById('companyLogo');
        const selectedOption = select.options[select.selectedIndex];
        const logoPath = selectedOption.getAttribute('data-logo');
        logoImg.src = logoPath ? logoPath : 'assets/img/default-company.png';
    }

    // --- Dynamic Row Logic ---
    function addScheduleRow() {
        const table = document.querySelector("#scheduleTable tbody");
        const row = table.insertRow();
        row.innerHTML = `
        <td><input type="date" name="schedule_date[]" class="form-control form-control-sm border-0 bg-light"></td>
        <td><input type="text" name="schedule_hour[]" class="form-control form-control-sm border-0 bg-light" placeholder="00:00 - 00:00"></td>
        <td><textarea name="schedule_function[]" class="form-control form-control-sm border-0 bg-light" rows="2"></textarea></td>
        <td><input type="number" name="schedule_guarantee[]" class="form-control form-control-sm border-0 bg-light"></td>
        <td><button type="button" class="btn text-danger btn-sm border-0" onclick="removeRow(this)"><i class="bi bi-dash-circle"></i></button></td>
    `;
    }

    function addKitchenRow() {
        const table = document.querySelector("#kitchenTable tbody");
        const row = table.insertRow();
        row.innerHTML = `
        <td><input type="date" name="k_date[]" class="form-control form-control-sm border-0 bg-light"></td>
        <td><input type="text" name="k_type[]" class="form-control form-control-sm border-0 bg-light"></td>
        <td><textarea name="k_item[]" class="form-control form-control-sm border-0 bg-light" rows="2"></textarea></td>
        <td><input type="number" name="k_qty[]" class="form-control form-control-sm border-0 bg-light text-center"></td>
        <td><button type="button" class="btn text-danger btn-sm border-0" onclick="removeRow(this)"><i class="bi bi-dash-circle"></i></button></td>
    `;
    }

    function addMenuRow() {
        const table = document.querySelector("#menuTable tbody");
        const row = table.insertRow();
        row.innerHTML = `
        <td><input type="date" name="menu_time[]" class="form-control form-control-sm border-0 bg-light"></td>
        <td><input type="text" name="menu_name[]" class="form-control form-control-sm border-0"></td>
        <td><input type="text" name="menu_set[]" class="form-control form-control-sm border-0 bg-light"></td>
        <td><textarea name="menu_detail[]" class="form-control form-control-sm border-0" rows="1"></textarea></td>
        <td><input type="text" name="menu_qty[]" class="form-control form-control-sm border-0"></td>
        <td><input type="text" name="menu_price[]" class="form-control form-control-sm border-0"></td>
        <td class="text-center"><button type="button" class="btn text-danger btn-sm border-0" onclick="removeRow(this)"><i class="bi bi-dash-circle"></i></i></button></td>
    `;
    }

    function removeRow(btn) {
        const rowCount = btn.closest("tbody").querySelectorAll("tr").length;
        if (rowCount > 1) {
            btn.closest("tr").remove();
        } else {
            alert("ต้องมีอย่างน้อย 1 แถวครับจาร");
        }
    }

</script>

<?php include "footer.php"; ?>