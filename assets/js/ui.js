/* =====================================================================
   assets/js/ui.js — ตัวช่วยหน้าต่างแจ้งเตือน/ยืนยันของทั้งระบบ
   ต้องโหลดหลัง SweetAlert2

   ใช้แทน alert()/confirm() ของเบราว์เซอร์:
     UI.alert('บันทึกแล้ว')                       → Promise (รอจนกดปิด)
     UI.alert('ผิดพลาด', 'error')
     UI.confirm('ยืนยันการลบ?').then(ok => { if (ok) … })
     UI.confirm({ title, text, confirmText, danger: true })
     UI.toast('บันทึกเรียบร้อย')                  → มุมขวาบน หายเอง

   ใน HTML ใส่ data-confirm แทน onclick="return confirm(…)":
     <a href="del.php?id=1" data-confirm="ลบรายการนี้?">…</a>
     <form data-confirm="ยืนยันการลบ?">…</form>
     <button data-confirm="…" data-confirm-danger>…</button>
   ===================================================================== */
(function (window, document) {
    'use strict';
    if (!window.Swal) return;

    var DEFAULT_BUTTONS = {
        confirmButton: 'btn btn-primary',
        cancelButton: 'btn btn-light',
        denyButton: 'btn btn-light'
    };

    // ค่าเริ่มต้นของ SweetAlert ทั้งระบบ: ใช้ปุ่มของธีม, ปุ่มยกเลิกอยู่ซ้าย
    var Base = window.Swal.mixin({
        buttonsStyling: false,
        reverseButtons: true,
        focusCancel: false,
        confirmButtonText: 'ตกลง',
        cancelButtonText: 'ยกเลิก',
        denyButtonText: 'ไม่',
        customClass: {
            confirmButton: 'btn btn-primary',
            cancelButton: 'btn btn-light',
            denyButton: 'btn btn-light'
        },
        showClass: { popup: 'swal2-show' },
        hideClass: { popup: 'swal2-hide' }
    });

    // หน้าที่เรียก Swal.fire({... confirmButtonColor: '#d33'}) จะได้ปุ่มสีแดงของธีมแทน
    // ใช้ this (ไม่ bind) เพื่อให้ mixin ลูก เช่น Toast ยังได้ค่าของตัวเอง
    var originalFire = Base.fire;
    Base.fire = function () {
        var args = Array.prototype.slice.call(arguments);
        if (args.length && typeof args[0] === 'object' && args[0] !== null && !args[0].toast) {
            var o = Object.assign({}, args[0]);
            var danger = /^#?(d33|dc3545|e74c3c|ff0000|f00|c00|b42318|dc2626|ef4444)/i.test(String(o.confirmButtonColor || '').trim());
            delete o.confirmButtonColor;
            delete o.cancelButtonColor;
            delete o.denyButtonColor;
            // customClass ที่หน้าส่งมาจะทับของ mixin ทั้งก้อน จึงต้องรวมค่าปุ่มเริ่มต้นเข้าไปใหม่
            o.customClass = Object.assign({}, DEFAULT_BUTTONS, o.customClass);
            if (danger || o.icon === 'warning' && /ลบ|delete|ยกเลิกงาน/i.test(String(o.confirmButtonText || ''))) {
                o.customClass.confirmButton = 'btn btn-danger';
            }
            args[0] = o;
        }
        return originalFire.apply(this || Base, args);
    };
    window.Swal = Base;

    var Toast = window.Swal.mixin({
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 2600,
        timerProgressBar: true,
        didOpen: function (t) {
            t.addEventListener('mouseenter', window.Swal.stopTimer);
            t.addEventListener('mouseleave', window.Swal.resumeTimer);
        }
    });

    function esc(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }
    // ข้อความยาวหลายบรรทัด: บรรทัดแรกเป็นหัวข้อ ที่เหลือเป็นรายละเอียด
    function splitMessage(msg) {
        var lines = String(msg == null ? '' : msg).split(/\n+/);
        var title = lines.shift();
        return { title: title, html: lines.length ? esc(lines.join('\n')).replace(/\n/g, '<br>') : undefined };
    }
    function guessIcon(msg) {
        var m = String(msg);
        if (/❌|ผิดพลาด|ไม่สำเร็จ|ล้มเหลว|error|ไม่สามารถ|ไม่พบ/i.test(m)) return 'error';
        if (/✅|สำเร็จ|เรียบร้อย/.test(m)) return 'success';
        if (/กรุณา|ต้อง|ห้าม/.test(m)) return 'warning';
        return 'info';
    }

    var UI = {
        alert: function (message, icon) {
            var o = typeof message === 'object' && message !== null ? message : null;
            if (o) return window.Swal.fire(Object.assign({ icon: 'info' }, o));
            var p = splitMessage(String(message).replace(/^[✅❌]\s*/, ''));
            return window.Swal.fire({ icon: icon || guessIcon(message), title: p.title, html: p.html });
        },
        confirm: function (message, opts) {
            var o = typeof message === 'object' && message !== null ? message : Object.assign({ text: message }, opts || {});
            var p = splitMessage(o.text || o.title || 'ยืนยันการดำเนินการ?');
            var danger = o.danger != null ? o.danger : /ลบ|ยกเลิก|ย้อนกลับไม่ได้|ไม่สามารถย้อนกลับ|delete/i.test(String(o.text || o.title));
            return window.Swal.fire({
                icon: o.icon || (danger ? 'warning' : 'question'),
                title: o.title && o.text ? o.title : p.title,
                html: o.title && o.text ? esc(o.text).replace(/\n/g, '<br>') : p.html,
                showCancelButton: true,
                confirmButtonText: o.confirmText || (danger ? 'ยืนยัน' : 'ตกลง'),
                cancelButtonText: o.cancelText || 'ยกเลิก',
                customClass: { confirmButton: danger ? 'btn btn-danger' : 'btn btn-primary', cancelButton: 'btn btn-light' }
            }).then(function (r) { return !!r.isConfirmed; });
        },
        toast: function (message, icon) {
            return Toast.fire({ icon: icon || 'success', title: message });
        }
    };
    window.UI = UI;

    // ---- data-confirm: ลิงก์ ฟอร์ม และปุ่ม ----
    function confirmFor(el) {
        return UI.confirm(el.getAttribute('data-confirm'), {
            danger: el.hasAttribute('data-confirm-danger') ? true : undefined,
            confirmText: el.getAttribute('data-confirm-ok') || undefined
        });
    }
    document.addEventListener('click', function (e) {
        var el = e.target.closest('[data-confirm]');
        if (!el || el.tagName === 'FORM' || el.__confirmed) return;
        e.preventDefault();
        e.stopImmediatePropagation();
        confirmFor(el).then(function (ok) {
            if (!ok) return;
            if (el.tagName === 'A' && el.href) { window.location.href = el.href; return; }
            el.__confirmed = true;
            el.click();
            el.__confirmed = false;
        });
    }, true);
    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (!form.matches || !form.matches('form[data-confirm]') || form.__confirmed) return;
        e.preventDefault();
        var submitter = e.submitter;
        confirmFor(form).then(function (ok) {
            if (!ok) return;
            form.__confirmed = true;
            if (form.requestSubmit) form.requestSubmit(submitter || undefined); else form.submit();
            form.__confirmed = false;
        });
    }, true);
})(window, document);
