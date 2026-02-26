$(document).ready(function () {
  function executeDelete(ids, rows) {
    Swal.fire({
      title: "ยืนยันการลบ?",
      text: "จารจะลบรายการที่เลือกจริงใช่ไหม? ข้อมูลหายเกลี้ยงเลยนะ!",
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

  $(document).on("click", ".btn-approve-row", function () {
    const id = $(this).data("id");
    const row = $(this).closest("tr");
    const button = $(this);

    Swal.fire({
      title: "ยืนยันการอนุมัติ?",
      text: "คุณต้องการอนุมัติรายการนี้ใช่หรือไม่?",
      icon: "question",
      showCancelButton: true,
      confirmButtonColor: "#198754",
      cancelButtonColor: "#6c757d",
      confirmButtonText: "ตกลง, อนุมัติเลย!",
      cancelButtonText: "ยกเลิก",
    }).then((result) => {
      if (result.isConfirmed) {
        $.ajax({
          url: "approve_event.php",
          type: "POST",
          data: { id: id },
          dataType: "json",
          success: function (response) {
            if (response.status === "success") {
              // 1. ทำให้แถวเปลี่ยนสถานะทันที (ความเนียน)
              row
                .find("td")
                .eq(6)
                .find(".badge")
                .removeClass("bg-warning-subtle text-warning")
                .addClass("bg-success-subtle text-success")
                .html('<i class="bi bi-check-circle me-1"></i> อนุมัติแล้ว');

              button.remove();

              // 2. ดึงเอาหน้า alert.php มาแสดงใหม่ใน #alert-container โดยไม่ต้องรีโหลดทั้งหน้า
              $("#alert-container").load("assets/alert.php");

              // หรือถ้าต้องการให้ชัวร์ว่า Session ทำงานครบถ้วน แนะนำให้ใช้:
              // location.reload();
            } else {
              Swal.fire("ผิดพลาด!", response.message, "error");
            }
          },
        });
      }
    });
  });
});
