<?php
// includes/gatekeeper.php
require_once 'db.php';
require_once 'auth.php';

function protect($page_type)
{
    // 1. กำหนดกลุ่มสิทธิ์ (ใช้ตัวเล็กทั้งหมดเพื่อความชัวร์)
    switch ($page_type) {
        case 'admin_only':
            $roles = ['admin'];
            break;
        case 'sale_only':
            $roles = ['staff'];
            break;
        case 'GM_only':
            $roles = ['gm'];
            break;
        case 'all_staff':
            $roles = ['Admin', 'Staff', 'G'];
            break;
        case 'viewer':
            $roles = ['viewer'];
            break;
        case 'all':
            $roles = ['admin', 'staff', 'gm', 'viewer'];
            break;
        default:
            $roles = [];
    }

    // 2. เช็คสิทธิ์ (ส่ง Array ที่เป็นตัวเล็กทั้งหมดไป)
    check_access($roles);

    // 3. โหลด Header (จารจะได้ไม่ต้อง include เองในหน้าเนื้อหา)
    include "header.php";
}