</div> </div> </div> 
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<!-- Select2 JS -->
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<!-- SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<!-- Driver.js for Tutorial -->
<script src="https://cdn.jsdelivr.net/npm/driver.js@1.0.1/dist/driver.js.iife.js"></script>
<script>
    // --- Select2 Global Initializations ---
    $(document).ready(function() {
        // สำหรับ Select2 ทั่วไป
        $('.select2').select2({
            width: '100%',
            placeholder: '--- เลือกรายการ ---',
            allowClear: true
        });
    });

    // สำหรับ Toggle Sidebar บนมือถือ
    document.getElementById('sidebarCollapse').addEventListener('click', function () {
        const sidebar = document.getElementById('sidebar');
        const content = document.getElementById('content');
        
        if (window.innerWidth <= 991) {
            sidebar.classList.toggle('active');
        } else {
            sidebar.classList.toggle('collapsed');
            content.classList.toggle('expanded');
        }
    });

    // --- Tutorial Logic ---
    const driver = window.driver.js.driver;
    const startBtn = document.getElementById('startTutorial');

    if (startBtn) {
        startBtn.addEventListener('click', () => {
            const currentPage = window.location.pathname.split("/").pop();
            let steps = [];

            // Common steps (Sidebar)
            steps.push({
                element: '#sidebar',
                popover: {
                    title: 'เมนูหลัก',
                    description: 'คุณสามารถเข้าถึงส่วนต่างๆ ของระบบได้จากแถบเมนูด้านข้างนี้',
                    side: "right",
                    align: 'start'
                }
            });

            // Page specific steps
            if (currentPage === 'dashboard.php' || currentPage === '') {
                steps.push(
                    {
                        element: '#companyFilter',
                        popover: {
                            title: 'ตัวกรองโรงแรม',
                            description: 'เลือกโรงแรมที่ต้องการดูข้อมูลภาพรวม',
                            side: "bottom",
                            align: 'start'
                        }
                    },
                    {
                        element: '.row.g-4.mb-4', // Stats cards
                        popover: {
                            title: 'สถิติภาพรวม',
                            description: 'แสดงจำนวนงานทั้งหมด, งานที่รออนุมัติ และยอดเงินมัดจำรวม',
                            side: "bottom",
                            align: 'start'
                        }
                    },
                    {
                        element: '#revenueChart',
                        popover: {
                            title: 'กราฟรายได้',
                            description: 'แสดงแนวโน้มรายได้ย้อนหลัง 6 เดือน',
                            side: "top",
                            align: 'start'
                        }
                    },
                    {
                        element: '.card:has(#roomDisplayBody)', // Room status table
                        popover: {
                            title: 'สถานะห้องประชุม',
                            description: 'ตรวจสอบว่าห้องไหนว่างหรือมีงานจัดเลี้ยงอยู่ในขณะนี้',
                            side: "top",
                            align: 'start'
                        }
                    }
                );
            } else if (currentPage === 'manage_banquet.php') {
                steps.push(
                    {
                        element: 'a[href="add_event.php"]',
                        popover: {
                            title: 'เพิ่มงานจัดเลี้ยง',
                            description: 'คลิกที่นี่เพื่อเริ่มสร้างรายการจองห้องประชุมหรืองานจัดเลี้ยงใหม่',
                            side: "bottom",
                            align: 'start'
                        }
                    },
                    {
                        element: '.dataTables_filter input',
                        popover: {
                            title: 'ค้นหาข้อมูล',
                            description: 'คุณสามารถค้นหาชื่องาน, ชื่อลูกค้า หรือเลขที่งานได้จากช่องนี้',
                            side: "bottom",
                            align: 'start'
                        }
                    },
                    {
                        element: '#banquetTable',
                        popover: {
                            title: 'ตารางรายการงาน',
                            description: 'แสดงรายการงานจัดเลี้ยงทั้งหมด คุณสามารถคลิกที่ชื่องานเพื่อดูรายละเอียด หรือใช้ปุ่มจัดการด้านขวา',
                            side: "top",
                            align: 'start'
                        }
                    }
                );
            } else if (currentPage === 'calendar.php') {
                steps.push(
                    {
                        element: '#calendar',
                        popover: {
                            title: 'ปฏิทินงาน',
                            description: 'แสดงตารางงานในรูปแบบปฏิทิน คุณสามารถดูภาพรวมรายเดือนหรือรายสัปดาห์ได้',
                            side: "top",
                            align: 'start'
                        }
                    }
                );
            } else if (currentPage === 'quotation_list.php') {
                steps.push(
                    {
                        element: 'a[href="add_quote.php"]',
                        popover: {
                            title: 'สร้างใบเสนอราคา',
                            description: 'ออกใบเสนอราคาใหม่ให้ลูกค้าได้ที่นี่',
                            side: "bottom",
                            align: 'start'
                        }
                    }
                );
            } else if (currentPage === 'add_event.php' || currentPage === 'edit.php') {
                steps.push(
                    {
                        element: '#company_select',
                        popover: {
                            title: 'เลือกโรงแรม',
                            description: 'เลือกโรงแรมที่เป็นเจ้าของสถานที่จัดงาน',
                            side: "bottom",
                            align: 'start'
                        }
                    },
                    {
                        element: '#customer_selector',
                        popover: {
                            title: 'ข้อมูลลูกค้า',
                            description: 'เลือกจากรายชื่อลูกค้าเดิม หรือพิมพ์ข้อมูลลูกค้าใหม่ได้ทันที',
                            side: "bottom",
                            align: 'start'
                        }
                    },
                    {
                        element: '#roomContainer',
                        popover: {
                            title: 'เลือกสถานที่',
                            description: 'คลิกเลือกห้องประชุมที่ต้องการใช้งาน',
                            side: "top",
                            align: 'start'
                        }
                    },
                    {
                        element: 'button[name="save"]',
                        popover: {
                            title: 'บันทึกข้อมูล',
                            description: 'เมื่อกรอกรายละเอียดครบแล้ว อย่าลืมกดบันทึกที่ปุ่มนี้',
                            side: "bottom",
                            align: 'end'
                        }
                    }
                );
            } else if (currentPage === 'customer.php') {
                steps.push(
                    {
                        element: '#custForm',
                        popover: {
                            title: 'เพิ่ม/แก้ไขลูกค้า',
                            description: 'กรอกรายละเอียดลูกค้าหรือบริษัทได้จากฟอร์มนี้',
                            side: "right",
                            align: 'start'
                        }
                    },
                    {
                        element: '#customerTable',
                        popover: {
                            title: 'รายชื่อลูกค้า',
                            description: 'แสดงรายชื่อลูกค้าทั้งหมด คุณสามารถดูประวัติ แก้ไข หรือลบข้อมูลได้',
                            side: "top",
                            align: 'start'
                        }
                    }
                );
            } else if (currentPage === 'banquet_mt.php' || currentPage === 'banquet_bk.php' || currentPage === 'banquet_hk.php') {
                steps.push(
                    {
                        element: '.card:has(table)',
                        popover: {
                            title: 'รายการงานแผนก',
                            description: 'ดูรายการงานที่แผนกของคุณต้องรับผิดชอบในแต่ละวัน',
                            side: "top",
                            align: 'start'
                        }
                    }
                );
            } else if (currentPage.includes('checklist')) {
                steps.push(
                    {
                        element: '.card:has(table)',
                        popover: {
                            title: 'จัดการรายการตรวจสอบ',
                            description: 'เพิ่มหรือแก้ไขรายการ Checklist เพื่อใช้ในการตรวจสอบความเรียบร้อยของงาน',
                            side: "top",
                            align: 'start'
                        }
                    }
                );
            } else if (currentPage === 'finance.php') {
                steps.push(
                    {
                        element: '#summaryWrapper',
                        popover: {
                            title: 'สรุปการเงิน',
                            description: 'แสดงราคาขาย ต้นทุน กำไร และค่า ROI ของงานนี้',
                            side: "bottom",
                            align: 'start'
                        }
                    },
                    {
                        element: '#financeForm',
                        popover: {
                            title: 'บันทึกรายการ',
                            description: 'เพิ่มรายการรายรับหรือรายจ่ายเพิ่มเติมของงานนี้ได้ที่นี่',
                            side: "right",
                            align: 'start'
                        }
                    },
                    {
                        element: '#financeTableContainer',
                        popover: {
                            title: 'ตารางรายการบัญชี',
                            description: 'แสดงรายการทั้งหมดที่บันทึกไว้ คุณสามารถลบรายการได้จากที่นี่',
                            side: "top",
                            align: 'start'
                        }
                    }
                );
            } else if (currentPage === 'view.php') {
                steps.push(
                    {
                        element: '.no-print',
                        popover: {
                            title: 'เมนูจัดการเอกสาร',
                            description: 'คุณสามารถพิมพ์งาน, ออกลายเซ็น หรือดาวน์โหลดเป็น PDF/Word ได้จากแถบนี้',
                            side: "left",
                            align: 'start'
                        }
                    },
                    {
                        element: '#printableArea',
                        popover: {
                            title: 'ตัวอย่างเอกสาร',
                            description: 'แสดงข้อมูลทั้งหมดของงานจัดเลี้ยงที่จะปรากฏบนเอกสารจริง',
                            side: "top",
                            align: 'start'
                        }
                    }
                );
            } else if (currentPage === 'signature_page.php') {
                steps.push(
                    {
                        element: '.card:has(canvas)',
                        popover: {
                            title: 'เซ็นชื่อ',
                            description: 'คุณสามารถเซ็นชื่อลงในช่องนี้เพื่อใช้แนบในเอกสาร',
                            side: "top",
                            align: 'start'
                        }
                    }
                );
            } else if (currentPage === 'setting.php' || currentPage === 'setting_room.php' || currentPage === 'setting_type.php') {
                steps.push(
                    {
                        element: '.card:has(form)',
                        popover: {
                            title: 'การตั้งค่าระบบ',
                            description: 'จัดการข้อมูลพื้นฐานของระบบ เช่น ห้องประชุม ประเภทงาน หรือข้อมูลโรงแรม',
                            side: "top",
                            align: 'start'
                        }
                    }
                );
            }

            const driverObj = driver({
                showProgress: true,
                nextBtnText: 'ถัดไป',
                prevBtnText: 'ก่อนหน้า',
                doneBtnText: 'เสร็จสิ้น',
                steps: steps
            });

            driverObj.drive();
        });
    }
</script>
</body>
</html>