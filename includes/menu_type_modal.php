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
.menu-type-modal-body .menu-card.disabled-card {
    cursor: not-allowed;
    opacity: 0.55;
    pointer-events: none;
}
.menu-type-modal-body .menu-card.disabled-card:hover {
    transform: none;
    box-shadow: none;
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
.menu-type-modal-body .template-card .tpl-img {
    width: 64px;
    height: 64px;
    object-fit: cover;
    border-radius: 8px;
    background: #f1f3f5;
    flex-shrink: 0;
}
.menu-type-modal-body .template-card .tpl-thumb-wrap {
    display: flex;
    gap: 12px;
    align-items: flex-start;
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
const _mtmImgCache = {};
const _mtmFallbackFoodUrl = 'https://upload.wikimedia.org/wikipedia/commons/thumb/4/4b/Indian_school_lunch_rice_meal_with_chicken_curry%2C_dal_fry%2C_paneer_butter_masala%2C_salad_and_papad.jpg/400px-Indian_school_lunch_rice_meal_with_chicken_curry%2C_dal_fry%2C_paneer_butter_masala%2C_salad_and_papad.jpg';

function _mtmImageUrl(keyword) {
    return 'https://commons.wikimedia.org/w/api.php?action=query&format=json&origin=*&generator=search&gsrsearch=' + encodeURIComponent(keyword) + '&gsrnamespace=6&gsrlimit=5&prop=imageinfo&iiprop=url|mime&iiurlwidth=400';
}

function _mtmSearchImage(keyword) {
    return fetch(_mtmImageUrl(keyword))
        .then(r => r.json())
        .then(data => {
            const pages = (data && data.query && data.query.pages) || {};
            const ids = Object.keys(pages).sort((a, b) => (pages[a].index || 0) - (pages[b].index || 0));
            for (const k of ids) {
                const p = pages[k];
                const ii = p.imageinfo && p.imageinfo[0];
                if (!ii) continue;
                const mime = ii.mime || ii.thumbmime || '';
                if (mime && mime.indexOf('image/') !== 0) continue;
                if (ii.thumburl) return ii.thumburl;
            }
            return null;
        });
}

function _mtmLoadMenuImage(imgEl, keyword) {
    const kw = (keyword || '').trim();
    const cacheKey = kw || 'food';
    if (_mtmImgCache[cacheKey] !== undefined) {
        if (_mtmImgCache[cacheKey]) imgEl.src = _mtmImgCache[cacheKey];
        else imgEl.style.display = 'none';
        return;
    }
    _mtmSearchImage(kw || 'food').then(url => {
        if (!url) return _mtmSearchImage('food');
        return url;
    }).then(url => {
        _mtmImgCache[cacheKey] = url || _mtmFallbackFoodUrl;
        imgEl.src = url || _mtmFallbackFoodUrl;
    }).catch(() => {
        _mtmImgCache[cacheKey] = _mtmFallbackFoodUrl;
        imgEl.src = _mtmFallbackFoodUrl;
    });
}

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
let _mtmBreakItems = [];
let _mtmBreakPending = null;

function _mtmEscapeHtml(text) {
    const d = document.createElement('div');
    d.appendChild(document.createTextNode(text));
    return d.innerHTML;
}

function _mtmEscapeAttr(text) {
    return text.replace(/&/g,'&amp;').replace(/'/g,'&#39;').replace(/"/g,'&quot;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

function _mtmGetCategorySetPrice() {
    const mt = _menuTypesData.find(t => Number(t.id) === Number(_mtmSelectedTypeId));
    const sp = mt ? mt.set_price : null;
    return (sp !== null && sp !== undefined && sp !== '') ? parseFloat(sp) : null;
}

function _mtmGetBreakTypePrice() {
    const bt = _breakTypesData.find(t => Number(t.id) === Number(_mtmSelectedTypeId));
    const p = bt ? bt.break_price : null;
    return (p !== null && p !== undefined && p !== '') ? parseFloat(p) : null;
}

function _mtmReset() {
    _mtmLevel = 0;
    _mtmSelectedCategory = null;
    _mtmSelectedType = null;
    _mtmSelectedTypeId = null;
    _mtmSelectedTemplate = null;
    _mtmSetPrice = 3000;
    _mtmSetItems = [];
    _mtmBreakItems = [];
    _mtmBreakPending = null;
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
    if (_mtmMode === 'template-break') _mtmRenderBreakTypes('');
    else renderMenuTypeModalCards('');
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
        else if (level === 1) _mtmLoadBreakTemplates(_mtmSelectedTypeId);
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
            const isSetMode = _mtmMode === 'template-menu' && _mtmSubMode === 'set';
            const sel = (_mtmSelectedTypeIds.includes(Number(mt.id))) ? ' selected' : '';
            const cnt = (_mtmSelectedTemplateIds[mt.id] || []).length;
            const disabled = isSetMode && _mtmSetItems.length > 0 && _mtmSelectedTypeIds.includes(Number(mt.id));
            html += `<div class="menu-card${sel}${disabled ? ' disabled-card' : ''}"${disabled ? '' : ` onclick="_mtmSelectType('${_mtmEscapeAttr(mt.type_name)}',${mt.id})"`}>${_mtmEscapeHtml(mt.type_name)}${cnt ? `<small class="text-muted d-block">${cnt} รายการ</small>` : ''}</div>`;
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

    const setPrice = _mtmGetCategorySetPrice();
    _mtmSetPrice = setPrice !== null ? setPrice : 0;

    let priceHtml = '';
    if (setPrice !== null && setPrice > 0) {
        priceHtml = '<div style="font-size:1.3rem;font-weight:700;color:#198754;margin-bottom:14px;">'
            + 'ราคาเซต ' + setPrice.toLocaleString('th-TH', {minimumFractionDigits: 0}) + ' บาท</div>';
        priceHtml += '<div class="set-hint">ใช้ราคาเซตของกลุ่มนี้ (จำนวนจะใส่ตอนยืนยันด้านล่าง)</div>';
    } else {
        priceHtml = '<div style="font-size:0.95rem;font-weight:600;color:#b8860b;margin-bottom:8px;">ยังไม่ได้ตั้งราคาเซตสำหรับกลุ่มนี้</div>';
        priceHtml += '<div style="max-width:300px;margin:0 auto 14px;text-align:left;">'
            + '<label style="font-size:0.8rem;font-weight:600;color:#555;">ราคาเซต (กรอกได้)</label>'
            + '<input type="number" id="mtmSetPriceInput" placeholder="เช่น 3000" min="0" step="0.01" '
            + 'style="width:100%;border:2px solid #e0e0e0;border-radius:10px;padding:8px 12px;font-size:0.9rem;color:#333;outline:none;">'
            + '</div>';
    }

    let listHtml = '';
    if (_mtmSetItems.length > 0) {
        listHtml = '<div style="margin-top:20px;text-align:left;border-top:2px dashed #dee2e6;padding-top:14px;">';
        listHtml += '<div style="font-size:0.8rem;font-weight:700;color:#6c757d;margin-bottom:8px;"><i class="bi bi-list-check me-1"></i>รายการที่เลือกแล้ว (' + _mtmSetItems.length + ')</div>';
        _mtmSetItems.forEach((item, idx) => {
            listHtml += '<div style="display:flex;align-items:center;justify-content:space-between;padding:8px 10px;background:#f8f9fa;border-radius:8px;margin-bottom:4px;font-size:0.82rem;">';
            listHtml += '<div style="flex:1;overflow:hidden;">';
            listHtml += '<div style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">' + _mtmEscapeHtml(item.name.substring(0, 50)) + (item.name.length > 50 ? '...' : '') + '</div>';
            if (item.note) {
                listHtml += '<div style="color:#b8860b;font-size:0.75rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><i class="bi bi-pencil-square me-1"></i>หมายเหตุ: ' + _mtmEscapeHtml(item.note) + '</div>';
            }
            listHtml += '</div>';
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
            ${priceHtml}
            <div style="max-width:300px;margin:10px auto 0;text-align:left;">
                <label style="font-size:0.8rem;font-weight:600;color:#555;">หมายเหตุ (ไม่บังคับ)</label>
                <input type="text" id="mtmSetNoteInput" placeholder="เช่น ไม่ใส่ใบกระเพรา" maxlength="200" style="width:100%;border:2px solid #e0e0e0;border-radius:10px;padding:8px 12px;font-size:0.9rem;color:#333;outline:none;">
            </div>
            <button type="button" class="btn btn-success fw-bold mt-3" onclick="_mtmAddToSetList()" style="min-width:160px;">
                <i class="bi bi-plus-circle me-1"></i>เพิ่มลงรายการ
            </button>
        </div>
        ${listHtml}
    `;
    setTimeout(() => {
        const inp = document.getElementById('mtmSetPriceInput') || document.getElementById('mtmSetNoteInput');
        if (inp) inp.focus();
    }, 100);
}

function _mtmAddToSetList() {
    if (!_mtmSelectedTemplate) return;
    const manualEl = document.getElementById('mtmSetPriceInput');
    const manualPrice = manualEl ? (parseFloat(manualEl.value) || 0) : 0;
    const price = _mtmSetPrice > 0 ? _mtmSetPrice : manualPrice;
    const qty = 1;
    const noteEl = document.getElementById('mtmSetNoteInput');
    const note = noteEl ? noteEl.value.trim() : '';
    if (price <= 0) { alert('ยังไม่ได้ตั้งราคาเซตสำหรับกลุ่มนี้ กรุณากรอกราคาเซตก่อน'); return; }

    _mtmSetItems.push({
        id: _mtmSelectedTemplate.id,
        type_id: _mtmSelectedTypeId,
        type_name: _mtmSelectedType,
        name: _mtmSelectedTemplate.menu_items || _mtmSelectedType,
        qty: qty,
        note: note,
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
    _mtmLevel = 1;
    _mtmSelectedType = null;
    _mtmSelectedTypeId = null;
    _mtmRenderBreadcrumb();
    _mtmUpdateFooter();
    document.getElementById('menuTypeSearchInput').value = '';
    _mtmRenderTypesForCategory(_mtmSelectedCategory, '');
}

function _mtmRemoveSetItem(idx) {
    _mtmSetItems.splice(idx, 1);
    _mtmSelectedTypeIds = [];
    _mtmSelectedTemplateIds = {};
    _mtmSetItems.forEach(function(item) {
        const tid = Number(item.type_id);
        if (!_mtmSelectedTypeIds.includes(tid)) _mtmSelectedTypeIds.push(tid);
        if (!_mtmSelectedTemplateIds[tid]) _mtmSelectedTemplateIds[tid] = [];
        if (!_mtmSelectedTemplateIds[tid].includes(Number(item.id)))
            _mtmSelectedTemplateIds[tid].push(Number(item.id));
    });
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
            const imgKw = (item.menu_items || item.name || _mtmSelectedType || '').split('\n')[0].trim();
            const onclick = (_mtmSubMode === 'set')
                ? `_mtmPickTemplateForSet(${item.id})`
                : `_mtmSelectTemplate(${item.id})`;
            html += `<div class="template-card${sel}" onclick="${onclick}">
                <div class="tpl-thumb-wrap">
                    <img class="tpl-img" data-kw="${_mtmEscapeAttr(imgKw)}" alt="" loading="lazy" onerror="this.style.display='none'">
                    <div style="flex:1;min-width:0;">
                        <div class="tpl-subtitle text-muted" style="font-size:0.8rem;">${_mtmEscapeHtml(_mtmSelectedType || '')}</div>
                        <div class="tpl-name" style="font-size:1.1rem;font-weight:600;">${_mtmEscapeHtml(shortDesc || item.menu_items || item.name)}</div>
                        <div class="tpl-price">${parseFloat(item.price_per_pax).toLocaleString('th-TH', {minimumFractionDigits:2})} บาท/หน่วย</div>
                    </div>
                </div>
            </div>`;
        });
    }

    body.innerHTML = html;
    body.querySelectorAll('.tpl-img').forEach(img => {
        _mtmLoadMenuImage(img, img.getAttribute('data-kw') || 'food');
    });
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
        qtyWrap.style.display = 'inline';
        const qtyEl = document.getElementById('mtmFooterQty');
        const qty = qtyEl ? (parseInt(qtyEl.value) || 1) : 1;
        if (_mtmSetItems.length > 0) {
            const total = _mtmSetItems[0].price * qty;
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
    } else if (_mtmMode === 'template-break') {
        if (_mtmSubMode === 'set') {
            qtyWrap.style.display = 'inline';
            if (_mtmBreakItems.length > 0) {
                const total = _mtmBreakItems[0].price;
                info.innerHTML = '<strong>' + _mtmBreakItems.length + ' รายการ</strong> — รวม ' + total.toLocaleString('th-TH', {minimumFractionDigits:0}) + ' บาท';
                info.style.color = '#198754';
                btn.disabled = false;
                btnLabel.textContent = 'ยืนยัน (' + _mtmBreakItems.length + ' รายการ)';
            } else {
                info.textContent = 'เลือกเบรกแล้วกดยืนยัน';
                info.style.color = '#999';
                btn.disabled = true;
                btnLabel.textContent = 'ยืนยัน';
            }
        } else {
            qtyWrap.style.display = 'none';
            if (_mtmBreakItems.length > 0) {
                const total = _mtmBreakItems.reduce(function(s, it) { return s + (it.qty * it.price); }, 0);
                info.innerHTML = '<strong>' + _mtmBreakItems.length + ' รายการ</strong> — รวม ' + total.toLocaleString('th-TH', {minimumFractionDigits:0}) + ' บาท';
                info.style.color = '#198754';
                btn.disabled = false;
                btnLabel.textContent = 'ยืนยัน (' + _mtmBreakItems.length + ' รายการ)';
            } else {
                info.textContent = 'เลือกเบรกแล้วกดยืนยัน';
                info.style.color = '#999';
                btn.disabled = true;
                btnLabel.textContent = 'ยืนยัน';
            }
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
        var setQty = parseInt(document.getElementById('mtmFooterQty').value) || 1;
        result = _mtmSetItems.slice();
        result.forEach(function(it) { it.qty = setQty; });
        result.category_name = _mtmSelectedCategory || '';
        result.set_price = _mtmSetItems[0].price;
    } else if (_mtmMode === 'template-break') {
        if (_mtmBreakItems.length === 0) return;
        if (_mtmSubMode === 'set') {
            var setQty = parseInt(document.getElementById('mtmFooterQty').value) || 1;
            result = _mtmBreakItems.slice();
            result.forEach(function(it) { it.qty = setQty; });
            result.set_price = _mtmBreakItems[0].price;
            result.category_name = _mtmBreakItems[0].type_name || '';
        } else {
            result = _mtmBreakItems.slice();
        }
    } else if (_mtmSelectedTemplate) {
        const tpl = _mtmSelectedTemplate;
        const qtyEl = document.getElementById('mtmFooterQty');
        const qty = qtyEl ? (parseInt(qtyEl.value) || 1) : 1;
        result = {
            id: tpl.id,
            type_id: _mtmSelectedTypeId,
            type_name: _mtmSelectedType || '',
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
        tabs.classList.add('visible');
        document.querySelectorAll('#menuTypeTabs .tab-btn').forEach(btn => {
            btn.classList.toggle('active', btn.dataset.submode === _mtmSubMode);
        });
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
            const hasItems = _mtmBreakItems.some(it => it.type_name === bt.type_name);
            const isSetMode = _mtmMode === 'template-break' && _mtmSubMode === 'set';
            const disabled = isSetMode && _mtmBreakItems.length > 0 && hasItems;
            const cnt = _mtmBreakItems.filter(it => it.type_name === bt.type_name).length;
            html += `<div class="menu-card${sel || (hasItems ? ' selected' : '')}${disabled ? ' disabled-card' : ''}"${disabled ? '' : ` onclick="_mtmSelectBreakType('${_mtmEscapeAttr(bt.type_name)}',${bt.id})"`}>${_mtmEscapeHtml(bt.type_name)}${cnt ? `<small class="text-muted d-block">${cnt} รายการ</small>` : ''}</div>`;
        });
        html += '</div>';
    }

    html += _mtmBreakListHtml();
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
            const sel = (_mtmBreakItems.some(it => it.id === item.id)) ? ' selected' : '';
            const shortDesc = (item.break_menu || '').length > 100 ? item.break_menu.substring(0, 100) + '...' : item.break_menu;
            const typePrice = _mtmGetBreakTypePrice();
            const showPrice = (typePrice !== null) ? typePrice : (parseFloat(item.break_price) || 0);
            html += `<div class="template-card${sel}" onclick="_mtmSelectBreakTemplate(${item.id})">
                <div class="tpl-thumb-wrap">
                    <img class="tpl-img" data-kw="${_mtmEscapeAttr(item.break_menu || '')}" alt="" loading="lazy" onerror="this.style.display='none'">
                    <div style="flex:1;min-width:0;">
                        <div class="tpl-subtitle text-muted" style="font-size:0.8rem;">${_mtmEscapeHtml(_mtmSelectedType || '')}</div>
                        <div class="tpl-name" style="font-size:1.1rem;font-weight:600;">${_mtmEscapeHtml(shortDesc)}</div>
                        <div class="tpl-price">${showPrice.toLocaleString('th-TH', {minimumFractionDigits:2})} บาท/หน่วย</div>
                    </div>
                </div>
            </div>`;
        });
    }

    html += _mtmBreakListHtml();
    body.innerHTML = html;
    body.querySelectorAll('.tpl-img').forEach(img => {
        _mtmLoadMenuImage(img, img.getAttribute('data-kw') || 'food');
    });
}

function _mtmBreakListHtml() {
    if (_mtmBreakItems.length === 0) return '';
    const isSet = (_mtmMode === 'template-break' && _mtmSubMode === 'set');
    let h = '<div style="margin-top:20px;text-align:left;border-top:2px dashed #dee2e6;padding-top:14px;">';
    h += '<div style="font-size:0.8rem;font-weight:700;color:#6c757d;margin-bottom:8px;"><i class="bi bi-list-check me-1"></i>เบรกที่เลือกแล้ว (' + _mtmBreakItems.length + ')</div>';
    _mtmBreakItems.forEach((item, idx) => {
        h += '<div style="display:flex;align-items:center;justify-content:space-between;gap:8px;padding:8px 10px;background:#f8f9fa;border-radius:8px;margin-bottom:4px;font-size:0.82rem;">';
        h += '<div style="flex:1;min-width:0;overflow:hidden;">';
        h += '<div style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">' + _mtmEscapeHtml(item.name) + '</div>';
        if (item.note) {
            h += '<div style="color:#b8860b;font-size:0.75rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><i class="bi bi-pencil-square me-1"></i>หมายเหตุ: ' + _mtmEscapeHtml(item.note) + '</div>';
        }
        h += '</div>';
        if (!isSet) {
            h += '<div style="display:flex;align-items:center;gap:6px;white-space:nowrap;">';
            h += '<label style="font-size:0.7rem;color:#555;">จำนวน</label>';
            h += '<input type="number" value="' + item.qty + '" min="1" style="width:55px;border:1px solid #ccc;border-radius:6px;padding:3px 5px;font-size:0.8rem;text-align:center;" onchange="_mtmSetBreakQty(' + idx + ', this.value)">';
            h += '<span style="font-weight:600;color:#198754;font-size:0.82rem;">' + (item.qty * item.price).toLocaleString('th-TH', {minimumFractionDigits:0}) + ' บาท</span>';
            h += '</div>';
        }
        h += '<button type="button" class="btn btn-sm btn-outline-danger" style="padding:2px 6px;font-size:0.7rem;" onclick="_mtmRemoveBreakItem(' + idx + ')"><i class="bi bi-x"></i></button>';
        h += '</div>';
    });
    h += '</div>';
    return h;
}

function _mtmSetBreakQty(idx, val) {
    _mtmBreakItems[idx].qty = parseInt(val) || 1;
    _mtmUpdateFooter();
    const typeId = _breakTypesData.find(bt => bt.type_name === _mtmSelectedType)?.id || 0;
    if (_mtmLevel === 0) _mtmRenderBreakTypes('');
    else _mtmRenderBreakTemplates(_mtmTypeCache[typeId] || []);
}

function _mtmRemoveBreakItem(idx) {
    _mtmBreakItems.splice(idx, 1);
    _mtmUpdateFooter();
    if (_mtmBreakPending) {
        _mtmRenderBreakPricePanel();
        return;
    }
    const typeId = _breakTypesData.find(bt => bt.type_name === _mtmSelectedType)?.id || 0;
    if (_mtmLevel === 0) _mtmRenderBreakTypes('');
    else _mtmRenderBreakTemplates(_mtmTypeCache[typeId] || []);
}

function _mtmSelectBreakTemplate(id) {
    const typeId = _breakTypesData.find(bt => bt.type_name === _mtmSelectedType)?.id || 0;
    const items = _mtmTypeCache[typeId] || [];
    const item = items.find(t => t.id === id);
    if (!item) return;

    if (_mtmBreakItems.some(it => it.id === item.id)) return;

    const typePrice = _mtmGetBreakTypePrice();
    _mtmBreakPending = {
        id: item.id,
        type_name: _mtmSelectedType || '',
        name: item.break_menu || '',
        qty: 1,
        note: '',
        price: (typePrice !== null) ? typePrice : (parseFloat(item.break_price) || 0),
        cost: parseFloat(item.break_cost) || 0,
        description: item.break_menu || ''
    };

    _mtmUpdateFooter();
    if (_mtmSubMode === 'set') {
        _mtmRenderBreakPricePanel();
    } else {
        _mtmBreakItems.push(_mtmBreakPending);
        _mtmBreakPending = null;
        _mtmRenderBreakTemplates(items);
    }
}

function _mtmRenderBreakPricePanel() {
    const body = document.getElementById('menuTypeModalBody');
    const item = _mtmBreakPending;
    if (!item) return;
    const shortName = item.name.length > 80 ? item.name.substring(0, 80) + '...' : item.name;

    let listHtml = '';
    if (_mtmBreakItems.length > 0) {
        listHtml = '<div style="margin-top:20px;text-align:left;border-top:2px dashed #dee2e6;padding-top:14px;">';
        listHtml += '<div style="font-size:0.8rem;font-weight:700;color:#6c757d;margin-bottom:8px;"><i class="bi bi-list-check me-1"></i>เบรกที่เลือกแล้ว (' + _mtmBreakItems.length + ')</div>';
        _mtmBreakItems.forEach((it, idx) => {
            listHtml += '<div style="display:flex;align-items:center;justify-content:space-between;padding:8px 10px;background:#f8f9fa;border-radius:8px;margin-bottom:4px;font-size:0.82rem;">';
            listHtml += '<div style="flex:1;overflow:hidden;">';
            listHtml += '<div style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">' + _mtmEscapeHtml(it.name.substring(0, 50)) + (it.name.length > 50 ? '...' : '') + '</div>';
            if (it.note) {
                listHtml += '<div style="color:#b8860b;font-size:0.75rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><i class="bi bi-pencil-square me-1"></i>หมายเหตุ: ' + _mtmEscapeHtml(it.note) + '</div>';
            }
            listHtml += '</div>';
            listHtml += '<button type="button" class="btn btn-sm btn-outline-danger ms-2" style="padding:2px 6px;font-size:0.7rem;" onclick="_mtmRemoveBreakItem(' + idx + ')"><i class="bi bi-x"></i></button>';
            listHtml += '</div>';
        });
        listHtml += '</div>';
    }

    body.innerHTML = `
        <div style="text-align:left;margin-bottom:6px;">
            <button type="button" class="btn btn-sm btn-outline-secondary" style="font-size:0.75rem;" onclick="_mtmCancelBreakPanel()"><i class="bi bi-arrow-left me-1"></i>ย้อนกลับ</button>
        </div>
        <div class="set-price-panel">
            <div class="set-label">เพิ่มรายการเบรก</div>
            <div class="set-sublabel">${_mtmEscapeHtml(item.type_name || '')}</div>
            <div class="set-type-badge" style="max-width:100%;white-space:normal;text-align:left;font-size:0.8rem;">${_mtmEscapeHtml(shortName)}</div>
            <div style="font-size:1.3rem;font-weight:700;color:${item.price > 0 ? '#198754' : '#999'};margin-bottom:14px;">
                ${item.price > 0 ? item.price.toLocaleString('th-TH', {minimumFractionDigits: 0}) + ' บาท' : 'ยังไม่ได้ตั้งราคาเบรกสำหรับประเภทนี้'}
            </div>
            <div style="max-width:300px;margin:10px auto 0;text-align:left;">
                <label style="font-size:0.8rem;font-weight:600;color:#555;">หมายเหตุ (ไม่บังคับ)</label>
                <input type="text" id="mtmBreakNoteInput" placeholder="เช่น ไม่ใส่ผักชี" maxlength="200" style="width:100%;border:2px solid #e0e0e0;border-radius:10px;padding:8px 12px;font-size:0.9rem;color:#333;outline:none;">
            </div>
            <div class="set-hint">ใช้ราคาเบรกของประเภทนี้ (จำนวนจะใส่ตอนยืนยันด้านล่าง)</div>
            <button type="button" class="btn btn-success fw-bold mt-3" onclick="_mtmAddToBreakList()" style="min-width:160px;">
                <i class="bi bi-plus-circle me-1"></i>เพิ่มลงรายการ
            </button>
        </div>
        ${listHtml}
    `;
    setTimeout(() => {
        const inp = document.getElementById('mtmBreakNoteInput');
        if (inp) inp.focus();
    }, 100);
}

function _mtmCancelBreakPanel() {
    if (_mtmBreakPending) {
        _mtmBreakPending = null;
        const typeId = _breakTypesData.find(bt => bt.type_name === _mtmSelectedType)?.id || 0;
        _mtmRenderBreakTemplates(_mtmTypeCache[typeId] || []);
    }
}

function _mtmAddToBreakList() {
    if (!_mtmBreakPending) return;
    const noteEl = document.getElementById('mtmBreakNoteInput');
    _mtmBreakPending.note = noteEl ? noteEl.value.trim() : '';

    _mtmBreakItems.push(_mtmBreakPending);
    _mtmBreakPending = null;

    _mtmUpdateFooter();
    _mtmLevel = 0;
    _mtmSelectedType = null;
    _mtmSelectedTypeId = null;
    _mtmRenderBreadcrumb();
    document.getElementById('menuTypeSearchInput').value = '';
    _mtmRenderBreakTypes('');
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
