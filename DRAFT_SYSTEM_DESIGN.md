# ระบบจัดการ Draft และ Version Control (Banquet System)

เอกสารฉบับนี้สรุปแนวทางการออกแบบระบบเพื่อให้รองรับการทำหลาย Draft ในงานเดียว และเลือกอนุมัติเพียงอันเดียว (Master-Draft Relationship)

## 1. การออกแบบโครงสร้างฐานข้อมูล (Database Schema)

### ก. ตารางใหม่: `event_projects` (โครงการงานจัดเลี้ยง)
ใช้สำหรับเก็บข้อมูลภาพรวมของงานที่ใช้ร่วมกันในทุก Draft
| Column | Type | Description |
| :--- | :--- | :--- |
| `id` | INT (PK) | ไอดีหลักของโครงการ |
| `project_name` | VARCHAR(255) | ชื่องานหลัก |
| `customer_id` | INT | ไอดีลูกค้า |
| `company_id` | INT | ไอดีบริษัทโรงแรม |
| `status` | ENUM | สถานะ (Pending, Approved, Completed, Cancelled) |
| `created_at` | TIMESTAMP | วันที่สร้าง |

### ข. ตารางเดิม: `functions` (ปรับปรุงเป็น Drafts)
เพิ่ม Column เพื่อเชื่อมโยงกับโครงการหลัก
| Column | Type | Description |
| :--- | :--- | :--- |
| `project_id` | INT (FK) | เชื่อมกับ `event_projects.id` |
| `version_no` | INT | ลำดับ Draft (1, 2, 3...) |
| `is_approved` | TINYINT(1) | สถานะการอนุมัติ (0=Draft, 1=Approved) |
| `draft_name` | VARCHAR(100) | ชื่อเรียก Draft (เช่น Option A, Option B) |

---

## 2. ขั้นตอนการทำงาน (Workflow)

### 1. การสร้างงานใหม่ (New Event)
- ระบบจะสร้าง `event_projects` 1 Record
- ระบบจะสร้าง `functions` (Draft V.1) และพ่วง `project_id` เข้ากับงานหลักที่สร้างขึ้น

### 2. การสร้าง Draft เพิ่ม (Duplicate Draft)
- ในหน้าจัดการงาน จะมีปุ่ม **"คัดลอกเป็น Draft ใหม่" (Copy to New Draft)**
- ระบบจะอ่านข้อมูลจาก Draft ปัจจุบัน แล้ว `INSERT` ลงตาราง `functions` เป็น Record ใหม่
- กำหนด `version_no` ให้รันต่อ และตั้งค่า `is_approved = 0`

### 3. การพิจารณาและอนุมัติ (Approval)
- เมื่อตัดสินใจเลือก Draft ที่ต้องการ:
    - ระบบจะ `UPDATE functions SET is_approved = 0` สำหรับทุก Draft ใน `project_id` นั้น
    - ระบบจะ `UPDATE functions SET is_approved = 1` สำหรับ Draft ที่ถูกเลือก
    - อัปเดตสถานะใน `event_projects` เป็น 'Approved'

---

## 3. การแสดงผลและส่วนติดต่อผู้ใช้ (UI/UX)

- **Event Dashboard:** แสดงรายการงานหลักจาก `event_projects`
- **Draft Switcher:** ในหน้าแก้ไขงาน จะมีแถบหรือ Dropdown ให้สลับดู Draft ต่างๆ ของงานนั้นๆ
- **Compare Mode:** (Option) แสดงตารางเปรียบเทียบราคาและเมนูระหว่าง Draft เพื่อช่วยในการตัดสินใจ

---

## 4. แผนการเริ่มดำเนินการ (Implementation Plan)

1. **Database Migration:** 
   - สร้างตาราง `event_projects`
   - เพิ่ม Column ในตาราง `functions` (`project_id`, `version_no`, `is_approved`, `draft_name`)
   - ย้ายข้อมูลเดิม (Data Migration) โดยสร้าง `project_id` ให้กับงานที่มีอยู่แล้ว
2. **Back-end Update:**
   - แก้ไข `api/save_function.php` และ `api/update_function.php`
   - สร้าง `api/duplicate_draft.php`
3. **Front-end Update:**
   - ปรับปรุงหน้า `manage_banquet.php` ให้แสดงเป็นรายโครงการ
   - เพิ่ม UI สำหรับจัดการ Draft ในหน้า `edit.php`
