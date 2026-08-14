<?php
/**
 * สถานะของรายการบัญชี (function_finance) มี 3 แบบ
 *
 *   quote — คีย์ตั้งแต่ยังเป็นใบเสนอราคา (ยังไม่มี EO)
 *   pre   — คีย์ตอนเป็น EO แล้วแต่งานยังไม่อนุมัติ
 *   post  — คีย์หลังงานอนุมัติแล้ว
 *
 * ไม่ต้องมีคอลัมน์เพิ่ม เพราะแยกได้จากข้อมูลที่มีอยู่:
 *   quotation_id ติดมา แต่ยังไม่มี function_id = ยังค้างอยู่ที่ใบเสนอราคา
 *   ที่เหลือดูจาก is_post_approval ตามเดิม
 *
 * พอใบเสนอราคาถูกแปลงเป็น EO รายการจะได้ function_id แล้วกลายเป็น pre ทันที
 * เพราะนับเป็นรายการของงานที่ยังไม่อนุมัติตามปกติ
 * (quotation_id ยังเก็บไว้เป็นร่องรอยว่ามาจากใบไหน แต่ไม่มีผลกับสถานะแล้ว)
 */

/** คืนรหัสสถานะ: 'quote' | 'pre' | 'post' */
function financeStage($f)
{
    if (!empty($f['quotation_id']) && empty($f['function_id'])) {
        return 'quote';
    }
    return !empty($f['is_post_approval']) ? 'post' : 'pre';
}

/** ป้ายกำกับภาษาไทย */
function financeStageLabel($f)
{
    switch (financeStage($f)) {
        case 'quote':
            return 'จากใบเสนอราคา';
        case 'post':
            return 'หลังอนุมัติ';
        default:
            return 'ก่อนอนุมัติ';
    }
}

/** class ของ Bootstrap badge สำหรับแต่ละสถานะ */
function financeStageBadgeClass($f)
{
    switch (financeStage($f)) {
        case 'quote':
            return 'bg-info text-dark';
        case 'post':
            return 'bg-warning text-dark';
        default:
            return 'bg-secondary text-white';
    }
}

/** สีพื้นหลังสำหรับหน้าพิมพ์/Excel (inline style) */
function financeStageBg($f)
{
    switch (financeStage($f)) {
        case 'quote':
            return '#d1ecf1';
        case 'post':
            return '#fff3cd';
        default:
            return '#e9ecef';
    }
}
