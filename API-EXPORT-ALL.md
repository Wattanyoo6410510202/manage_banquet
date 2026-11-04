# API ส่งออกข้อมูลทั้งหมด (ระบบ manage_banquet)

เอกสารนี้สำหรับทีมพัฒนาเว็บอื่นที่ต้องการดึง**ข้อมูลทั้งหมด**ของระบบนี้ (ทุกตารางในฐานข้อมูล) ไปใช้งาน

Endpoint นี้รันอยู่บนเซิร์ฟเวอร์ PHP ของระบบนี้เอง — เรียกจากฝั่ง server ของเว็บปลายทางเท่านั้น (ห้ามฝัง key ไว้ฝั่ง browser)

> ถ้าต้องการแค่ใบเสนอราคาพร้อมรายการ (จัดรูปแบบสำเร็จแล้ว) ใช้ `api/export_quotations.php` แทน — ดู `API-EXPORT-QUOTATIONS.md`

## Endpoint

```
GET/POST https://nas909ssf.myqnapcloud.com:8081/manage_banquet/api/export_all.php
```

## การยืนยันตัวตน (Auth)

ส่ง secret key มาด้วยวิธีใดวิธีหนึ่ง:

1. Header `X-API-Key: <key>` (แนะนำ), หรือ
2. พารามิเตอร์ `key` (query string หรือ POST body)

ค่า key กำหนดไว้ในไฟล์ `api/export_all.php` (ค่าคงที่ `EXPORT_ALL_KEY`) — แยกจาก key ของ `export_quotations.php`
ถ้า key ผิดหรือไม่ได้ส่งมา จะได้ `401 unauthorized`

## พารามิเตอร์ (ไม่บังคับ)

| พารามิเตอร์ | ตัวอย่าง | ความหมาย |
|---|---|---|
| `tables` | `tables=quotations,quotation_items,customers` | ดึงเฉพาะตารางที่ระบุ (คั่นด้วย `,`) ถ้าไม่ส่ง = ทุกตาราง |
| `list` | `list=1` | คืนเฉพาะรายชื่อตาราง + จำนวนแถว ไม่ส่งข้อมูล |

ชื่อตารางที่ไม่มีอยู่จริงจะได้ `400` พร้อมรายชื่อตารางที่ใช้ได้ (`available`)

## ตัวอย่างการเรียกใช้

### cURL
```bash
# ทุกตาราง
curl 'https://nas909ssf.myqnapcloud.com:8081/manage_banquet/api/export_all.php' \
  -H 'X-API-Key: <key>'

# เฉพาะบางตาราง
curl 'https://nas909ssf.myqnapcloud.com:8081/manage_banquet/api/export_all.php?tables=functions,quotations' \
  -H 'X-API-Key: <key>'
```

### PHP
```php
$ch = curl_init('https://nas909ssf.myqnapcloud.com:8081/manage_banquet/api/export_all.php');
curl_setopt_array($ch, [
    CURLOPT_HTTPHEADER => ['X-API-Key: <key>'],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 120,
]);
$result = json_decode(curl_exec($ch), true);
curl_close($ch);
$customers = $result['data']['customers'];
```

### JavaScript (Node.js / server-side)
```js
const res = await fetch('https://nas909ssf.myqnapcloud.com:8081/manage_banquet/api/export_all.php', {
  headers: { 'X-API-Key': process.env.BANQUET_EXPORT_KEY },
});
if (!res.ok) throw new Error('export failed: ' + res.status);
const { data } = await res.json();
```

## Response

`200 OK`

```json
{
  "status": "success",
  "exported_at": "2026-11-04T09:29:23+07:00",
  "tables": { "customers": 76, "quotations": 93, "...": 0 },
  "data": {
    "customers": [ { "id": 15, "cust_name": "BNI", "...": "..." } ],
    "quotations": [ { "id": 19, "quote_no": "QT-20260704-1518", "...": "..." } ]
  }
}
```

- `data.<ชื่อตาราง>` = array ของแถว**ทุกคอลัมน์**ตามโครงสร้างจริงในฐานข้อมูล (ชื่อคอลัมน์เหมือนใน DB)
- ตัวเลขคืนเป็น number, วันที่/เวลาเป็น string รูปแบบ MySQL (`YYYY-MM-DD` / `YYYY-MM-DD HH:MM:SS`, เวลาไทย)
- ตารางใหม่ที่เพิ่มในอนาคตจะถูกส่งออกอัตโนมัติ

## ตารางที่ส่งออก

| กลุ่ม | ตาราง |
|---|---|
| งานจัดเลี้ยง (Function) | `functions`, `function_schedules`, `function_menus`, `function_menu_details`, `function_breaks`, `function_kitchens`, `function_finance`, `function_rooms`, `function_status_log`, `function_types` |
| โปรเจกต์/ลูกค้า | `event_projects`, `customers`, `companies` |
| ใบเสนอราคา | `quotations`, `quotation_items` |
| ห้อง | `meeting_rooms`, `room_bookings` |
| ข้อมูลหลัก | `master_menu_types`, `master_menu_categories`, `master_break_types`, `master_checklist_bk`, `master_checklist_hk`, `master_checklist_mt` |
| ฝ่ายขาย | `sales_logs`, `sales_targets` |
| ผู้ใช้/ระบบ | `users` (**ไม่มีคอลัมน์ `password`**), `signatures`, `audit_logs` |

ความสัมพันธ์หลัก: `functions.project_id → event_projects.id`, `event_projects.customer_id → customers.id`, `quotations.customer_id → customers.id`, `quotation_items.quote_id → quotations.id`, `function_*.function_id → functions.id`

## ความปลอดภัย / ข้อควรระวัง

- มี**ข้อมูลส่วนบุคคลจริง** (ลูกค้า พนักงาน LINE user id, IP ใน audit log) — ใช้เท่าที่จำเป็น ห้ามเผยแพร่ต่อ
- Endpoint เป็น **read-only**
- หมุน key ได้โดยแก้ `EXPORT_ALL_KEY` ใน `api/export_all.php` แล้ว deploy — ต้องแจ้งเว็บปลายทางให้เปลี่ยนตาม
- ซ่อนคอลัมน์เพิ่มได้ที่ `EXPORT_HIDDEN_COLUMNS` ในไฟล์เดียวกัน
