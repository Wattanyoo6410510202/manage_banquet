<?php
/**
 * Menu Type Modal Component
 * Include this file in any page that needs the menu type picker modal.
 * 
 * Modes:
 * - "type": 2-level picker (Category → Type). Sets hidden input value + calls fetchMenuDetailById.
 *   Used in: add_event.php, edit.php, food_management.php
 * - "template-menu": 3-level picker with sub-modes:
 *   - "items": Category → Type → Menu Detail (from function_menu_details via AJAX)
 *   - "set": Category → Type → Set price (default 3000)
 *   Used in: add_quote.php, edit_quotation.php
 * - "template-break": 2-level picker (Break Type → Break Detail via AJAX).
 *   Used in: add_quote.php, edit_quotation.php
 *
 * Usage:
 * Type mode: openMenuTypeModal(triggerEl, callbackName)
 * Template mode: openTemplateModal(mode, callbackName, subMode?)
 *   - mode: 'template-menu' or 'template-break'
 *   - subMode: 'items' (default) or 'set' — only for template-menu
 *   - callbackName: JS function name receiving {id, name, price, cost, description}
 */

if (empty($menu_types_array)) {
    $menu_types_array = [];
}
$modal_json = json_encode($menu_types_array, JSON_UNESCAPED_UNICODE);

if (empty($break_types_array)) {
    $break_types_array = [];
}
$break_modal_json = json_encode($break_types_array, JSON_UNESCAPED_UNICODE);
?>

<style>
.menu-type-modal-overlay {
    display: none;
    position: fixed;
    top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(0,0,0,0.5);
    z-index: 1060;
    backdrop-filter: blur(3px);
    animation: fadeIn 0.2s ease;
}
.menu-type-modal-overlay.active { display: flex; align-items: center; justify-content: center; }

.menu-type-modal {
    background: #fff;
    border-radius: 16px;
    width: 90%;
    max-width: 750px;
    max-height: 85vh;
    display: flex;
    flex-direction: column;
    box-shadow: 0 20px 60px rgba(0,0,0,0.3);
    animation: slideUp 0.25s ease;
}

.menu-type-modal-header {
    padding: 16px 20px;
    border-bottom: 1px solid #eee;
    display: flex;
    align-items: center;
    gap: 12px;
}

.menu-type-modal-header input {
    flex: 1;
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 8px 12px;
    font-size: 0.9rem;
    outline: none;
}
.menu-type-modal-header input:focus { border-color: #0d6efd; }

.menu-type-modal-tabs {
    padding: 0 20px;
    border-bottom: 1px solid #eee;
    display: none;
}
.menu-type-modal-tabs.visible { display: flex; gap: 0; }
.menu-type-modal-tabs .tab-btn {
    flex: 1;
    padding: 10px 12px;
    font-size: 0.85rem;
    font-weight: 600;
    color: #6c757d;
    background: transparent;
    border: none;
    border-bottom: 3px solid transparent;
    cursor: pointer;
    transition: all 0.15s;
    text-align: center;
}
.menu-type-modal-tabs .tab-btn:hover { color: #333; background: #f8f9fa; }
.menu-type-modal-tabs .tab-btn.active {
    color: #0d6efd;
    border-bottom-color: #0d6efd;
}

.menu-type-modal-breadcrumb {
    padding: 8px 20px;
    background: #f8f9fa;
    border-bottom: 1px solid #eee;
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 0.8rem;
}
.menu-type-modal-breadcrumb .crumb {
    color: #6c757d;
    cursor: pointer;
    transition: color 0.15s;
}
.menu-type-modal-breadcrumb .crumb:hover { color: #0d6efd; }
.menu-type-modal-breadcrumb .crumb.active { color: #333; font-weight: 600; cursor: default; }
.menu-type-modal-breadcrumb .sep { color: #ccc; }

.menu-type-modal-body {
    flex: 1;
    overflow-y: auto;
    padding: 12px 20px;
}

.menu-type-modal-body .cat-section { margin-bottom: 16px; }
.menu-type-modal-body .cat-title {
    font-size: 0.75rem;
    font-weight: 700;
    color: #6c757d;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    padding: 4px 0 8px 0;
    border-bottom: 1px solid #eee;
    margin-bottom: 8px;
}

.menu-type-modal-body .card-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
    gap: 8px;
}

.menu-type-modal-body .menu-card {
    border: 1px solid #e0e0e0;
    border-radius: 10px;
    padding: 10px 12px;
    cursor: pointer;
    transition: all 0.15s;
    font-size: 0.85rem;
    font-weight: 500;
    color: #333;
    text-align: center;
}
.menu-type-modal-body .menu-card:hover {
    border-color: #0d6efd;
    background: #f0f7ff;
    transform: translateY(-1px);
    box-shadow: 0 2px 8px rgba(13,110,253,0.15);
}
.menu-type-modal-body .menu-card.selected {
    border-color: #198754;
    background: #d1e7dd;
    color: #0f5132;
}

.menu-type-modal-body .template-card {
    border: 1px solid #e0e0e0;
    border-radius: 10px;
    padding: 12px 14px;
    cursor: pointer;
    transition: all 0.15s;
    text-align: left;
}
.menu-type-modal-body .template-card:hover {
    border-color: #0d6efd;
    background: #f0f7ff;
    transform: translateY(-1px);
    box-shadow: 0 2px 8px rgba(13,110,253,0.15);
}
.menu-type-modal-body .template-card.selected {
    border-color: #198754;
    background: #d1e7dd;
}
.menu-type-modal-body .template-card .tpl-name {
    font-size: 0.85rem;
    font-weight: 600;
    color: #333;
    margin-bottom: 2px;
}
.menu-type-modal-body .template-card .tpl-detail {
    font-size: 0.75rem;
    color: #666;
    max-height: 2.4em;
    overflow: hidden;
    text-overflow: ellipsis;
}
.menu-type-modal-body .template-card .tpl-price {
    font-size: 0.8rem;
    font-weight: 600;
    color: #198754;
    margin-top: 4px;
}
.menu-type-modal-body .template-card.selected .tpl-name { color: #0f5132; }
.menu-type-modal-body .template-card.selected .tpl-price { color: #0f5132; }

.menu-type-modal-body .set-price-panel {
    max-width: 400px;
    margin: 20px auto;
    text-align: center;
}
.menu-type-modal-body .set-price-panel .set-label {
    font-size: 0.9rem;
    font-weight: 600;
    color: #333;
    margin-bottom: 6px;
}
.menu-type-modal-body .set-price-panel .set-sublabel {
    font-size: 0.8rem;
    color: #6c757d;
    margin-bottom: 12px;
}
.menu-type-modal-body .set-price-panel .set-type-badge {
    display: inline-block;
    padding: 6px 16px;
    background: #d1e7dd;
    color: #0f5132;
    border-radius: 8px;
    font-size: 0.9rem;
    font-weight: 600;
    margin-bottom: 16px;
}
.menu-type-modal-body .set-price-panel input {
    width: 100%;
    border: 2px solid #e0e0e0;
    border-radius: 10px;
    padding: 12px 16px;
    font-size: 1.5rem;
    font-weight: 700;
    text-align: center;
    color: #333;
    outline: none;
    transition: border-color 0.15s;
    -moz-appearance: textfield;
}
.menu-type-modal-body .set-price-panel input::-webkit-outer-spin-button,
.menu-type-modal-body .set-price-panel input::-webkit-inner-spin-button {
    -webkit-appearance: none;
    margin: 0;
}
.menu-type-modal-body .set-price-panel input:focus { border-color: #0d6efd; }
.menu-type-modal-body .set-price-panel .set-hint {
    font-size: 0.75rem;
    color: #999;
    margin-top: 8px;
}

.menu-type-modal-body .no-results {
    text-align: center;
    padding: 30px;
    color: #999;
    font-size: 0.9rem;
}

.menu-type-modal-body .loading-spinner {
    text-align: center;
    padding: 30px;
    color: #999;
}
.menu-type-modal-body .loading-spinner .spinner-border { width: 2rem; height: 2rem; }

.menu-type-modal-footer {
    padding: 12px 20px;
    border-top: 1px solid #eee;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.menu-type-modal-footer .selected-info {
    font-size: 0.85rem;
    color: #333;
    font-weight: 500;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    max-width: 60%;
}

.btn-menu-type-picker {
    border: 1px dashed #aaa;
    background: #fafafa;
    border-radius: 8px;
    padding: 4px 10px;
    font-size: 0.8rem;
    color: #555;
    cursor: pointer;
    transition: all 0.15s;
    white-space: nowrap;
}
.btn-menu-type-picker:hover {
    border-color: #0d6efd;
    color: #0d6efd;
    background: #f0f7ff;
}
.menu-type-label {
    display: inline-block;
    max-width: 140px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    vertical-align: middle;
    margin-left: 4px;
}

@keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
@keyframes slideUp { from { opacity: 0; transform: translateY(30px); } to { opacity: 1; transform: translateY(0); } }

@media (max-width: 600px) {
    .menu-type-modal { width: 95%; max-height: 85vh; }
    .menu-type-modal-body .card-grid { grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 6px; }
    .menu-type-modal-body .menu-card { font-size: 0.8rem; padding: 8px; }
}
</style>

<!-- Modal HTML -->
<div class="menu-type-modal-overlay" id="menuTypeModalOverlay">
    <div class="menu-type-modal">
        <div class="menu-type-modal-header">
            <i class="bi bi-search"></i>
            <input type="text" id="menuTypeSearchInput" placeholder="พิมพ์เพื่อค้นหา..." oninput="filterMenuTypeModal(this.value)">
            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="closeMenuTypeModal()">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
        <div class="menu-type-modal-tabs" id="menuTypeTabs">
           
            <button type="button" class="tab-btn active" data-submode="set" onclick="_mtmSwitchSubMode('set')">
                <i class="bi bi-grid-3x3-gap me-1"></i>เลือกแบบเซต
            </button>
             <button type="button" class="tab-btn " data-submode="items" onclick="_mtmSwitchSubMode('items')">
                <i class="bi bi-list-ul me-1"></i>เลือกรายการ
            </button>
        </div>
        <div class="menu-type-modal-breadcrumb" id="menuTypeBreadcrumb" style="display:none;"></div>
        <div class="menu-type-modal-body" id="menuTypeModalBody"></div>
        <div class="menu-type-modal-footer" id="menuTypeFooter" style="display:none;">
            <div class="selected-info" id="menuTypeSelectedInfo"></div>
            <div style="display:flex;align-items:center;gap:8px;">
                <span id="menuTypeQtyWrap" style="display:none;white-space:nowrap;">
                    <label class="me-1" style="font-size:0.8rem;font-weight:600;color:#555;">จำนวน</label>
                    <input type="number" id="mtmFooterQty" value="1" min="1" style="width:60px;border:1px solid #ccc;border-radius:6px;padding:4px 6px;font-size:0.85rem;text-align:center;font-weight:600;" onfocus="this.select()">
                </span>
                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="closeMenuTypeModal()">ยกเลิก</button>
                <button type="button" class="btn btn-sm btn-primary fw-bold" id="menuTypeConfirmBtn" onclick="confirmTemplateSelection()" disabled>
                    <i class="bi bi-check-lg me-1"></i><span id="menuTypeConfirmLabel">เลือก</span>
                </button>
            </div>
        </div>
    </div>
</div>

<script>
const _menuTypesData = <?= $modal_json ?>;
const _breakTypesData = <?= $break_modal_json ?>;

let _mtmTarget = null;
let _mtmCallback = null;
let _mtmMode = 'type';
let _mtmSubMode = 'set';
let _mtmLevel = 0;
let _mtmSelectedCategory = null;
let _mtmSelectedType = null;
let _mtmSelectedTypeId = null;
let _mtmSelectedTypeIds = [];
let _mtmSelectedTemplateIds = {};
let _mtmSelectedTemplate = null;
let _mtmTypeCache = {};
let _mtmSetPrice = 3000;
let _mtmSetItems = [];

function _mtmEscapeHtml(text) {
    const d = document.createElement('div');
    d.appendChild(document.createTextNode(text));
    return d.innerHTML;
}

function _mtmEscapeAttr(text) {
    return text.replace(/&/g,'&amp;').replace(/'/g,'&#39;').replace(/"/g,'&quot;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

function _mtmReset() {
    _mtmLevel = 0;
    _mtmSelectedCategory = null;
    _mtmSelectedType = null;
    _mtmSelectedTypeId = null;
    _mtmSelectedTemplate = null;
    _mtmSetPrice = 3000;
    _mtmSetItems = [];
    _mtmSelectedTypeIds = [];
    _mtmSelectedTemplateIds = {};
}

function _mtmRenderBreadcrumb() {
    const bc = document.getElementById('menuTypeBreadcrumb');
    if (_mtmMode === 'type') { bc.style.display = 'none'; return; }

    bc.style.display = 'flex';
    let html = '';

    if (_mtmMode === 'template-menu') {
        html += `<span class="crumb ${_mtmLevel === 0 ? 'active' : ''}" onclick="if(${_mtmLevel}>0)_mtmGoLevel(0)">หมวดใหญ่</span>`;
        if (_mtmLevel >= 1) {
            html += `<span class="sep"><i class="bi bi-chevron-right"></i></span>`;
            html += `<span class="crumb ${_mtmLevel === 1 ? 'active' : ''}" onclick="if(${_mtmLevel}>1)_mtmGoLevel(1)">${_mtmEscapeHtml(_mtmSelectedCategory || '')}</span>`;
        }
        if (_mtmLevel >= 2) {
            html += `<span class="sep"><i class="bi bi-chevron-right"></i></span>`;
            html += `<span class="crumb ${_mtmLevel === 2 ? 'active' : (_mtmLevel === 3 ? '' : 'active')}" onclick="if(${_mtmLevel}>2)_mtmGoLevel(2)">${_mtmEscapeHtml(_mtmSelectedType || '')}</span>`;
        }
        if (_mtmLevel >= 3 && _mtmSubMode === 'set') {
            const shortMenu = _mtmSelectedTemplate ? ((_mtmSelectedTemplate.menu_items || '').substring(0, 30) + ((_mtmSelectedTemplate.menu_items || '').length > 30 ? '...' : '')) : '';
            html += `<span class="sep"><i class="bi bi-chevron-right"></i></span>`;
            html += `<span class="crumb active">ใส่ราคา</span>`;
        }
    } else if (_mtmMode === 'template-break') {
        html += `<span class="crumb ${_mtmLevel === 0 ? 'active' : ''}" onclick="if(${_mtmLevel}>0)_mtmGoLevel(0)">ประเภทเบรก</span>`;
        if (_mtmLevel >= 1) {
            html += `<span class="sep"><i class="bi bi-chevron-right"></i></span>`;
            html += `<span class="crumb active">${_mtmEscapeHtml(_mtmSelectedType || '')}</span>`;
        }
    }

    bc.innerHTML = html;
}

function _mtmSwitchSubMode(subMode) {
    _mtmSubMode = subMode;
    _mtmReset();

    document.querySelectorAll('#menuTypeTabs .tab-btn').forEach(btn => {
        btn.classList.toggle('active', btn.dataset.submode === subMode);
    });

    _mtmRenderBreadcrumb();
    _mtmUpdateFooter();
    renderMenuTypeModalCards('');
}

function _mtmGoLevel(level) {
    _mtmLevel = level;
    _mtmSelectedTemplate = null;
    _mtmUpdateFooter();
    _mtmRenderBreadcrumb();
    document.getElementById('menuTypeSearchInput').value = '';

    if (_mtmMode === 'template-menu') {
        if (level === 0) renderMenuTypeModalCards('');
        else if (level === 1) _mtmRenderTypesForCategory(_mtmSelectedCategory, '');
        else if (level === 2) _mtmLoadTemplates(_mtmSelectedTypeId);
    } else if (_mtmMode === 'template-break') {
        if (level === 0) _mtmRenderBreakTypes('');
    }
}

function renderMenuTypeModalCards(filter) {
    const body = document.getElementById('menuTypeModalBody');
    filter = (filter || '').toLowerCase();

    const groups = {};
    _menuTypesData.forEach(mt => {
        const catName = mt.category_name || 'อื่นๆ';
        if (!groups[catName]) groups[catName] = [];
        if (!filter || mt.type_name.toLowerCase().includes(filter) || catName.toLowerCase().includes(filter)) {
            groups[catName].push(mt);
        }
    });

    let html = '';
    let hasResults = false;

    if (_mtmMode === 'type') {
    Object.keys(groups).sort().forEach(catName => {
        const items = groups[catName];
        if (items.length === 0) return;
        hasResults = true;

            html += `<div class="cat-section"><div class="cat-title">${_mtmEscapeHtml(catName)}</div><div class="card-grid">`;
            items.forEach(mt => {
                const currentVal = _mtmTarget ? _mtmTarget.value : '';
                const sel = (mt.id == currentVal) ? ' selected' : '';
                html += `<div class="menu-card${sel}" data-id="${mt.id}" onclick="selectMenuTypeFromModal(${mt.id},'${_mtmEscapeAttr(mt.type_name)}')">${_mtmEscapeHtml(mt.type_name)}</div>`;
            });
            html += `</div></div>`;
    });
    } else {
        html += '<div class="card-grid">';
        Object.keys(groups).sort().forEach(catName => {
            const items = groups[catName];
            if (items.length === 0) return;
            hasResults = true;
            const hasSelected = items.some(mt => _mtmSelectedTypeIds.includes(Number(mt.id)));
            const catSel = _mtmSelectedCategory === catName || hasSelected ? ' selected' : '';
            html += `<div class="menu-card${catSel}" onclick="_mtmSelectCategory('${_mtmEscapeAttr(catName)}')">${_mtmEscapeHtml(catName)} <small class="text-muted d-block">${items.length} ประเภท</small></div>`;
        });
        html += '</div>';
    }

    if (!hasResults) {
        html = '<div class="no-results"><i class="bi bi-search" style="font-size:2rem;display:block;margin-bottom:8px;"></i>ไม่พบเมนูที่ค้นหา</div>';
    }

    body.innerHTML = html;
}

function _mtmSelectCategory(catName) {
    _mtmSelectedCategory = catName;
    _mtmLevel = 1;
    _mtmSelectedTemplate = null;
    _mtmUpdateFooter();
    _mtmRenderBreadcrumb();
    document.getElementById('menuTypeSearchInput').value = '';

    _mtmRenderTypesForCategory(catName, '');
}

function _mtmRenderTypesForCategory(catName, filter) {
    const body = document.getElementById('menuTypeModalBody');
    filter = (filter || '').toLowerCase();

    const types = _menuTypesData.filter(mt => {
        const matchCat = (mt.category_name || 'อื่นๆ') === catName;
        if (!matchCat) return false;
        if (filter && !mt.type_name.toLowerCase().includes(filter)) return false;
        return true;
    });

    let html = '';
    if (types.length === 0) {
        html = '<div class="no-results">ไม่พบประเภทเมนูในหมวดนี้</div>';
    } else {
        html += '<div class="card-grid">';
        types.forEach(mt => {
            const sel = (_mtmSelectedTypeIds.includes(Number(mt.id))) ? ' selected' : '';
            const cnt = (_mtmSelectedTemplateIds[mt.id] || []).length;
            html += `<div class="menu-card${sel}" onclick="_mtmSelectType('${_mtmEscapeAttr(mt.type_name)}',${mt.id})">${_mtmEscapeHtml(mt.type_name)}${cnt ? `<small class="text-muted d-block">${cnt} รายการ</small>` : ''}</div>`;
        });
        html += '</div>';
    }

    body.innerHTML = html;
}

function _mtmSelectType(typeName, typeId) {
    _mtmSelectedType = typeName;
    _mtmSelectedTypeId = typeId;
    _mtmLevel = 2;
    _mtmSelectedTemplate = null;
    _mtmUpdateFooter();
    _mtmRenderBreadcrumb();
    _mtmLoadTemplates(typeId);
}

function _mtmGoSetPrice() {
    _mtmLevel = 3;
    _mtmUpdateFooter();
    _mtmRenderBreadcrumb();
    _mtmRenderSetPricePanel();
}

function _mtmRenderSetPricePanel() {
    const body = document.getElementById('menuTypeModalBody');
    const menuName = _mtmSelectedTemplate ? (_mtmSelectedTemplate.menu_items || '') : '';
    const shortMenu = menuName.length > 80 ? menuName.substring(0, 80) + '...' : menuName;

    let listHtml = '';
    if (_mtmSetItems.length > 0) {
        listHtml = '<div style="margin-top:20px;text-align:left;border-top:2px dashed #dee2e6;padding-top:14px;">';
        listHtml += '<div style="font-size:0.8rem;font-weight:700;color:#6c757d;margin-bottom:8px;"><i class="bi bi-list-check me-1"></i>รายการที่เลือกแล้ว (' + _mtmSetItems.length + ')</div>';
        _mtmSetItems.forEach((item, idx) => {
            listHtml += '<div style="display:flex;align-items:center;justify-content:space-between;padding:8px 10px;background:#f8f9fa;border-radius:8px;margin-bottom:4px;font-size:0.82rem;">';
            listHtml += '<span style="flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">' + _mtmEscapeHtml(item.name.substring(0, 50)) + (item.name.length > 50 ? '...' : '') + '</span>';
            listHtml += ' <span class="text-muted ms-1" style="white-space:nowrap;">x' + item.qty + '</span>';
            listHtml += '<span style="white-space:nowrap;margin-left:8px;color:#198754;font-weight:600;">' + item.price.toLocaleString('th-TH', {minimumFractionDigits:0}) + '</span>';
            listHtml += '<button type="button" class="btn btn-sm btn-outline-danger ms-2" style="padding:2px 6px;font-size:0.7rem;" onclick="_mtmRemoveSetItem(' + idx + ')"><i class="bi bi-x"></i></button>';
            listHtml += '</div>';
        });
        listHtml += '</div>';
    }

    body.innerHTML = `
        <div class="set-price-panel">
            <div class="set-label">กำหนดราคาสำหรับรายการนี้</div>
            <div class="set-sublabel">${_mtmEscapeHtml(_mtmSelectedCategory || '')} › ${_mtmEscapeHtml(_mtmSelectedType || '')}</div>
            ${shortMenu ? '<div class="set-type-badge" style="max-width:100%;white-space:normal;text-align:left;font-size:0.8rem;">' + _mtmEscapeHtml(shortMenu) + '</div>' : ''}
            <div style="display:flex;gap:12px;max-width:300px;margin:0 auto;">
                <div style="flex:1;text-align:left;">
                    <label style="font-size:0.8rem;font-weight:600;color:#555;">จำนวน</label>
                    <input type="number" id="mtmSetQtyInput" value="1" min="1" style="width:100%;border:2px solid #e0e0e0;border-radius:10px;padding:10px 12px;font-size:1.2rem;font-weight:700;text-align:center;color:#333;outline:none;-moz-appearance:textfield;" onfocus="this.select()">
                </div>
                <div style="flex:2;text-align:left;">
                    <label style="font-size:0.8rem;font-weight:600;color:#555;">ราคา/หน่วย</label>
                    <input type="number" id="mtmSetPriceInput" value="${_mtmSetPrice}" min="0" step="100" style="width:100%;border:2px solid #e0e0e0;border-radius:10px;padding:10px 12px;font-size:1.2rem;font-weight:700;text-align:center;color:#333;outline:none;-moz-appearance:textfield;" oninput="_mtmSetPrice=parseFloat(this.value)||0;" onfocus="this.select()">
                </div>
            </div>
            <div class="set-hint">ใส่จำนวนและราคาต่อหน่วย</div>
            <button type="button" class="btn btn-success fw-bold mt-3" onclick="_mtmAddToSetList()" style="min-width:160px;">
                <i class="bi bi-plus-circle me-1"></i>เพิ่มลงรายการ
            </button>
        </div>
        ${listHtml}
    `;
    setTimeout(() => {
        const inp = document.getElementById('mtmSetQtyInput');
        if (inp) { inp.focus(); inp.select(); }
    }, 100);
}

function _mtmAddToSetList() {
    if (!_mtmSelectedTemplate) return;
    const price = parseFloat(_mtmSetPrice) || 0;
    const qty = parseInt(document.getElementById('mtmSetQtyInput').value) || 1;
    if (price <= 0) { alert('กรุณาระบุราคามากกว่า 0'); return; }

    _mtmSetItems.push({
        id: _mtmSelectedTemplate.id,
        type_id: _mtmSelectedTypeId,
        type_name: _mtmSelectedType,
        name: _mtmSelectedTemplate.menu_items || _mtmSelectedType,
        qty: qty,
        price: price,
        cost: 0,
        description: _mtmSelectedTemplate.menu_items || ''
    });

    if (!_mtmSelectedTypeIds.includes(Number(_mtmSelectedTypeId))) _mtmSelectedTypeIds.push(Number(_mtmSelectedTypeId));
    if (!_mtmSelectedTemplateIds[_mtmSelectedTypeId]) _mtmSelectedTemplateIds[_mtmSelectedTypeId] = [];
    if (!_mtmSelectedTemplateIds[_mtmSelectedTypeId].includes(Number(_mtmSelectedTemplate.id)))
        _mtmSelectedTemplateIds[_mtmSelectedTypeId].push(Number(_mtmSelectedTemplate.id));

    _mtmSelectedTemplate = null;
    _mtmSetPrice = 3000;
    _mtmLevel = 2;
    _mtmRenderBreadcrumb();
    _mtmUpdateFooter();
    _mtmLoadTemplates(_mtmSelectedTypeId);
}

function _mtmRemoveSetItem(idx) {
    _mtmSetItems.splice(idx, 1);
    _mtmUpdateFooter();
    if (_mtmLevel === 3) {
        _mtmRenderSetPricePanel();
    } else {
        _mtmLoadTemplates(_mtmSelectedTypeId);
    }
}

function _mtmLoadTemplates(typeId) {
    const body = document.getElementById('menuTypeModalBody');
    body.innerHTML = '<div class="loading-spinner"><div class="spinner-border text-primary mb-2"></div><div>กำลังโหลดรายการเมนู...</div></div>';

    const url = 'api/get_menu_templates_ajax.php?type_id=' + typeId;
    fetch(url)
        .then(r => r.json())
        .then(items => {
            _mtmTypeCache[typeId] = items;
            document.getElementById('menuTypeSearchInput').value = '';
            _mtmRenderTemplates();
        })
        .catch(() => {
            body.innerHTML = '<div class="no-results">ไม่สามารถโหลดข้อมูลได้</div>';
        });
}

function _mtmRenderTemplates(filter) {
    const body = document.getElementById('menuTypeModalBody');
    let html = '';
    filter = (filter || '').toLowerCase();

    const items = _mtmTypeCache[_mtmSelectedTypeId] || [];
    const filtered = filter
        ? items.filter(item => (item.menu_items || item.name || '').toLowerCase().includes(filter))
        : items;

    if (filtered.length === 0) {
        html = '<div class="no-results"><i class="bi bi-search" style="font-size:2rem;display:block;margin-bottom:8px;"></i>ไม่พบเมนูที่ค้นหา</div>';
    } else {
        filtered.forEach(item => {
            const prevSel = _mtmSelectedTemplateIds[_mtmSelectedTypeId] && _mtmSelectedTemplateIds[_mtmSelectedTypeId].includes(Number(item.id));
            const curSel = _mtmSelectedTemplate && _mtmSelectedTemplate.id === item.id;
            const sel = (prevSel || curSel) ? ' selected' : '';
            const shortDesc = (item.menu_items || '').length > 100 ? item.menu_items.substring(0, 100) + '...' : item.menu_items;
            const onclick = (_mtmSubMode === 'set')
                ? `_mtmPickTemplateForSet(${item.id})`
                : `_mtmSelectTemplate(${item.id})`;
            html += `<div class="template-card${sel}" onclick="${onclick}">
                <div class="tpl-subtitle text-muted" style="font-size:0.8rem;">${_mtmEscapeHtml(_mtmSelectedType || '')}</div>
                <div class="tpl-name" style="font-size:1.1rem;font-weight:600;">${_mtmEscapeHtml(shortDesc || item.menu_items || item.name)}</div>
                <div class="tpl-price">${parseFloat(item.price_per_pax).toLocaleString('th-TH', {minimumFractionDigits:2})} บาท/หน่วย</div>
            </div>`;
        });
    }

    body.innerHTML = html;
}

function _mtmPickTemplateForSet(id) {
    const items = _mtmTypeCache[_mtmSelectedTypeId] || [];
    const item = items.find(t => t.id === id);
    if (!item) return;

    _mtmSelectedTemplate = item;

    document.querySelectorAll('.menu-type-modal-body .template-card').forEach(c => c.classList.remove('selected'));
    event.target.closest('.template-card').classList.add('selected');

    _mtmGoSetPrice();
}

function _mtmSelectTemplate(id) {
    const items = _mtmTypeCache[_mtmSelectedTypeId] || [];
    const item = items.find(t => t.id === id);
    if (!item) return;

    _mtmSelectedTemplate = item;

    document.querySelectorAll('.menu-type-modal-body .template-card').forEach(c => c.classList.remove('selected'));
    event.target.closest('.template-card').classList.add('selected');

    _mtmUpdateFooter();
}

function _mtmUpdateFooter() {
    const footer = document.getElementById('menuTypeFooter');
    const info = document.getElementById('menuTypeSelectedInfo');
    const btn = document.getElementById('menuTypeConfirmBtn');
    const btnLabel = document.getElementById('menuTypeConfirmLabel');
    const qtyWrap = document.getElementById('menuTypeQtyWrap');

    if (_mtmMode === 'type') {
        footer.style.display = 'none';
        return;
    }

    footer.style.display = 'flex';

    if (_mtmMode === 'template-menu' && _mtmSubMode === 'set') {
        qtyWrap.style.display = 'none';
        if (_mtmSetItems.length > 0) {
            const total = _mtmSetItems.reduce((s, i) => s + (i.qty * i.price), 0);
            info.innerHTML = '<strong>' + _mtmSetItems.length + ' รายการ</strong> — รวม ' + total.toLocaleString('th-TH', {minimumFractionDigits:0}) + ' บาท';
            info.style.color = '#198754';
            btn.disabled = false;
            btnLabel.textContent = 'ยืนยัน (' + _mtmSetItems.length + ' รายการ)';
        } else {
            info.textContent = 'เลือกเมนูแล้วกดเพิ่มลงรายการ';
            info.style.color = '#999';
            btn.disabled = true;
            btnLabel.textContent = 'ยืนยัน';
        }
    } else if (_mtmSelectedTemplate) {
        qtyWrap.style.display = 'inline';
        const name = _mtmSelectedTemplate.menu_items || _mtmSelectedTemplate.break_menu || '';
        const price = parseFloat(_mtmSelectedTemplate.price_per_pax || _mtmSelectedTemplate.break_price || 0);
        info.textContent = name.substring(0, 60) + (name.length > 60 ? '...' : '') + ' — ' + price.toLocaleString('th-TH', {minimumFractionDigits:2}) + ' บาท';
        info.style.color = '#333';
        btn.disabled = false;
        btnLabel.textContent = 'เลือก';
    } else {
        qtyWrap.style.display = 'none';
        info.textContent = 'กรุณาเลือกรายการเมนู';
        info.style.color = '#999';
        btn.disabled = true;
        btnLabel.textContent = 'เลือก';
    }
}

function confirmTemplateSelection() {
    let result = null;

    if (_mtmMode === 'template-menu' && _mtmSubMode === 'set') {
        if (_mtmSetItems.length === 0) return;
        result = _mtmSetItems.slice();
    } else if (_mtmSelectedTemplate) {
        const tpl = _mtmSelectedTemplate;
        const qtyEl = document.getElementById('mtmFooterQty');
        const qty = qtyEl ? (parseInt(qtyEl.value) || 1) : 1;
        result = {
            id: tpl.id,
            name: tpl.menu_items || tpl.break_menu || '',
            qty: qty,
            price: parseFloat(tpl.price_per_pax || tpl.break_price || 0),
            cost: parseFloat(tpl.cost_per_pax || tpl.break_cost || 0),
            description: tpl.menu_items || tpl.break_menu || ''
        };
    }

    if (!result) return;

    if (_mtmCallback && typeof window[_mtmCallback] === 'function') {
        window[_mtmCallback](result);
    }

    closeMenuTypeModal();
}

function openMenuTypeModal(triggerEl, callbackName) {
    let targetInput;
    if (triggerEl.classList.contains('btn-menu-type-picker')) {
        const row = triggerEl.closest('tr') || triggerEl.closest('.input-group') || triggerEl.parentElement;
        targetInput = row.querySelector('.menu-type-id') || row.querySelector('input[name="menu_set_id[]"]');
    } else {
        targetInput = triggerEl;
    }

    _mtmTarget = targetInput;
    _mtmCallback = callbackName || null;
    _mtmMode = 'type';
    _mtmSubMode = 'items';
    _mtmReset();

    document.getElementById('menuTypeTabs').classList.remove('visible');
    renderMenuTypeModalCards('');
    document.getElementById('menuTypeSearchInput').value = '';
    document.getElementById('menuTypeSearchInput').placeholder = 'พิมพ์เพื่อค้นหา...';
    document.getElementById('menuTypeFooter').style.display = 'none';
    document.getElementById('menuTypeModalOverlay').classList.add('active');
    document.getElementById('menuTypeSearchInput').focus();
}

function openTemplateModal(mode, callbackName, subMode) {
    _mtmTarget = null;
    _mtmCallback = callbackName || null;
    _mtmMode = mode;
    _mtmSubMode = subMode || 'set';
    _mtmReset();
    _mtmTypeCache = {};

    const tabs = document.getElementById('menuTypeTabs');
    document.getElementById('menuTypeSearchInput').value = '';

    if (mode === 'template-menu') {
        tabs.classList.add('visible');
        document.querySelectorAll('#menuTypeTabs .tab-btn').forEach(btn => {
            btn.classList.toggle('active', btn.dataset.submode === _mtmSubMode);
        });
        document.getElementById('menuTypeSearchInput').placeholder = 'พิมพ์เพื่อค้นหาเมนู...';
        renderMenuTypeModalCards('');
    } else if (mode === 'template-break') {
        tabs.classList.remove('visible');
        document.getElementById('menuTypeSearchInput').placeholder = 'พิมพ์เพื่อค้นหาเบรก...';
        _mtmRenderBreakTypes('');
    }

    _mtmRenderBreadcrumb();
    _mtmUpdateFooter();
    document.getElementById('menuTypeModalOverlay').classList.add('active');
    document.getElementById('menuTypeSearchInput').focus();
}

function _mtmRenderBreakTypes(filter) {
    const body = document.getElementById('menuTypeModalBody');
    filter = (filter || '').toLowerCase();

    let html = '';
    let hasResults = false;

    const types = _breakTypesData.filter(bt => !filter || bt.type_name.toLowerCase().includes(filter));

    if (types.length === 0) {
        html = '<div class="no-results"><i class="bi bi-search" style="font-size:2rem;display:block;margin-bottom:8px;"></i>ไม่พบประเภทเบรก</div>';
    } else {
        html += '<div class="card-grid">';
        types.forEach(bt => {
            const sel = (_mtmSelectedType === bt.type_name) ? ' selected' : '';
            html += `<div class="menu-card${sel}" onclick="_mtmSelectBreakType('${_mtmEscapeAttr(bt.type_name)}',${bt.id})">${_mtmEscapeHtml(bt.type_name)}</div>`;
        });
        html += '</div>';
    }

    body.innerHTML = html;
}

function _mtmSelectBreakType(typeName, typeId) {
    _mtmSelectedType = typeName;
    _mtmSelectedTypeId = typeId;
    _mtmLevel = 1;
    _mtmSelectedTemplate = null;
    _mtmUpdateFooter();
    _mtmRenderBreadcrumb();
    _mtmLoadBreakTemplates(typeId);
}

function _mtmLoadBreakTemplates(typeId) {
    const body = document.getElementById('menuTypeModalBody');
    body.innerHTML = '<div class="loading-spinner"><div class="spinner-border text-primary mb-2"></div><div>กำลังโหลดรายการเบรก...</div></div>';

    const url = 'api/get_break_templates_ajax.php?type_id=' + typeId;
    fetch(url)
        .then(r => r.json())
        .then(items => {
            _mtmTypeCache[typeId] = items;
            _mtmRenderBreakTemplates(items);
        })
        .catch(() => {
            body.innerHTML = '<div class="no-results">ไม่สามารถโหลดข้อมูลได้</div>';
        });
}

function _mtmRenderBreakTemplates(items) {
    const body = document.getElementById('menuTypeModalBody');
    let html = '';

    if (items.length === 0) {
        html = '<div class="no-results"><i class="bi bi-inbox" style="font-size:2rem;display:block;margin-bottom:8px;"></i>ยังไม่มีเทมเพลตเบรกในประเภทนี้</div>';
    } else {
        items.forEach(item => {
            const sel = (_mtmSelectedTemplate && _mtmSelectedTemplate.id === item.id) ? ' selected' : '';
            const shortDesc = (item.break_menu || '').length > 100 ? item.break_menu.substring(0, 100) + '...' : item.break_menu;
            html += `<div class="template-card${sel}" onclick="_mtmSelectBreakTemplate(${item.id})">
                <div class="tpl-name">${_mtmEscapeHtml('[ ' + (_mtmSelectedType || '') + ' ]')}</div>
                <div class="tpl-detail">${_mtmEscapeHtml(shortDesc)}</div>
                <div class="tpl-price">${parseFloat(item.break_price).toLocaleString('th-TH', {minimumFractionDigits:2})} บาท/หน่วย</div>
            </div>`;
        });
    }

    body.innerHTML = html;
}

function _mtmSelectBreakTemplate(id) {
    const typeId = _breakTypesData.find(bt => bt.type_name === _mtmSelectedType)?.id || 0;
    const items = _mtmTypeCache[typeId] || [];
    const item = items.find(t => t.id === id);
    if (!item) return;

    _mtmSelectedTemplate = item;

    document.querySelectorAll('.menu-type-modal-body .template-card').forEach(c => c.classList.remove('selected'));
    event.target.closest('.template-card').classList.add('selected');

    _mtmUpdateFooter();
}

function closeMenuTypeModal() {
    document.getElementById('menuTypeModalOverlay').classList.remove('active');
    _mtmTarget = null;
    _mtmMode = 'type';
    _mtmReset();
}

function selectMenuTypeFromModal(id, name) {
    if (_mtmTarget) {
        _mtmTarget.value = id;

        const row = _mtmTarget.closest('tr') || _mtmTarget.closest('.input-group') || _mtmTarget.parentElement;
        const label = row.querySelector('.menu-type-label');
        if (label) {
            label.textContent = name;
            label.classList.remove('text-muted');
            label.classList.add('fw-semibold', 'text-dark');
        }

        document.querySelectorAll('.menu-type-modal-body .menu-card').forEach(c => c.classList.remove('selected'));
        event.target.classList.add('selected');

        if (_mtmCallback && typeof window[_mtmCallback] === 'function') {
            window[_mtmCallback](id, name, _mtmTarget);
        }

        if (typeof fetchMenuDetailById === 'function') {
            fetchMenuDetailById(id, _mtmTarget);
        }
    }

    setTimeout(closeMenuTypeModal, 150);
}

function filterMenuTypeModal(val) {
    if (_mtmMode === 'type') {
        renderMenuTypeModalCards(val);
    } else if (_mtmMode === 'template-menu') {
        if (_mtmLevel === 0) renderMenuTypeModalCards(val);
        else if (_mtmLevel === 1) _mtmRenderTypesForCategory(_mtmSelectedCategory, val);
        else if (_mtmLevel === 2) _mtmRenderTemplates(val);
    } else if (_mtmMode === 'template-break') {
        if (_mtmLevel === 0) _mtmRenderBreakTypes(val);
    }
}

document.getElementById('menuTypeModalOverlay').addEventListener('click', function(e) {
    if (e.target === this) closeMenuTypeModal();
});

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeMenuTypeModal();
});

function convertSelectToModal(selectEl) {
    const name = selectEl.getAttribute('name');
    const currentVal = selectEl.value;
    const currentText = selectEl.options[selectEl.selectedIndex] ? selectEl.options[selectEl.selectedIndex].text : '';

    const hidden = document.createElement('input');
    hidden.type = 'hidden';
    hidden.name = name;
    hidden.className = 'menu-type-id';
    hidden.value = currentVal;

    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'btn-menu-type-picker';
    btn.innerHTML = '<i class="bi bi-grid-3x3-gap me-1"></i>เลือกเมนู';
    btn.onclick = function() { openMenuTypeModal(hidden); };

    const lbl = document.createElement('span');
    lbl.className = 'menu-type-label ' + (currentVal ? 'fw-semibold text-dark' : 'text-muted');
    lbl.textContent = currentVal ? currentText : 'ยังไม่ได้เลือก';

    selectEl.replaceWith(hidden);
    hidden.parentElement.insertBefore(btn, hidden.nextSibling);
    btn.parentElement.insertBefore(lbl, btn.nextSibling);

    return hidden;
}
</script>
