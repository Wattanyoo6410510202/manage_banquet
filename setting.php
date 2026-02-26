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
    $stmt = $conn->prepare("SELECT id, username, role FROM users WHERE id = ?");
    $stmt->bind_param("i", $uid);
    $stmt->execute();
    $edit_user = $stmt->get_result()->fetch_assoc();
}
?>

<style>
    :root { --hotel-gold: #b89441; --hotel-gold-light: rgba(184, 148, 65, 0.1);  }
    .form-control:focus, .form-select:focus { border-color: var(--hotel-gold); box-shadow: 0 0 0 0.25rem var(--hotel-gold-light); }
    .text-gold { color: var(--hotel-gold) !important; }
    .preview-zone { width: 100px; height: 100px; border: 2px dashed #ddd; border-radius: 12px; overflow: hidden; background: #fdfdfd; position: relative; cursor: pointer; margin-bottom: 10px; }
    .btn-remove-preview { position: absolute; top: 5px; right: 5px; background: rgba(255,0,0,0.7); color: white; border-radius: 50%; width: 20px; height: 20px; font-size: 12px; display: none; border: none; z-index: 10; }
    .img-table-preview { width: 40px; height: 40px; object-fit: cover; cursor: zoom-in; border-radius: 5px; }
    .card { border: none; box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075); }
</style>

<div class="row g-4 mb-5">
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-body p-4">
                <h5 class="fw-bold mb-4 text-gold"><i class="bi bi-building me-2"></i><?php echo $edit_data ? 'แก้ไขบริษัท' : 'ลงทะเบียนบริษัท'; ?></h5>
                <form action="api/save_settings.php" method="POST" enctype="multipart/form-data">
                    <?php if($edit_data): ?> <input type="hidden" name="id" value="<?php echo $edit_data['id']; ?>"> <?php endif; ?>
                    
                    <div class="text-center mb-3">
                        <div class="preview-zone mx-auto d-flex align-items-center justify-content-center" onclick="document.getElementById('logoInput').click();">
                            <button type="button" class="btn-remove-preview" id="remove_img" onclick="resetPreview(event)"><i class="bi bi-x"></i></button>
                            <?php if (!empty($edit_data['logo_path']) && file_exists($edit_data['logo_path'])): ?>
                                <img src="<?php echo $edit_data['logo_path']; ?>" id="img_preview" style="width:100%; height:100%; object-fit:cover;">
                            <?php else: ?>
                                <div id="icon_placeholder" class="text-muted small"><i class="bi bi-image fs-2"></i><br>LOGO</div>
                                <img src="" id="img_preview" class="d-none" style="width:100%; height:100%; object-fit:cover;">
                            <?php endif; ?>
                        </div>
                        <input type="file" name="logo" id="logoInput" class="d-none" accept="image/*" onchange="previewImg(this)">
                        <input type="hidden" name="old_logo" value="<?php echo $edit_data['logo_path'] ?? ''; ?>">
                    </div>

                    <div class="mb-3">
                        <label class="small fw-bold">ชื่อบริษัท/โรงแรม</label>
                        <input type="text" name="company_name" class="form-control" value="<?php echo $edit_data['company_name'] ?? ''; ?>" required>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="small fw-bold">เบอร์โทร</label>
                            <input type="text" name="phone" class="form-control" value="<?php echo $edit_data['phone'] ?? ''; ?>">
                        </div>
                        <div class="col-6">
                            <label class="small fw-bold">ผู้ติดต่อ</label>
                            <input type="text" name="contact_name" class="form-control" value="<?php echo $edit_data['contact_name'] ?? ''; ?>">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="small fw-bold">อีเมล</label>
                        <input type="email" name="email" class="form-control" value="<?php echo $edit_data['email'] ?? ''; ?>">
                    </div>
                    <div class="mb-4">
                        <label class="small fw-bold">ที่อยู่</label>
                        <textarea name="address" class="form-control" rows="2"><?php echo $edit_data['address'] ?? ''; ?></textarea>
                    </div>
                    <button type="submit" class="btn btn-dark w-100 py-2"><i class="bi bi-save me-2 text-gold"></i>บันทึกบริษัท</button>
                    <?php if($edit_data): ?> <a href="setting.php" class="btn btn-light border w-100 mt-2">ยกเลิก</a> <?php endif; ?>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card h-100">
            <div class="card-header bg-white border-0 py-3"><h6 class="fw-bold mb-0">ทะเบียนสถานประกอบการ</h6></div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light small">
                            <tr><th class="ps-4">โลโก้</th><th>รายละเอียด</th><th>ติดต่อ</th><th class="text-end pe-4">จัดการ</th></tr>
                        </thead>
                        <tbody>
    <?php
    $res = $conn->query("SELECT * FROM companies ORDER BY id DESC");
    while($row = $res->fetch_assoc()):
    ?>
    <tr>
        <td class="ps-4">
            <img src="<?php echo $row['logo_path'] ?: 'img/default-logo.png'; ?>" 
                 class="img-table-preview shadow-sm" 
                 onclick="showFullImg('<?php echo $row['logo_path'] ?: 'img/default-logo.png'; ?>', '<?php echo $row['company_name']; ?>')">
        </td>
        <td>
            <div class="fw-bold small"><?php echo $row['company_name']; ?></div>
            <div class="text-muted" style="font-size: 11px;"><?php echo $row['address']; ?></div>
            <div class="text-muted" style="font-size: 11px;"><?php echo $row['email']; ?></div>
        </td>
        <td class="small">
            <div><i class="bi bi-phone me-1"></i><?php echo $row['phone']; ?></div>
        </td>
        <td class="text-end pe-4">
            <a href="setting.php?edit_id=<?php echo $row['id']; ?>" 
               class="btn btn-sm btn-light border">
                <i class="bi bi-pencil text-gold"></i>
            </a>
            
            <a href="api/save_settings.php?delete_id=<?php echo $row['id']; ?>" 
               class="btn btn-sm btn-light border text-danger" 
               onclick="return confirm('คุณแน่ใจหรือไม่ว่าต้องการลบข้อมูลบริษัท <?php echo $row['company_name']; ?>?');">
                <i class="bi bi-trash"></i>
            </a>
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

<hr class="my-5">

<div class="row g-4">
    <div class="col-lg-4">
        <div class="card">
            <div class="card-body p-4">
                <h5 class="fw-bold mb-4 text-gold"><i class="bi bi-person-gear me-2"></i><?php echo $edit_user ? 'แก้ไขผู้ใช้' : 'เพิ่มผู้ใช้งาน'; ?></h5>
                <form action="api/save_user.php" method="POST">
                    <?php if($edit_user): ?> <input type="hidden" name="id" value="<?php echo $edit_user['id']; ?>"> <?php endif; ?>
                        <div class="mb-3">
    <label class="small fw-bold">ชื่อ-นามสกุล</label>
    <input type="text" name="name" class="form-control" value="<?php echo $edit_user['name'] ?? ''; ?>" required>
</div>
                    <div class="mb-3">
                        <label class="small fw-bold">Username</label>
                        <input type="text" name="username" class="form-control" value="<?php echo $edit_user['username'] ?? ''; ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="small fw-bold">Password <?php echo $edit_user ? '(ว่างเพื่อใช้เดิม)' : ''; ?></label>
                        <div class="input-group">
                            <input type="password" name="password" id="passInput" class="form-control" <?php echo $edit_user ? '' : 'required'; ?>>
                            <button class="btn btn-outline-secondary" type="button" onclick="togglePass()"><i class="bi bi-eye"></i></button>
                        </div>
                    </div>
                    <div class="mb-4">
                        <label class="small fw-bold">สิทธิ์การใช้งาน (Role)</label>
                        <select name="role" class="form-select">
                            <option value="Admin" <?php echo (isset($edit_user['role']) && $edit_user['role']=='Admin')?'selected':''; ?>>Admin (ดูแลระบบ)</option>
                            <option value="Staff" <?php echo (isset($edit_user['role']) && $edit_user['role']=='Staff')?'selected':''; ?>>Staff (พนักงาน)</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-dark w-100 py-2">บันทึกข้อมูลผู้ใช้</button>
                    <?php if($edit_user): ?> <a href="setting.php" class="btn btn-light border w-100 mt-2">ยกเลิก</a> <?php endif; ?>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card h-100">
            <div class="card-header bg-white border-0 py-3"><h6 class="fw-bold mb-0">รายชื่อผู้มีสิทธิ์ใช้งาน</h6></div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light small">
                            <tr><th class="ps-4">ชื่อ-นามสกุล</th><th>Username</th><th>Role</th><th class="text-end pe-4">Action</th></tr>
                        </thead>
                        <tbody>
                            <?php
                            $users = $conn->query("SELECT * FROM users ORDER BY id DESC");
                            while($u = $users->fetch_assoc()):
                            ?>
                            <tr>
                                <td class="ps-4"><i class="bi bi-person-circle me-2 text-muted"></i><?php echo $u['name']; ?></td>
                                <td class="ps-4"><i class="bi bi-person-circle me-2 text-muted"></i><?php echo $u['username']; ?></td>
                                <td><span class="badge bg-light text-dark border fw-normal"><?php echo $u['role']; ?></span></td>
                                <td class="text-end pe-4">
                                    <a href="setting.php?edit_user_id=<?php echo $u['id']; ?>" class="btn btn-sm btn-light border"><i class="bi bi-pencil text-gold"></i></a>
                                    <a href="api/save_user.php?delete_id=<?php echo $u['id']; ?>" class="btn btn-sm btn-light border text-danger" onclick="return confirm('ลบผู้ใช้รายนี้?')"><i class="bi bi-trash"></i></a>
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

<div class="modal fade" id="imgModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header border-0 pb-0">
                <h6 class="modal-title fw-bold" id="modalTitle"></h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center p-4">
                <img src="" id="modalImg" class="img-fluid rounded" style="max-height: 400px;">
            </div>
        </div>
    </div>
</div>

<script>
// --- ส่วนของ Image Preview ---
function previewImg(input) {
    const preview = document.getElementById('img_preview');
    const placeholder = document.getElementById('icon_placeholder');
    const removeBtn = document.getElementById('remove_img');

    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            preview.src = e.target.result;
            preview.classList.remove('d-none');
            if(placeholder) placeholder.classList.add('d-none');
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
        if(placeholder) placeholder.classList.remove('d-none');
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
</script>

<?php include "footer.php"; ?>