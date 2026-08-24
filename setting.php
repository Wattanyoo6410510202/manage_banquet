<?php
include "config.php";
require_once 'includes/gatekeeper.php'; 
protect('admin_only'); // สั่งเลยว่าหน้านี้ "Admin เท่านั้น"

// --- ส่วนดึงข้อมูลแก้ไข (บริษัท) ---
$edit_data = null;
if (isset($_GET['edit_id'])) {
    $id = $_GET['edit_id'];
    $stmt = $conn->prepare("SELECT * FROM companies WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $edit_data = $stmt->get_result()->fetch_assoc();
}

// --- ส่วนดึงข้อมูลแก้ไข (User) ---
$edit_user = null;
if (isset($_GET['edit_user_id'])) {
    $uid = $_GET['edit_user_id'];
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->bind_param("i", $uid);
    $stmt->execute();
    $edit_user = $stmt->get_result()->fetch_assoc();
}

// เช็กว่าควรเปิด Tab ไหน (Auto-switch เมื่อกดแก้ไข)
$active_tab = $_GET['active_tab'] ?? ((isset($_GET['edit_user_id'])) ? 'user' : 'company');

// --- ข้อมูลสำหรับแท็บ "นำเข้าต้นทุน" (ใช้จับคู่/แสดงตัวอย่างฝั่ง JS ก่อนยืนยันนำเข้าจริง) ---
$import_categories = [];
$ic_res = $conn->query("SELECT id, category_name FROM master_menu_categories ORDER BY sort_order ASC, id ASC");
while ($row = $ic_res->fetch_assoc()) { $import_categories[] = $row; }

$import_menu_types = [];
$imt_res = $conn->query("SELECT id, category_id, type_name FROM master_menu_types");
while ($row = $imt_res->fetch_assoc()) { $import_menu_types[] = $row; }

$import_menu_items = [];
$imi_res = $conn->query("SELECT id, menu_type_id, menu_items, cost_per_pax FROM function_menu_details");
while ($row = $imi_res->fetch_assoc()) { $import_menu_items[] = $row; }
?>

<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">

<style>
:root {
    --hotel-gold: #b89441;
    --hotel-gold-light: rgba(184, 148, 65, 0.1);
    --hotel-gold-dark: #a38235;
}

/* --- Tabs --- */
.nav-tabs {
    border-bottom: 2px solid #eee;
    display: flex;
    flex-wrap: nowrap;
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
}

.nav-tabs::-webkit-scrollbar {
    display: none;
}

.nav-tabs .nav-link {
    border: none;
    color: #666;
    font-weight: 600;
    padding: 1rem 1.5rem;
    white-space: nowrap;
}

/* สีทองตอน Active ของ Tab */
.nav-tabs .nav-link.active {
    color: var(--hotel-gold) !important;
    background: none;
    border-bottom: 3px solid var(--hotel-gold);
}

/* --- DataTables & Buttons (Active State) --- */
/* ปุ่ม Pagination หน้าที่กำลังเปิด (Active) */
.page-item.active .page-link {
    background-color: var(--hotel-gold) !important;
    border-color: var(--hotel-gold) !important;
    color: white !important;
}

/* สีของลิงก์/ปุ่มเวลา Hover */
.btn-outline-secondary:hover {
    background-color: var(--hotel-gold);
    border-color: var(--hotel-gold);
    color: white;
}

/* ไฮไลท์แถวในตารางเมื่อเอาเม้าส์ชี้ */
.table-hover tbody tr:hover {
    background-color: var(--hotel-gold-light) !important;
}

/* --- Preview รูปภาพ --- */
.preview-zone {
    width: 100px;
    height: 100px;
    border: 2px dashed #ddd;
    border-radius: 12px;
    overflow: hidden;
    background: #fdfdfd;
    position: relative;
    cursor: pointer;
    transition: 0.3s;
}

/* เมื่อ Focus หรือ Active ที่โซนอัปโหลด */
.preview-zone:hover {
    border-color: var(--hotel-gold);
    background-color: var(--hotel-gold-light);
}

.btn-remove-preview {
    position: absolute;
    top: 5px;
    right: 5px;
    background: rgba(255, 0, 0, 0.7);
    color: white;
    border-radius: 50%;
    width: 24px;
    height: 24px;
    font-size: 14px;
    border: none;
    display: none;
    z-index: 10;
}

/* --- Table Styling --- */
.img-table-preview {
    width: 40px;
    height: 40px;
    object-fit: cover;
    border-radius: 8px;
    border: 1px solid transparent;
    transition: 0.2s;
}

.img-table-preview:hover {
    border-color: var(--hotel-gold);
    transform: scale(1.1);
}

.card {
    border: none;

    border-radius: 15px;
}

/* Responsive */
@media (max-width: 768px) {
    .container-fluid {
        padding-left: 10px;
        padding-right: 10px;
    }

    .card-body {
        padding: 1rem !important;
    }

    .tab-content {
        padding: 1.5rem 0 !important;
    }

    table.dataTable {
        font-size: 0.85rem;
    }

    .btn-sm {
        padding: 0.4rem 0.6rem;
    }
}
</style>

<div class="container-fluid p-0">
    <div id="alert-container"><?php include "assets/alert.php"; ?></div>

    <div class="card">
        <div class="card-body p-0">
            <ul class="nav nav-tabs px-4 pt-2" id="settingTab" role="tablist">
                <li class="nav-item">
                    <button class="nav-link <?php echo $active_tab == 'company' ? 'active' : ''; ?>"
                        data-bs-toggle="tab" data-bs-target="#company-pane">
                        <i class="bi bi-building me-2"></i>ตั้งค่าบริษัท
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link <?php echo $active_tab == 'user' ? 'active' : ''; ?>" data-bs-toggle="tab"
                        data-bs-target="#user-pane">
                        <i class="bi bi-people me-2"></i>จัดการผู้ใช้งาน
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link <?php echo $active_tab == 'import' ? 'active' : ''; ?>" data-bs-toggle="tab"
                        data-bs-target="#import-pane">
                        <i class="bi bi-file-earmark-spreadsheet me-2"></i>นำเข้าต้นทุน
                    </button>
                </li>
            </ul>

            <div class="tab-content p-4">

                <div class="tab-pane fade <?php echo $active_tab == 'company' ? 'show active' : ''; ?>"
                    id="company-pane">
                    <div class="row g-4">
                        <div class="col-xl-4">
                            <div class="p-3 border rounded-3 bg-light">
                                <h6 class="fw-bold mb-3 text-gold"><i
                                        class="bi bi-plus-circle me-2"></i><?php echo $edit_data ? 'แก้ไขข้อมูล' : 'เพิ่มบริษัทใหม่'; ?>
                                </h6>
                                <form action="api/save_settings.php" method="POST" enctype="multipart/form-data">
                                    <?php if ($edit_data): ?> <input type="hidden" name="id"
                                        value="<?php echo $edit_data['id']; ?>"> <?php endif; ?>

                                    <div class="text-center mb-3">
                                        <label class="small fw-bold mb-2 d-block">โลโก้บริษัท</label>
                                        <div class="preview-zone mx-auto d-flex align-items-center justify-content-center"
                                            onclick="document.getElementById('logoInput').click();">
                                            <button type="button" class="btn-remove-preview" id="remove_img"
                                                onclick="resetPreview(event)"><i class="bi bi-x"></i></button>
                                            <?php if (!empty($edit_data['logo_path']) && file_exists($edit_data['logo_path'])): ?>
                                            <img src="<?php echo $edit_data['logo_path']; ?>" id="img_preview"
                                                style="width:100%; height:100%; object-fit:cover;">
                                            <?php else: ?>
                                            <div id="icon_placeholder" class="text-muted small text-center"><i
                                                    class="bi bi-image fs-2"></i><br>อัปโหลดโลโก้</div>
                                            <img src="" id="img_preview" class="d-none"
                                                style="width:100%; height:100%; object-fit:cover;">
                                            <?php endif; ?>
                                        </div>
                                        <input type="file" name="logo" id="logoInput" class="d-none" accept="image/*"
                                            onchange="previewImg(this)">
                                        <input type="hidden" name="old_logo"
                                            value="<?php echo $edit_data['logo_path'] ?? ''; ?>">
                                    </div>

                                    <div class="text-center mb-3">
                                        <label class="small fw-bold mb-2 d-block">ตราประทับ</label>
                                        <div class="preview-zone mx-auto d-flex align-items-center justify-content-center"
                                            onclick="document.getElementById('stampInput').click();">
                                            <button type="button" class="btn-remove-preview" id="remove_stamp"
                                                onclick="resetStampPreview(event)"><i class="bi bi-x"></i></button>
                                            <?php if (!empty($edit_data['stamp_path']) && file_exists($edit_data['stamp_path'])): ?>
                                            <img src="<?php echo $edit_data['stamp_path']; ?>" id="stamp_preview"
                                                style="width:100%; height:100%; object-fit:cover;">
                                            <?php else: ?>
                                            <div id="stamp_placeholder" class="text-muted small text-center"><i
                                                    class="bi bi-stamp fs-2"></i><br>อัปโหลดตราประทับ</div>
                                            <img src="" id="stamp_preview" class="d-none"
                                                style="width:100%; height:100%; object-fit:cover;">
                                            <?php endif; ?>
                                        </div>
                                        <input type="file" name="stamp" id="stampInput" class="d-none" accept="image/*"
                                            onchange="previewStamp(this)">
                                        <input type="hidden" name="old_stamp"
                                            value="<?php echo $edit_data['stamp_path'] ?? ''; ?>">
                                    </div>

                                    <div class="mb-3">
                                        <label class="small fw-bold">ชื่อบริษัท/โรงแรม</label>
                                        <input type="text" name="company_name" class="form-control"
                                            value="<?php echo $edit_data['company_name'] ?? ''; ?>" required>
                                    </div>
                                    <div class="row g-2 mb-3">
                                        <div class="col-6">
                                            <label class="small fw-bold">เบอร์โทร</label>
                                            <input type="text" name="phone" class="form-control"
                                                value="<?php echo $edit_data['phone'] ?? ''; ?>">
                                        </div>
                                        <div class="col-6">
                                            <label class="small fw-bold">ผู้ติดต่อ</label>
                                            <input type="text" name="contact_name" class="form-control"
                                                value="<?php echo $edit_data['contact_name'] ?? ''; ?>">
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label class="small fw-bold">อีเมล</label>
                                        <input type="email" name="email" class="form-control"
                                            value="<?php echo $edit_data['email'] ?? ''; ?>">
                                    </div>
                                    <div class="mb-4">
                                        <label class="small fw-bold">ที่อยู่</label>
                                        <textarea name="address" class="form-control"
                                            rows="2"><?php echo $edit_data['address'] ?? ''; ?></textarea>
                                    </div>
                                    <button type="submit" class="btn btn-dark btn-sm px-3  flex-shrink-0"><i
                                            class="bi bi-save me-2 text-gold"></i>บันทึกข้อมูล</button>
                                    <?php if ($edit_data): ?> <a href="setting.php"
                                        class="btn btn-light border w-100 mt-2">ยกเลิกการแก้ไข</a> <?php endif; ?>
                                </form>
                            </div>
                        </div>
                        <div class="col-xl-8">
                            <table id="companyTable" class="table table-hover align-middle w-100">
                                <thead class="table-light">
                                    <tr>
                                        <th>โลโก้</th>
                                        <th>ตราประทับ</th>
                                        <th>ชื่อบริษัท</th>
                                        <th>ติดต่อ</th>
                                        <th class="text-end">จัดการ</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $res = $conn->query("SELECT * FROM companies ORDER BY id DESC");
                                    while ($row = $res->fetch_assoc()):
                                        ?>
                                    <tr>
                                        <td><img src="<?php echo $row['logo_path'] ?: 'img/default-logo.png'; ?>"
                                                class="img-table-preview shadow-sm"
                                                onclick="showFullImg(this.src, '<?php echo $row['company_name']; ?>')">
                                        </td>
                                        <td>
                                            <?php if (!empty($row['stamp_path']) && file_exists($row['stamp_path'])): ?>
                                            <img src="<?php echo $row['stamp_path']; ?>"
                                                class="img-table-preview shadow-sm"
                                                onclick="showFullImg(this.src, 'ตราประทับ <?php echo $row['company_name']; ?>')">
                                            <?php else: ?>
                                            <span class="text-muted small">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="fw-bold"><?php echo $row['company_name']; ?></div>
                                            <div class="text-muted x-small"><?php echo $row['address']; ?></div>
                                        </td>
                                        <td class="small">
                                            <div><i class="bi bi-phone text-gold me-1"></i><?php echo $row['phone']; ?>
                                            </div>
                                            <div class="text-muted"><i
                                                    class="bi bi-envelope me-1"></i><?php echo $row['email']; ?></div>
                                        </td>
                                        <td class="text-end">
                                            <a href="setting.php?edit_id=<?php echo $row['id']; ?>"
                                                class="btn btn-sm btn-outline-secondary"><i
                                                    class="bi bi-pencil"></i></a>
                                            <a href="api/save_settings.php?delete_id=<?php echo $row['id']; ?>"
                                                class="btn btn-sm btn-outline-danger"
                                                onclick="return confirm('ยืนยันการลบ?')"><i class="bi bi-trash"></i></a>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="tab-pane fade <?php echo $active_tab == 'user' ? 'show active' : ''; ?>" id="user-pane">
                    <div class="row g-4">
                        <div class="col-xl-4">
                            <div class="p-3 border rounded-3 bg-light">
                                <h6 class="fw-bold mb-3 text-gold"><i
                                        class="bi bi-person-plus me-2"></i><?php echo $edit_user ? 'แก้ไขผู้ใช้' : 'เพิ่มผู้ใช้งาน'; ?>
                                </h6>
                                <form action="api/save_user.php" method="POST">
                                    <?php if ($edit_user): ?> <input type="hidden" name="id"
                                        value="<?php echo $edit_user['id']; ?>"> <?php endif; ?>
                                    <div class="mb-3">
                                        <label class="small fw-bold">ชื่อ-นามสกุล</label>
                                        <input type="text" name="name" class="form-control"
                                            value="<?php echo $edit_user['name'] ?? ''; ?>" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="small fw-bold">Username</label>
                                        <input type="text" name="username" class="form-control"
                                            value="<?php echo $edit_user['username'] ?? ''; ?>" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="small fw-bold">Password
                                            <?php echo $edit_user ? '(เว้นว่างถ้าไม่เปลี่ยน)' : ''; ?></label>
                                        <div class="input-group">
                                            <input type="password" name="password" id="passInput" class="form-control"
                                                <?php echo $edit_user ? '' : 'required'; ?>>
                                            <button class="btn btn-outline-secondary" type="button"
                                                onclick="togglePass()"><i class="bi bi-eye"></i></button>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label class="small fw-bold">LINE User ID <small class="text-muted">(สำหรับแจ้งเตือน)</small></label>
                                        <input type="text" name="line_user_id" class="form-control"
                                            value="<?php echo $edit_user['line_user_id'] ?? ''; ?>" placeholder="Uxxxxxxxxxxx">
                                    </div>
                                    <div class="mb-4">
                                        <label class="small fw-bold">ระดับสิทธิ์ (Role)</label>
                                        <select name="role" class="form-select">
                                            <option value="Admin"
                                                <?php echo (isset($edit_user['role']) && $edit_user['role'] == 'Admin') ? 'selected' : ''; ?>>
                                                Admin (ผู้ดูแลระบบ)
                                            </option>
                                            <option value="Staff"
                                                <?php echo (isset($edit_user['role']) && $edit_user['role'] == 'Staff') ? 'selected' : ''; ?>>
                                                Staff (พนักงาน)
                                            </option>
                                            <option value="GM"
                                                <?php echo (isset($edit_user['role']) && $edit_user['role'] == 'GM') ? 'selected' : ''; ?>>
                                                GM (ผู้จัดการ)
                                            </option>
                                            <option value="Viewer"
                                                <?php echo (isset($edit_user['role']) && $edit_user['role'] == 'Viewer') ? 'selected' : ''; ?>>
                                                Viewer (ผู้ดูแลระบบ)
                                            </option>
                                            <option value="Technician"
                                                <?php echo (isset($edit_user['role']) && $edit_user['role'] == 'Technician') ? 'selected' : ''; ?>>
                                                Technician (ช่างเทคนิค)
                                            </option>
                                            <option value="Housekeeping"
                                                <?php echo (isset($edit_user['role']) && $edit_user['role'] == 'Housekeeping') ? 'selected' : ''; ?>>
                                                Housekeeping (เจ้าหน้าที่ทำความสะอาด)
                                            </option>
                                            <option value="Banquet_Staff"
                                                <?php echo (isset($edit_user['role']) && $edit_user['role'] == 'Banquet_Staff') ? 'selected' : ''; ?>>
                                                Banquet Staff (พนักงานจัดเลี้ยง)
                                            </option>
                                            <option value="Procurement"
                                                <?php echo (isset($edit_user['role']) && $edit_user['role'] == 'Procurement') ? 'selected' : ''; ?>>
                                                Procurement (เจ้าหน้าที่จัดซื้อ)
                                            </option>
                                        </select>
                                    </div>
                                    <button type="submit" class="btn btn-dark btn-sm px-3  flex-shrink-0"><i
                                            class="bi bi-save me-2 text-gold"></i>บันทึกข้อมูลผู้ใช้</button>
                                    <?php if ($edit_user): ?> <a href="setting.php?edit_user_id="
                                        class="btn btn-light border w-100 mt-2">ยกเลิก</a> <?php endif; ?>
                                </form>
                            </div>
                        </div>
                        <div class="col-xl-8">
                            <table id="userTable" class="table table-hover align-middle w-100">
                                <thead class="table-light">
                                    <tr>
                                        <th>ชื่อ-นามสกุล</th>
                                        <th>Username</th>
                                        <th>Role</th>
                                        <th>LINE</th>
                                        <th class="text-end">จัดการ</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $users = $conn->query("SELECT * FROM users ORDER BY id DESC");
                                    while ($u = $users->fetch_assoc()):
                                        $displayName = !empty($u['name']) ? $u['name'] : '<span class="text-muted">ไม่ระบุชื่อ</span>';
                                        ?>
                                    <tr>
                                        <td><i
                                                class="bi bi-person-circle me-2 text-muted"></i><?php echo $displayName; ?>
                                        </td>
                                        <td><code><?php echo $u['username']; ?></code></td>
                                        <td>
                                            <?php 
        // ตั้งค่า icon และสีตาม Role
        switch (strtolower($u['role'])) {
            case 'admin':
                $icon = 'bi-shield-check';
                $color = 'text-danger'; // สีแดงดูมีอำนาจ
                break;
            case 'staff':
                $icon = 'bi-graph-up-arrow';
                $color = 'text-success'; // สีเขียวสายทำยอด
                break;
            case 'GM':
                $icon = 'bi-person-badge';
                $color = 'text-primary'; // สีน้ำเงินสายคุม
                break;
                case 'viewer':
                $icon = 'bi-binoculars';
                $color = 'text-primary'; // สีน้ำเงินสายคุม
                break;
            default:
                $icon = 'bi-person';
                $color = 'text-secondary';
                break;
        }
    ?>
                                            <span class="badge bg-light text-dark border fw-normal">
                                                <i class="bi <?php echo $icon; ?> me-1 <?php echo $color; ?>"></i>
                                                <?php echo htmlspecialchars($u['role']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if (!empty($u['line_user_id'])): ?>
                                                <span class="small text-muted" title="<?= htmlspecialchars($u['line_user_id']) ?>">
                                                    <i class="bi bi-line text-success"></i> ผูกแล้ว
                                                </span>
                                                <button type="button" class="btn btn-sm btn-outline-success ms-1 py-0 px-1"
                                                    style="font-size: 0.7rem;"
                                                    onclick="testLine(<?php echo $u['id']; ?>, this)"
                                                    title="ส่งข้อความทดสอบหาผู้ใช้นี้ทาง LINE">
                                                    <i class="bi bi-send"></i> ทดสอบ
                                                </button>
                                            <?php else: ?>
                                                <span class="small text-muted"><i class="bi bi-dash"></i></span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end">
                                            <a href="setting.php?edit_user_id=<?php echo $u['id']; ?>"
                                                class="btn btn-sm btn-outline-secondary"><i
                                                    class="bi bi-pencil"></i></a>
                                            <button
                                                onclick="confirmDelete('api/save_user.php?delete_id=<?php echo $u['id']; ?>', 'ลบผู้ใช้ <?php echo $u['username']; ?>?')"
                                                class="btn btn-sm btn-outline-danger"><i
                                                    class="bi bi-trash"></i></button>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="tab-pane fade <?php echo $active_tab == 'import' ? 'show active' : ''; ?>" id="import-pane">
                    <div class="row g-4">
                        <div class="col-xl-4">
                            <div class="p-3 border rounded-3 bg-light">
                                <h6 class="fw-bold mb-3 text-gold"><i
                                        class="bi bi-file-earmark-spreadsheet me-2"></i>นำเข้าต้นทุนอาหารจาก Excel</h6>
                                <p class="small text-muted">อัปโหลดไฟล์ Excel (.xlsx) ที่แยกชีทตามชุดราคา
                                    แต่ละชีทต้องมีคอลัมน์ "หมวด", "เมนู" และ "Total Cost"
                                    ระบบจะจับคู่ชื่อเมนู + ประเภทอาหารในชีท เพื่ออัปเดต/เพิ่มราคาทุนต่อหัวให้อัตโนมัติ</p>

                                <button type="button" id="btnDownloadSample" class="btn btn-outline-secondary btn-sm px-3 mb-3">
                                    <i class="bi bi-download me-1"></i>ดาวน์โหลดไฟล์ตัวอย่าง
                                </button>

                                <div class="mb-3">
                                    <input type="file" id="importFileInput" class="form-control form-control-sm"
                                        accept=".xlsx,.xls">
                                </div>
                                <button type="button" id="btnParseFile" class="btn btn-dark btn-sm px-3" disabled>
                                    <i class="bi bi-search me-1"></i>อ่านไฟล์ / จับคู่ชีท
                                </button>

                                <div id="sheetMappingZone" class="mt-3 d-none">
                                    <label class="small fw-bold mb-2 d-block">จับคู่ชีท → กลุ่มอาหาร (หมวดราคา)</label>
                                    <div id="sheetMappingList"></div>
                                    <button type="button" id="btnGeneratePreview"
                                        class="btn btn-outline-dark btn-sm w-100 mt-2">
                                        <i class="bi bi-eye me-1"></i>สร้างตัวอย่างก่อนนำเข้า
                                    </button>
                                </div>

                                <div id="importSummaryZone" class="mt-3 d-none">
                                    <div class="alert alert-light border small mb-2" id="importSummaryText"></div>
                                    <button type="button" id="btnConfirmImport" class="btn btn-success w-100 fw-bold">
                                        <i class="bi bi-cloud-upload me-1"></i>ยืนยันนำเข้าข้อมูล
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-8">
                            <div class="table-responsive" style="max-height: 640px;">
                                <table class="table table-hover align-middle table-sm" id="importPreviewTable">
                                    <thead class="table-light">
                                        <tr class="small text-muted">
                                            <th>กลุ่มอาหาร</th>
                                            <th>หมวด</th>
                                            <th>เมนู</th>
                                            <th class="text-end">ต้นทุนใหม่</th>
                                            <th>สถานะ</th>
                                        </tr>
                                    </thead>
                                    <tbody id="importPreviewBody">
                                        <tr>
                                            <td colspan="5" class="text-center text-muted py-4">อัปโหลดไฟล์แล้วกด
                                                "อ่านไฟล์ / จับคู่ชีท" เพื่อเริ่มต้น</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="imgModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header border-0 pb-0">
                <h6 class="modal-title fw-bold" id="modalTitle"></h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center p-4">
                <img src="" id="modalImg" class="img-fluid rounded shadow" style="max-height: 500px;">
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>

<script>
$(document).ready(function() {
    // เรียกใช้งาน DataTable
    $('#companyTable').DataTable({
        language: {
            url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/th.json'
        },
        pageLength: 5,
        lengthMenu: [5, 10, 25, 50]
    });

    $('#userTable').DataTable({
        language: {
            url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/th.json'
        },
        pageLength: 10
    });

    $('#salesTargetTable').DataTable({
        language: {
            url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/th.json'
        },
        pageLength: 10
    });
});

// --- ฟังก์ชันรูปภาพ ---
function previewImg(input) {
    const preview = document.getElementById('img_preview');
    const placeholder = document.getElementById('icon_placeholder');
    const removeBtn = document.getElementById('remove_img');
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            preview.src = e.target.result;
            preview.classList.remove('d-none');
            if (placeholder) placeholder.classList.add('d-none');
            removeBtn.style.display = 'block';
        }
        reader.readAsDataURL(input.files[0]);
    }
}

function resetPreview(event) {
    event.stopPropagation();
    const input = document.getElementById('logoInput');
    const preview = document.getElementById('img_preview');
    const placeholder = document.getElementById('icon_placeholder');
    const removeBtn = document.getElementById('remove_img');
    const oldLogo = document.getElementsByName('old_logo')[0].value;

    input.value = "";
    if (oldLogo && oldLogo !== "") {
        preview.src = oldLogo;
        removeBtn.style.display = 'none';
    } else {
        preview.src = "";
        preview.classList.add('d-none');
        if (placeholder) placeholder.classList.remove('d-none');
        removeBtn.style.display = 'none';
    }
}

function showFullImg(src, title) {
    document.getElementById('modalImg').src = src;
    document.getElementById('modalTitle').innerText = title;
    new bootstrap.Modal(document.getElementById('imgModal')).show();
}

function togglePass() {
    const p = document.getElementById('passInput');
    p.type = (p.type === "password") ? "text" : "password";
}

function confirmDelete(url, msg) {
    if (confirm(msg)) {
        window.location.href = url;
    }
}

// --- ทดสอบส่ง LINE หาผู้ใช้รายคน ---
async function testLine(userId, btn) {
    const original = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
    try {
        const fd = new FormData();
        fd.append('user_id', userId);
        const res = await fetch('api/test_line.php', { method: 'POST', body: fd });
        const data = await res.json();
        alert((data.ok ? '✅ ' : '❌ ') + data.message);
    } catch (e) {
        alert('❌ เชื่อมต่อเซิร์ฟเวอร์ไม่สำเร็จ กรุณาลองใหม่');
    } finally {
        btn.disabled = false;
        btn.innerHTML = original;
    }
}

// --- ฟังก์ชันรูปตราประทับ ---
function previewStamp(input) {
    const preview = document.getElementById('stamp_preview');
    const placeholder = document.getElementById('stamp_placeholder');
    const removeBtn = document.getElementById('remove_stamp');
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            preview.src = e.target.result;
            preview.classList.remove('d-none');
            if (placeholder) placeholder.classList.add('d-none');
            removeBtn.style.display = 'block';
        }
        reader.readAsDataURL(input.files[0]);
    }
}

function resetStampPreview(event) {
    event.stopPropagation();
    const input = document.getElementById('stampInput');
    const preview = document.getElementById('stamp_preview');
    const placeholder = document.getElementById('stamp_placeholder');
    const removeBtn = document.getElementById('remove_stamp');
    const oldStamp = document.getElementsByName('old_stamp')[0].value;

    input.value = "";
    if (oldStamp && oldStamp !== "") {
        preview.src = oldStamp;
        removeBtn.style.display = 'none';
    } else {
        preview.src = "";
        preview.classList.add('d-none');
        if (placeholder) placeholder.classList.remove('d-none');
        removeBtn.style.display = 'none';
    }
}

// ==== นำเข้าต้นทุนจาก Excel ====
const importCategories = <?= json_encode($import_categories, JSON_UNESCAPED_UNICODE) ?>;
const importMenuTypes = <?= json_encode($import_menu_types, JSON_UNESCAPED_UNICODE) ?>;
const importMenuItems = <?= json_encode($import_menu_items, JSON_UNESCAPED_UNICODE) ?>;

let importWorkbookSheets = {}; // sheetName -> [{type_name, menu_name, cost}]
let importFinalRows = [];

function escapeHtml(str) {
    return String(str).replace(/[&<>"']/g, function (c) {
        return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
}

// สร้างไฟล์ตัวอย่างให้ดาวน์โหลด ให้เห็นโครงสร้างคอลัมน์/การจัดกลุ่มที่ระบบอ่านได้ถูกต้อง
document.getElementById('btnDownloadSample').addEventListener('click', function () {
    const headerRow = ['หมวด', 'ลำดับ', 'เมนู', 'ต้นทุนค่าวัตถุดิบ', 'ต้นทุนสูญเสีย', 'ต้นทุนแฝง', 'Total Cost'];
    const titleRow = ['ต้นทุนอาหารจัดเลี้ยง (ตัวอย่างไฟล์นำเข้า)'];
    const blankRow = ['', '', '', '', '', '', ''];

    const sheet1 = [
        titleRow,
        headerRow,
        ['เมนูแกง/เมนูต้ม', 'A01', 'ต้มข่าไก่', 174.35, 8.72, 20, 203.07],
        ['', 'A02', 'ต้มยำไก่', 152.57, 7.63, 20, 180.20],
        ['', 'A03', 'มัสมั่นไก่', 168.30, 8.42, 20, 196.72],
        blankRow,
        ['เมนูผัด', 'B01', 'ผัดขี้เมาไก่สับ', 51.26, 2.56, 20, 73.82],
        ['', 'B02', 'ผัดผักรวมไก่', 150.37, 7.52, 20, 177.89],
        blankRow,
        ['เมนูของหวาน', 'C01', 'กล้วยบวดชี', 82.50, 4.13, 20, 106.63],
    ];

    const sheet2 = [
        titleRow,
        headerRow,
        ['เมนูทอด', 'A01', 'ไก่ทอดน้ำปลา', 124.30, 6.22, 20, 150.52],
        ['', 'A02', 'ไข่ลูกเขย', 76.08, 3.80, 20, 99.88],
        blankRow,
        ['เมนูข้าว', 'B01', 'ข้าวสวย', 44.00, 2.20, 20, 66.20],
    ];

    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, XLSX.utils.aoa_to_sheet(sheet1), 'ไทยเซ็ต 3000');
    XLSX.utils.book_append_sheet(wb, XLSX.utils.aoa_to_sheet(sheet2), 'ไทยเซ็ต 3500');
    XLSX.writeFile(wb, 'ตัวอย่างนำเข้าต้นทุน.xlsx');
});

document.getElementById('importFileInput').addEventListener('change', function () {
    document.getElementById('btnParseFile').disabled = !this.files.length;
    document.getElementById('sheetMappingZone').classList.add('d-none');
    document.getElementById('importSummaryZone').classList.add('d-none');
});

document.getElementById('btnParseFile').addEventListener('click', function () {
    const file = document.getElementById('importFileInput').files[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = function (e) {
        try {
            const data = new Uint8Array(e.target.result);
            const workbook = XLSX.read(data, { type: 'array' });
            importWorkbookSheets = {};
            workbook.SheetNames.forEach(function (sheetName) {
                importWorkbookSheets[sheetName] = parseCostSheet(workbook.Sheets[sheetName]);
            });
            renderSheetMapping();
        } catch (err) {
            console.error(err);
            alert('อ่านไฟล์ Excel ไม่สำเร็จ: ' + err.message);
        }
    };
    reader.readAsArrayBuffer(file);
});

// อ่าน sheet แบบไม่ผูกตำแหน่งแถว/คอลัมน์ตายตัว หาแถวหัวตารางจากคำว่า "เมนู" แล้วจับคอลัมน์จากชื่อหัวตาราง
function parseCostSheet(ws) {
    const rows = XLSX.utils.sheet_to_json(ws, { header: 1, defval: '', raw: true });
    let headerRowIdx = -1, idxType = -1, idxName = -1, idxCost = -1;

    for (let i = 0; i < rows.length; i++) {
        const cells = rows[i].map(c => String(c).trim());
        const ni = cells.indexOf('เมนู');
        if (ni !== -1) {
            headerRowIdx = i;
            idxType = cells.indexOf('หมวด');
            idxName = ni;
            idxCost = cells.indexOf('Total Cost');
            break;
        }
    }
    if (headerRowIdx === -1 || idxName === -1 || idxCost === -1) return [];

    // คอลัมน์ "หมวด" มี 2 แบบที่เจอในไฟล์จริง:
    //  1) ไฟล์ไทยเซ็ต: แถวแรกของกลุ่มมีป้ายลำดับคอร์ส ("รายการที่1") แล้วแถวถัดไปมีชื่อหมวดจริง
    //     ("เมนูแกง/เมนูต้ม" ฯลฯ) ซึ่งครอบคลุมทั้งกลุ่ม (รวมแถวแรกด้วย) — กรณีนี้ใช้ชื่อหมวดจริงเป็นหลัก
    //     แต่บางกลุ่มท้ายๆ (เช่น ข้าว/ของหวาน) ดันไม่มีชื่อหมวดจริงตามมาเลย เหลือแต่ป้ายลำดับคอร์สเปล่าๆ
    //     — กรณีนี้ "รายการที่N" ไม่ใช่ชื่อหมวดที่ระบบใช้จริงสำหรับไทยเซ็ต เลยต้องปล่อยว่างให้กรอกเอง
    //  2) ไฟล์โต๊ะจีน: ทุกกลุ่มมีแค่ป้ายลำดับคอร์ส ("รายการที่1"..."รายการที่6") ไม่มีชื่อหมวดจริงเลยทั้งชีท
    //     ซึ่งตรงกับชื่อประเภทอาหารที่ใช้จริงในระบบสำหรับหมวดโต๊ะจีนอยู่แล้ว ("รายการที่ 1" ฯลฯ)
    //     กรณีนี้ต้องใช้ป้ายลำดับคอร์สนั่นแหละเป็นชื่อหมวด ไม่ใช่ทิ้งไปเฉยๆ
    // แยก 2 กรณีนี้ด้วยการดูทั้งชีท: ถ้าไม่มีกลุ่มไหนในชีทมีชื่อหมวดจริงเลย (ทุกกลุ่มใช้ป้ายลำดับคอร์สล้วนๆ)
    // แปลว่าชีทนี้ใช้ป้ายลำดับคอร์สเป็นชื่อหมวดจริง (แบบโต๊ะจีน) — แต่ถ้าชีทมีชื่อหมวดจริงอยู่แล้วในกลุ่มส่วนใหญ่
    // กลุ่มที่เหลือป้ายลำดับคอร์สเปล่าๆ ถือเป็นข้อมูลไม่ครบ (แบบไทยเซ็ต) ต้องให้ผู้ใช้กรอกเอง ไม่เดาให้
    const courseLabelPattern = /^รายการที่\s*(\d+)$/;
    const blocks = []; // { items: [{name, cost}], realType, courseLabelType }
    let block = [];
    let realType = '';
    let courseLabelType = '';

    function flushBlock() {
        if (!block.length) return;
        blocks.push({ items: block, realType, courseLabelType });
        block = [];
        realType = '';
        courseLabelType = '';
    }

    for (let i = headerRowIdx + 1; i < rows.length; i++) {
        const r = rows[i];
        const typeRaw = (idxType !== -1 ? String(r[idxType] ?? '').trim() : '');
        const name = String(r[idxName] ?? '').trim();
        const cost = parseFloat(r[idxCost]);

        if (!name || isNaN(cost)) {
            flushBlock(); // แถวคั่นกลุ่ม/แถวว่าง = จบกลุ่มปัจจุบัน
            continue;
        }

        if (typeRaw) {
            const courseMatch = typeRaw.match(courseLabelPattern);
            if (courseMatch) {
                if (!courseLabelType) courseLabelType = 'รายการที่ ' + courseMatch[1];
            } else {
                realType = typeRaw; // ชื่อหมวดจริง มาทีหลังก็ทับป้ายลำดับคอร์สได้เสมอ
            }
        }
        block.push({ name, cost: Math.round(cost * 100) / 100 });
    }
    flushBlock();

    // ทั้งชีทไม่มีกลุ่มไหนมีชื่อหมวดจริงเลย = ชีทนี้ใช้ป้ายลำดับคอร์สเป็นชื่อหมวดจริง (แบบโต๊ะจีน)
    const sheetUsesCourseLabelsOnly = blocks.length > 0 && blocks.every(b => !b.realType);

    const parsed = [];
    blocks.forEach(b => {
        const finalType = b.realType || (sheetUsesCourseLabelsOnly ? b.courseLabelType : '');
        b.items.forEach(item => parsed.push({ type_name: finalType, menu_name: item.name, cost: item.cost }));
    });

    return parsed;
}

// เดากลุ่มอาหารจากตัวเลขในชื่อชีท เทียบกับตัวเลขในชื่อกลุ่มอาหารที่มีอยู่แล้ว
function guessCategoryId(sheetName) {
    const m = sheetName.match(/\d[\d,]*/);
    if (!m) return '';
    const digits = m[0].replace(/,/g, '');
    const found = importCategories.find(c => c.category_name.replace(/[^\d]/g, '') === digits);
    return found ? found.id : '';
}

function renderSheetMapping() {
    const zone = document.getElementById('sheetMappingList');
    zone.innerHTML = '';
    const catOptions = importCategories.map(c => `<option value="${c.id}">${escapeHtml(c.category_name)}</option>`).join('');

    Object.keys(importWorkbookSheets).forEach(function (sheetName) {
        const rows = importWorkbookSheets[sheetName];
        const guessId = guessCategoryId(sheetName);
        const div = document.createElement('div');
        div.className = 'd-flex align-items-start gap-2 mb-2 p-2 border rounded-2 bg-white';
        div.innerHTML = `
            <input type="checkbox" class="form-check-input sheet-import-check mt-1" data-sheet="${escapeHtml(sheetName)}" ${rows.length ? 'checked' : ''} ${rows.length ? '' : 'disabled'}>
            <div class="flex-grow-1">
                <div class="small fw-semibold">${escapeHtml(sheetName)} <span class="text-muted">(${rows.length} เมนู)</span>${rows.length ? '' : ' <span class="text-danger">(ไม่พบคอลัมน์ที่ต้องใช้)</span>'}</div>
                <select class="form-select form-select-sm sheet-category-select" data-sheet="${escapeHtml(sheetName)}">
                    <option value="">-- เลือกกลุ่มอาหาร --</option>
                    ${catOptions}
                </select>
            </div>`;
        zone.appendChild(div);
        if (guessId) {
            div.querySelector('.sheet-category-select').value = guessId;
        }
    });

    document.getElementById('sheetMappingZone').classList.remove('d-none');
    document.getElementById('importSummaryZone').classList.add('d-none');
    document.getElementById('importPreviewBody').innerHTML =
        '<tr><td colspan="5" class="text-center text-muted py-4">เลือกกลุ่มอาหารให้ครบ แล้วกด "สร้างตัวอย่างก่อนนำเข้า"</td></tr>';
}

document.getElementById('btnGeneratePreview').addEventListener('click', function () {
    const checks = document.querySelectorAll('.sheet-import-check');
    const finalRows = [];
    let missingMap = false;

    checks.forEach(function (chk) {
        if (!chk.checked) return;
        const sheetName = chk.getAttribute('data-sheet');
        const sel = document.querySelector('.sheet-category-select[data-sheet="' + CSS.escape(sheetName) + '"]');
        const catId = sel.value;
        if (!catId) { missingMap = true; sel.classList.add('is-invalid'); return; }
        sel.classList.remove('is-invalid');
        const catName = (importCategories.find(c => String(c.id) === String(catId)) || {}).category_name || '';
        importWorkbookSheets[sheetName].forEach(function (row) {
            finalRows.push({
                category_id: parseInt(catId, 10),
                category_name: catName,
                type_name: row.type_name,
                menu_name: row.menu_name,
                cost: row.cost
            });
        });
    });

    if (missingMap) {
        alert('กรุณาเลือกกลุ่มอาหารให้ครบทุกชีทที่ติ๊กเลือกไว้');
        return;
    }
    if (!finalRows.length) {
        alert('ไม่มีรายการให้นำเข้า กรุณาติ๊กเลือกอย่างน้อย 1 ชีท');
        return;
    }

    renderPreview(finalRows);
});

function renderPreview(finalRows) {
    const typeLookup = {}; // "catId|typeName" -> type id
    importMenuTypes.forEach(function (t) {
        typeLookup[t.category_id + '|' + t.type_name.trim()] = t.id;
    });
    const itemLookup = {}; // "typeId|menuName" -> {id, cost}
    importMenuItems.forEach(function (m) {
        itemLookup[m.menu_type_id + '|' + m.menu_items.trim()] = { id: m.id, cost: parseFloat(m.cost_per_pax) || 0 };
    });

    let newCount = 0, updateCount = 0, unchangedCount = 0, unresolvedCount = 0;
    const newTypeSet = new Set();

    const bodyRows = finalRows.map(function (row, idx) {
        const typeNameTrim = row.type_name.trim();

        if (typeNameTrim === '') {
            unresolvedCount++;
            return `<tr data-row-idx="${idx}">
                <td class="small">${escapeHtml(row.category_name)}</td>
                <td class="small"><input type="text" class="form-control form-control-sm import-type-fix is-invalid" data-idx="${idx}" placeholder="ระบุหมวด เช่น เมนูข้าว"></td>
                <td class="small">${escapeHtml(row.menu_name)}</td>
                <td class="small text-end">${row.cost.toFixed(2)}</td>
                <td><span class="badge bg-danger-subtle text-danger">ต้องระบุหมวดก่อน</span></td>
            </tr>`;
        }

        const typeKey = row.category_id + '|' + typeNameTrim;
        const typeId = typeLookup[typeKey];
        let statusHtml;

        if (typeId === undefined) {
            newTypeSet.add(row.category_name + ' / ' + row.type_name);
            newCount++;
            statusHtml = '<span class="badge bg-primary-subtle text-primary">ใหม่ (สร้างประเภท+เมนู)</span>';
        } else {
            const itemKey = typeId + '|' + row.menu_name.trim();
            const existing = itemLookup[itemKey];
            if (!existing) {
                newCount++;
                statusHtml = '<span class="badge bg-primary-subtle text-primary">เมนูใหม่</span>';
            } else if (Math.abs(existing.cost - row.cost) >= 0.005) {
                updateCount++;
                statusHtml = `<span class="badge bg-warning-subtle text-warning-emphasis">อัปเดต ${existing.cost.toFixed(2)} &rarr; ${row.cost.toFixed(2)}</span>`;
            } else {
                unchangedCount++;
                statusHtml = '<span class="badge bg-light text-muted border">ไม่เปลี่ยนแปลง</span>';
            }
        }

        return `<tr data-row-idx="${idx}">
            <td class="small">${escapeHtml(row.category_name)}</td>
            <td class="small">${escapeHtml(row.type_name)}</td>
            <td class="small">${escapeHtml(row.menu_name)}</td>
            <td class="small text-end">${row.cost.toFixed(2)}</td>
            <td>${statusHtml}</td>
        </tr>`;
    });

    document.getElementById('importPreviewBody').innerHTML =
        bodyRows.join('') || '<tr><td colspan="5" class="text-center text-muted py-4">ไม่มีรายการ</td></tr>';

    document.getElementById('importSummaryText').innerHTML =
        `รวม <b>${finalRows.length}</b> รายการ &nbsp;|&nbsp; ` +
        `<span class="text-primary">เมนูใหม่ ${newCount}</span> &nbsp;|&nbsp; ` +
        `<span class="text-warning-emphasis">อัปเดตราคา ${updateCount}</span> &nbsp;|&nbsp; ` +
        `<span class="text-muted">ไม่เปลี่ยนแปลง ${unchangedCount}</span>` +
        (newTypeSet.size ? `<br><span class="text-danger small">จะสร้างประเภทอาหารใหม่ ${newTypeSet.size} รายการ: ${[...newTypeSet].map(escapeHtml).join(', ')}</span>` : '') +
        (unresolvedCount ? `<br><span class="text-danger fw-bold small" id="unresolvedCountText">⚠ มี ${unresolvedCount} รายการที่ไม่มี "หมวด" ในไฟล์ Excel — กรุณาพิมพ์ระบุในตารางด้านขวาก่อนยืนยันนำเข้า</span>` : '');

    importFinalRows = finalRows;
    document.getElementById('importSummaryZone').classList.remove('d-none');
    updateConfirmButtonState();
}

// ผูก event ให้ช่องกรอกหมวดที่ขาดหาย อัปเดตข้อมูลจริง + เปิด/ปิดปุ่มยืนยันตามความครบถ้วน
document.getElementById('importPreviewBody').addEventListener('input', function (e) {
    if (!e.target.classList.contains('import-type-fix')) return;
    const idx = parseInt(e.target.getAttribute('data-idx'), 10);
    const val = e.target.value.trim();
    importFinalRows[idx].type_name = val;
    e.target.classList.toggle('is-invalid', val === '');
    updateConfirmButtonState();
});

function updateConfirmButtonState() {
    const hasUnresolved = importFinalRows.some(r => r.type_name.trim() === '');
    document.getElementById('btnConfirmImport').disabled = hasUnresolved;
}

document.getElementById('btnConfirmImport').addEventListener('click', function () {
    if (!importFinalRows.length) return;
    if (importFinalRows.some(r => r.type_name.trim() === '')) {
        alert('กรุณาระบุ "หมวด" ให้ครบทุกรายการที่ขึ้นสีแดงก่อนยืนยันนำเข้า');
        return;
    }
    if (!confirm('ยืนยันนำเข้าข้อมูลต้นทุน ' + importFinalRows.length + ' รายการ? ระบบจะอัปเดต/เพิ่มราคาทุนต่อหัวในระบบทันที')) return;

    const btn = this;
    const original = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>กำลังนำเข้า...';

    fetch('api/import_food_cost.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            rows: importFinalRows.map(r => ({
                category_id: r.category_id,
                type_name: r.type_name,
                menu_name: r.menu_name,
                cost: r.cost
            }))
        })
    })
        .then(res => res.json())
        .then(res => {
            if (res.status === 'success') {
                let msg = `นำเข้าสำเร็จ\nเพิ่มใหม่: ${res.inserted} รายการ\nอัปเดตราคา: ${res.updated} รายการ\nไม่เปลี่ยนแปลง: ${res.unchanged} รายการ\nสร้างประเภทอาหารใหม่: ${res.types_created} รายการ`;
                if (res.errors && res.errors.length) {
                    msg += `\n\nข้อผิดพลาด ${res.errors.length} รายการ:\n` + res.errors.slice(0, 10).join('\n');
                }
                alert(msg);
                location.href = 'setting.php?active_tab=import';
            } else {
                alert('เกิดข้อผิดพลาด: ' + (res.message || 'ไม่ทราบสาเหตุ'));
                btn.disabled = false;
                btn.innerHTML = original;
            }
        })
        .catch(err => {
            console.error(err);
            alert('การเชื่อมต่อล้มเหลว');
            btn.disabled = false;
            btn.innerHTML = original;
        });
});
</script>

<?php include "footer.php"; ?>