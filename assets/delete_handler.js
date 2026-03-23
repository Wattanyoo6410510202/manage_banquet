$(document).ready(function () {
  function executeDelete(ids, rows) {
    Swal.fire({
      title: "ยืนยันการลบ?",
      text: "การดำเนินการนี้ไม่สามารถย้อนกลับได้ คุณแน่ใจหรือไม่ว่าต้องการลบรายการนี้?",
      icon: "warning",
      showCancelButton: true,
      confirmButtonColor: "#d33",
      cancelButtonColor: "#3085d6",
      confirmButtonText: "ลบเลย!",
      cancelButtonText: "ยกเลิก",
    }).then((result) => {
      if (result.isConfirmed) {
        $.ajax({
          url: "api/delete_function.php",
          type: "POST",
          data: { ids: ids },
          dataType: "json",
          success: function (response) {
            if (response.status === "success") {
              // 🚀 1. ลบแถวออกจากตารางด้วย Effect (ความเนียนที่จารชอบ)
              $(rows).fadeOut(400, function () {
                $(this).remove();
              });

              // ⚡️ 2. ดึงหน้าแจ้งเตือน Flash Message มาแสดงใหม่ด้านบน (แทน Swal.fire เดิม)
              // วิธีนี้จะทำให้หน้าเว็บไม่รีโหลด แต่แถบเขียวจะเด้งขึ้นมาเอง
              $("#alert-container").empty().load("assets/alert.php");

              // ⚡️ 3. ล้างค่าสถานะการเลือก (Checkbox)
              clearSelection();
            } else {
              Swal.fire("พลาดแล้ว!", response.message, "error");
            }
          },
          error: function () {
            Swal.fire("พัง!", "ติดต่อ Server ไม่ได้ครับจาร", "error");
          },
        });
      }
    });
  }

  // ฟังก์ชันล้างค่า Select
  function clearSelection() {
    $(".row-checkbox").prop("checked", false);
    $("#selectAll").prop("checked", false);
    $("#deleteSelected").fadeOut();
    $("#selectCount").text("0");
  }

  // Event: ลบทีละแถว (ปุ่มถังขยะท้ายแถว)
  // ใช้ $(document).on เพื่อให้รองรับกรณี DataTables เปลี่ยนหน้า
  $(document).on("click", ".btn-delete, .btn-delete-row", function () {
    const id = $(this).data("id");
    const row = $(this).closest("tr");
    executeDelete([id], row);
  });

  // Event: ลบหลายแถว (ปุ่มแดงด้านบน)
  $("#deleteSelected").on("click", function () {
    const ids = [];
    const rows = [];
    $(".row-checkbox:checked").each(function () {
      ids.push($(this).val());
      rows.push($(this).closest("tr"));
    });

    if (ids.length > 0) {
      executeDelete(ids, rows);
    }
  });

  // Event: ติ๊กเลือกทั้งหมด / ติ๊กรายตัว (เพื่อโชว์ปุ่มลบ)
  $(document).on("change", ".row-checkbox, #selectAll", function () {
    const selectedCount = $(".row-checkbox:checked").length;
    $("#selectCount").text(selectedCount);

    if (selectedCount > 0) {
      $("#deleteSelected").fadeIn();
    } else {
      $("#deleteSelected").fadeOut();
    }
  });

  // $(document).on("click", ".btn-approve-row", function () {
  //   const id = $(this).data("id");
  //   const row = $(this).closest("tr");
  //   const button = $(this);

  //   Swal.fire({
  //     title: "ยืนยันการอนุมัติ?",
  //     text: "คุณต้องการอนุมัติรายการนี้ใช่หรือไม่?",
  //     icon: "question",
  //     showCancelButton: true,
  //     confirmButtonColor: "#198754",
  //     cancelButtonColor: "#6c757d",
  //     confirmButtonText: "ตกลง, อนุมัติเลย!",
  //     cancelButtonText: "ยกเลิก",
  //   }).then((result) => {
  //     if (result.isConfirmed) {
  //       $.ajax({
  //         url: "approve_event.php",
  //         type: "POST",
  //         data: { id: id },
  //         dataType: "json",
  //         success: function (response) {
  //           if (response.status === "success") {
  //             // 1. ทำให้แถวเปลี่ยนสถานะทันที (ความเนียน)
  //             row
  //               .find("td")
  //               .eq(6)
  //               .find(".badge")
  //               .removeClass("bg-warning-subtle text-warning")
  //               .addClass("bg-success-subtle text-success")
  //               .html('<i class="bi bi-check-circle me-1"></i> อนุมัติแล้ว');

  //             button.remove();

  //             // 2. ดึงเอาหน้า alert.php มาแสดงใหม่ใน #alert-container โดยไม่ต้องรีโหลดทั้งหน้า
  //             $("#alert-container").load("assets/alert.php");

  //             // หรือถ้าต้องการให้ชัวร์ว่า Session ทำงานครบถ้วน แนะนำให้ใช้:
  //             // location.reload();
  //           } else {
  //             Swal.fire("ผิดพลาด!", response.message, "error");
  //           }
  //         },
  //       });
  //     }
  //   });
  // });
  $(document).on("click", ".btn-status-change, .btn-approve-row", function () {
    const id = $(this).data("id");
    const newStatus = $(this).data("status") || "Confirmed";
    const row = $(this).closest("tr");
    const currentBtn = $(this);

    // 1. ตั้งค่าสีและข้อความตามสถานะ (สำหรับ Badge)
    const statusConfig = {
      Confirmed: {
        text: "อนุมัติแล้ว",
        class: "bg-success-subtle text-success",
        icon: "bi-check-circle",
      },
      "In Progress": {
        text: "ดำเนินการ",
        class: "bg-info-subtle text-info",
        icon: "bi-play-circle",
      },
      Completed: {
        text: "จบงานแล้ว",
        class: "bg-primary-subtle text-primary",
        icon: "bi-flag",
      },
      Cancelled: {
        text: "ยกเลิก",
        class: "bg-danger-subtle text-danger",
        icon: "bi-x-circle",
      },
    };

    Swal.fire({
      title: "ยืนยันการทำรายการ?",
      text: `ต้องการเปลี่ยนสถานะเป็น "${newStatus}" ใช่หรือไม่?`,
      icon: "question",
      showCancelButton: true,
      confirmButtonText: "ตกลง",
      cancelButtonText: "ยกเลิก",
      confirmButtonColor: newStatus === "Cancelled" ? "#dc3545" : "#198754",
    }).then((result) => {
      if (result.isConfirmed) {
        $.ajax({
          url: "approve_event.php", // ไฟล์ PHP ที่จารย์ใช้จัดการ DB
          type: "POST",
          data: { id: id, status: newStatus },
          dataType: "json",
          success: function (response) {
            if (response.status === "success") {
              // --- ✅ ส่วนที่ 1: อัปเดต Badge (ช่องที่ 7) ---
              const config = statusConfig[newStatus];
              row.find("td").eq(6).html(`
                            <span class="badge ${config.class} rounded-pill px-3">
                                <i class="bi ${config.icon} me-1"></i> ${config.text}
                            </span>
                        `);

              // --- ✅ ส่วนที่ 2: สร้างชุดปุ่มใหม่ (Action Buttons) ---
              let actionButtons = `<div class="d-flex justify-content-center gap-1">`;

              if (newStatus === "Confirmed") {
                actionButtons += `
                                <button type="button" class="btn btn-sm btn-info text-white btn-status-change" data-id="${id}" data-status="In Progress" title="เริ่มดำเนินการ"><i class="bi bi-play-fill"></i> ดำเนินการ</button>
                                <button type="button" class="btn btn-sm btn-outline-danger btn-status-change" data-id="${id}" data-status="Cancelled" title="ยกเลิก"><i class="bi bi-x-lg"></i></button>
                            `;
              } else if (newStatus === "In Progress") {
                actionButtons += `
                                <button type="button" class="btn btn-sm btn-primary btn-status-change" data-id="${id}" data-status="Completed" title="จบงาน"><i class="bi bi-flag-fill"></i> จบงาน</button>
                                <button type="button" class="btn btn-sm btn-outline-danger btn-status-change" data-id="${id}" data-status="Cancelled" title="ยกเลิก"><i class="bi bi-x-lg"></i></button>
                            `;
              }

              // ปุ่มพื้นฐาน (ดูรายละเอียด)
              actionButtons += `
                            <div class="vr mx-1"></div>
                            <a href="view.php?id=${id}" class="btn btn-sm btn-outline-primary" title="ดูรายละเอียด"><i class="bi bi-printer"></i></a>
                        `;

              // ถ้ายังไม่จบ/ไม่ยกเลิก ให้มีปุ่มแก้ไขและลบ
              if (newStatus !== "Completed" && newStatus !== "Cancelled") {
                actionButtons += `
                                <a href="edit.php?id=${id}" class="btn btn-sm btn-outline-dark" title="แก้ไข"><i class="bi bi-pencil-square"></i></a>
                                <button type="button" class="btn btn-sm btn-outline-danger btn-delete-row" data-id="${id}" title="ลบ"><i class="bi bi-trash"></i></button>
                            `;
              }

              actionButtons += `</div>`;

              // ยัดปุ่มใหม่ลงช่อง Action (td.sticky-col)
              row.find("td.sticky-col").html(actionButtons);

              // --- ✅ ส่วนที่ 3: โหลด Dashboard สรุปยอดใหม่ ---
              if ($("#alert-container").length) {
                $("#alert-container").load("assets/alert.php");
              }
            } else {
              Swal.fire("ผิดพลาด!", response.message, "error");
            }
          },
          error: function (xhr) {
            console.log(xhr.responseText);
            Swal.fire("Error", "ไม่สามารถติดต่อ Server ได้", "error");
          },
        });
      }
    });
  });
});
