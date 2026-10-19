<?php
/**
 * ประเภทรายการในใบเสนอราคา (dropdown ในตารางรายการ)
 *
 * เก็บลง quotation_items สามคอลัมน์:
 *   item_type        = Food / Break / Room / Meeting / Extra / Other
 *   menu_category_id = id ของเซตอาหารใน master_menu_categories (เฉพาะ Food ที่เลือกเซต)
 *   break_type_id    = id ของเบรกใน master_break_types (เฉพาะ Break)
 *
 * ค่าใน dropdown รวมไว้ในค่าเดียว: "Food" (ข้อมูลเก่า), "Food:3", "Break:7", "Room", "Extra", "Other"
 */

/** ประเภทที่ไม่ได้ดึงจาก DB */
const QUOTE_ITEM_OTHER_TYPES = [
    'Room'    => 'ห้องพักโรงแรม',
    'Meeting' => 'ห้องประชุม',
    'Extra'   => 'เพิ่มเติม',
    'Other' => 'อื่นๆ',
];

/**
 * ชื่อที่แสดงของ "Food" ที่ไม่มีเซต — มีแค่ในรายการเก่าที่บันทึกก่อนมี dropdown
 * แถวใหม่ไม่มีตัวเลือกนี้ ต้องเลือกประเภทเอง
 */
const QUOTE_ITEM_UNSPECIFIED = 'ไม่ระบุ';

/** ประเภทที่ไปลงตารางห้องพัก/ห้องประชุมของ EO (function_rooms.room_type) */
const EO_ROOM_TYPES = [
    'Room'    => 'ห้องพัก',
    'Meeting' => 'ห้องประชุม',
];

/** <option> ของ dropdown ประเภทในตารางห้องพัก/ห้องประชุมของ EO */
function eoRoomTypeOptions($selected = 'Room')
{
    $html = '';
    foreach (EO_ROOM_TYPES as $k => $label) {
        $html .= '<option value="' . $k . '"' . ($k === $selected ? ' selected' : '') . '>' . $label . '</option>';
    }
    return $html;
}

/** ตัวเลือกสำหรับแถวใหม่ตามลำดับที่แสดง: เซตอาหาร → เบรก (จาก DB) → ประเภทอื่น */
function quoteItemTypeOptions($conn)
{
    $options = [];
    $res = $conn->query("SELECT id, category_name FROM master_menu_categories ORDER BY sort_order ASC, id ASC");
    while ($res && $row = $res->fetch_assoc()) {
        $options['Food:' . $row['id']] = $row['category_name'];
    }
    $res = $conn->query("SELECT id, type_name FROM master_break_types ORDER BY id ASC");
    while ($res && $row = $res->fetch_assoc()) {
        $options['Break:' . $row['id']] = $row['type_name'];
    }
    return $options + QUOTE_ITEM_OTHER_TYPES;
}

/** ราคาที่ตั้งไว้ของเซต/เบรก คีย์เป็นค่าใน dropdown — ตัวที่ยังไม่ตั้งราคาจะไม่มีในนี้ */
function quoteSetPrices($conn)
{
    $prices = [];
    $res = $conn->query("SELECT id, set_price FROM master_menu_categories WHERE set_price > 0");
    while ($res && $row = $res->fetch_assoc()) {
        $prices['Food:' . $row['id']] = (float) $row['set_price'];
    }
    $res = $conn->query("SELECT id, break_price FROM master_break_types WHERE break_price > 0");
    while ($res && $row = $res->fetch_assoc()) {
        $prices['Break:' . $row['id']] = (float) $row['break_price'];
    }
    return $prices;
}

/**
 * แปลงค่าจาก dropdown เป็น [item_type, menu_category_id, break_type_id]
 * ค่าแปลก/ว่างถือเป็นอาหารไม่ระบุเซต
 */
function parseQuoteItemType($value)
{
    $value = (string) $value;
    if (array_key_exists($value, QUOTE_ITEM_OTHER_TYPES)) {
        return [$value, null, null];
    }
    if (preg_match('/^Food:(\d+)$/', $value, $m)) {
        return ['Food', (int) $m[1], null];
    }
    if (preg_match('/^Break:(\d+)$/', $value, $m)) {
        return ['Break', null, (int) $m[1]];
    }
    return ['Food', null, null];
}

/** แปลงแถวจาก DB กลับเป็นค่าของ dropdown */
function quoteItemTypeValue($item_type, $menu_category_id, $break_type_id = null)
{
    if (array_key_exists($item_type, QUOTE_ITEM_OTHER_TYPES)) {
        return $item_type;
    }
    if ($item_type === 'Break' && $break_type_id) {
        return 'Break:' . (int) $break_type_id;
    }
    return $menu_category_id ? 'Food:' . (int) $menu_category_id : 'Food';
}
