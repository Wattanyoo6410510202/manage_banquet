<?php
include "header.php";
include "config.php";

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
$active_tab = (isset($_GET['edit_user_id'])) ? 'user' : 'company';
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
        box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.05);
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

<div class="container-fluid py-4">
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
                                    <div class="mb-4">
                                        <label class="small fw-bold">ระดับสิทธิ์ (Role)</label>
                                        <select name="role" class="form-select">
                                            <option value="Admin" <?php echo (isset($edit_user['role']) && $edit_user['role'] == 'Admin') ? 'selected' : ''; ?>>Admin (ผู้ดูแลระบบ)
                                            </option>
                                            <option value="Staff" <?php echo (isset($edit_user['role']) && $edit_user['role'] == 'Staff') ? 'selected' : ''; ?>>Staff (พนักงาน)
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
                                            <td><span
                                                    class="badge bg-light text-dark border"><?php echo $u['role']; ?></span>
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

<script>
    $(document).ready(function () {
        // เรียกใช้งาน DataTable
        $('#companyTable').DataTable({
            language: { url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/th.json' },
            pageLength: 5,
            lengthMenu: [5, 10, 25, 50]
        });

        $('#userTable').DataTable({
            language: { url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/th.json' },
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
            reader.onload = function (e) {
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
        if (confirm(msg)) { window.location.href = url; }
    }
</script>

<?php include "footer.php"; ?>