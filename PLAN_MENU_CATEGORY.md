# แผนงาน: เพิ่ม "ประเภทใหญ่" สำหรับเมนูอาหาร

## วัตถุประสงค์
เพิ่มระดับ "ประเภทใหญ่" (Super Category) เหนือ `master_menu_types` เพื่อจัดกลุ่มเมนูอาหารให้เป็นระบบ เช่น
- อาหารไทย → เซตเมนูอาหารไทย, บุฟเฟต์ไทย, ข้าวกล่อง
- อาหารจีน → โต๊ะจีน
- อาหารนานาชาติ → บุฟเฟต์นานาชาติ
- ของว่าง/ค็อกเทล → ค็อกเทล, เบรก

---

## 1. Database Changes

### 1.1 สร้างตารางใหม่: `master_menu_categories`

```sql
CREATE TABLE master_menu_categories (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    category_name VARCHAR(150) NOT NULL,
    sort_order INT(11) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### 1.2 เพิ่มคอลัมน์ `category_id` ใน `master_menu_types`

```sql
ALTER TABLE master_menu_types
    ADD COLUMN category_id INT(11) DEFAULT NULL AFTER id,
    ADD FOREIGN KEY (category_id) REFERENCES master_menu_categories(id) ON DELETE SET NULL;
```

### 1.3 Seed Data

```sql
INSERT INTO master_menu_categories (id, category_name, sort_order) VALUES
(1, 'อาหารไทย', 1),
(2, 'อาหารจีน', 2),
(3, 'อาหารนานาชาติ', 3),
(4, 'ของว่าง / ค็อกเทล', 4);

-- อัพเดต master_menu_types ให้เชื่อมกับ category
UPDATE master_menu_types SET category_id = 1 WHERE id IN (1, 6);    -- บุฟเฟต์ไทย, ข้าวกล่อง
UPDATE master_menu_types SET category_id = 2 WHERE id = 3;          -- โต๊ะจีน
UPDATE master_menu_types SET category_id = 3 WHERE id = 2;          -- บุฟเฟต์นานาชาติ
UPDATE master_menu_types SET category_id = 4 WHERE id IN (4, 7);    -- ค็อกเทล, เบรก
UPDATE master_menu_types SET category_id = 1 WHERE id = 5;          -- เซตเมนู
```

---

## 2. ไฟล์ที่ต้องแก้ (ทั้งหมด 10 ไฟล์)

### 2.1 `setting_master.php` — CRUD จัดการ Master Data

**สิ่งที่แก้:**
- เพิ่มส่วนจัดการ `master_menu_categories` (ตาราง + เพิ่ม/แก้ไข/ลบ)
- เพิ่มคอลัมน์ "ประเภทใหญ่" ในตารางแสดง `master_menu_types`
- เพิ่ม dropdown `category_id` ในฟอร์มเพิ่ม/แก้ไข menu type

**Estimated changes: ~50 บรรทัด**

---

### 2.2 `food_management.php` — จัดการแคตตาล็อกเมนู

**สิ่งที่แก้:**
- เพิ่ม dropdown filter "ประเภทใหญ่" ด้านบนตาราง
  - เลือก category → แสดงเฉพาะ menu types ในกลุ่มนั้น
- เพิ่ม dropdown `category_id` ในฟอร์ม (optional: filter menu type dropdown ตาม category)
- JOIN `master_menu_types` กับ `master_menu_categories` เพื่อแสดงชื่อ category

**Estimated changes: ~30 บรรทัด**

---

### 2.3 `add_event.php` — ฟอร์มสร้าง EO

**สิ่งที่แก้:**
- เพิ่ม dropdown "ประเภทใหญ่" ก่อน dropdown ประเภทเมนู
  - เมื่อเลือก category → filter ประเภทเมนูให้เหลือเฉพาะในกลุ่มนั้น (AJAX หรือ JS)
- แก้ dropdown `menu_set_id` ให้แบ่งกลุ่มตาม category (optgroup)
- JOIN query เพิ่ม `master_menu_categories`

**Estimated changes: ~40 บรรทัด**

---

### 2.4 `edit.php` — ฟอร์มแก้ไข EO

**สิ่งที่แก้:**
- เหมือน `add_event.php`
- เพิ่ม dropdown ประเภทใหญ่ + filter ประเภทเมนู
- แสดงค่า category ที่เลือกอยู่แล้ว (pre-select จากข้อมูลเดิม)

**Estimated changes: ~40 บรรทัด**

---

### 2.5 `view.php` — หน้าดู/พิมพ์ EO

**สิ่งที่แก้:**
- JOIN `master_menu_categories` เพื่อแสดงชื่อ "ประเภทใหญ่" ร่วมกับ "ประเภทย่อย"
- เพิ่มคอลัมน์หรือ label ในส่วนเมนูอาหาร

**Estimated changes: ~10 บรรทัด**

---

### 2.6 `add_quote.php` — ฟอร์มสร้างใบเสนอราคา

**สิ่งที่แก้:**
- เพิ่ม filter/category selector สำหรับเมนู template
- แสดง `category_name` ใน dropdown menu templates

**Estimated changes: ~20 บรรทัด**

---

### 2.7 `edit_quotation.php` — ฟอร์มแก้ไขใบเสนอราคา

**สิ่งที่แก้:**
- เหมือน `add_quote.php`

**Estimated changes: ~20 บรรทัด**

---

### 2.8 `public_calendar.php` — ปฏิทินสาธารณะ

**สิ่งที่แก้:**
- AJAX endpoint: JOIN เพิ่ม `master_menu_categories` ใน query menus
- `buildEoHtml()`: แสดง "ประเภทใหญ่" ในส่วนเมนูอาหาร

**Estimated changes: ~10 บรรทัด**

---

### 2.9 `api/get_menu_detail_ajax.php` — AJAX ดึงเมนูตามประเภท

**สิ่งที่แก้:**
- เพิ่ม parameter `category_id` (optional) เพื่อ filter menu types ในกลุ่ม
- หรือ JOIN เพิ่มเพื่อส่ง `category_name` กลับมาด้วย

**Estimated changes: ~10 บรรทัด**

---

### 2.10 `main_kitchen.php` — จัดการเบรก (optional)

**สิ่งที่แก้:**
- ไม่จำเป็นต้องแก้ (เบรกใช้ `master_break_types` แยกต่างหาก)
- แต่ถ้าต้องการ grouping เบรกด้วย ค่อยเพิ่มทีหลัง

**Estimated changes: 0 บรรทัด (Phase 2)**

---

## 3. Flow การทำงาน (User Journey)

### ขั้นตอนการสร้าง/แก้ไข EO

```
1. ผู้ใช้เปิดฟอร์ม EO (add_event / edit)
                    ↓
2. เลือก "ประเภทใหญ่" เช่น "อาหารไทย"
                    ↓
3. Dropdown "ประเภทเมนู" แสดงเฉพาะกลุ่มอาหารไทย
   (เซตเมนูอาหารไทย, บุฟเฟต์ไทย, ข้าวกล่อง)
                    ↓
4. เลือก "เซตเมนูอาหารไทย"
                    ↓
5. AJAX ดึงเมนูจาก function_menu_details ที่ตรงกับ menu_type_id
                    ↓
6. บันทึก → function_menus เก็บ menu_set_id เดิม (ไม่ต้องแก้)
```

### Flow การแสดงผล

```
view.php / public_calendar.php
    ↓
JOIN function_menus → master_menu_types → master_menu_categories
    ↓
แสดง: "อาหารไทย > เซตเมนูอาหารไทย"
```

---

## 4. Seed Data (ประเภทใหญ่ตัวอย่าง)

| id | category_name | sort_order | ประเภทย่อยที่เชื่อม |
|----|---------------|------------|---------------------|
| 1 | อาหารไทย | 1 | บุฟเฟต์ไทย, เซตเมนู, ข้าวกล่อง |
| 2 | อาหารจีน | 2 | โต๊ะจีน |
| 3 | อาหารนานาชาติ | 3 | บุฟเฟต์นานาชาติ |
| 4 | ของว่าง / ค็อกเทล | 4 | ค็อกเทล, เบรก |

---

## 5. แผนงาน (Phased Approach)

### Phase 1: DB + Admin (วันที่ 1)
- [ ] สร้างตาราง `master_menu_categories`
- [ ] เพิ่มคอลัมน์ `category_id` ใน `master_menu_types`
- [ ] เพิ่ม seed data
- [ ] แก้ `setting_master.php` — CRUD ประเภทใหญ่

### Phase 2: Food Management (วันที่ 1)
- [ ] แก้ `food_management.php` — เพิ่ม filter + dropdown category

### Phase 3: EO Forms (วันที่ 2)
- [ ] แก้ `add_event.php` — เพิ่ม category dropdown + filter
- [ ] แก้ `edit.php` — เหมือน add_event

### Phase 4: View + Calendar (วันที่ 2)
- [ ] แก้ `view.php` — แสดง category
- [ ] แก้ `public_calendar.php` — AJAX + display

### Phase 5: Quotation (วันที่ 3)
- [ ] แก้ `add_quote.php` — เพิ่ม category filter
- [ ] แก้ `edit_quotation.php` — เหมือน add_quote

### Phase 6: AJAX + Polish (วันที่ 3)
- [ ] แก้ `api/get_menu_detail_ajax.php` — เพิ่ม filter
- [ ] ทดสอบทั้งระบบ
- [ ] แก้ bug / polish

---

## 6. ผลกระทบที่ต้องระวัง

| ความเสี่ยง | วิธีจัดการ |
|-----------|-----------|
| ข้อมูลเดิมไม่มี `category_id` | ใช้ `DEFAULT NULL` + SET NULL = ไม่เสียข้อมูล |
| `function_menus.menu_set_id` ไม่ต้องแก้ | เก็บค่าเดิม เพิ่มแค่ display layer |
| AJAX dropdown cascade ต้องลื่น | ใช้ JS filter หรือ AJAX แยก request |
| Seed data ผิด/ซ้ำ | ใช้ `INSERT IGNORE` + `ON DUPLICATE KEY` |

---

## 7. สรุปงานทั้งหมด

| รายการ | จำนวนไฟล์ | ประมาณการ |
|--------|-----------|-----------|
| Database changes | 1 | 15 นาที |
| setting_master.php | 1 | 30 นาที |
| food_management.php | 1 | 20 นาที |
| add_event.php + edit.php | 2 | 40 นาที |
| view.php | 1 | 10 นาที |
| public_calendar.php | 1 | 10 นาที |
| add_quote.php + edit_quotation.php | 2 | 30 นาที |
| api/get_menu_detail_ajax.php | 1 | 10 นาที |
| ทดสอบ + bug fix | - | 30 นาที |
| **รวม** | **11 ไฟล์** | **~3 ชั่วโมง** |
