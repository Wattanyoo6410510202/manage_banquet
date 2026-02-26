// assets/ai_random_fill.js

const aiMagicFill = () => {
    // --- 1. คลังข้อมูลอัจฉริยะ (แยกตามประเภทงาน) ---
    // --- 1. คลังข้อมูลอัจฉริยะ 20 หมวดหมู่ (จุกๆ ครับจาร) ---
    const themes = [
        {
            name: 'งานแต่งงานพิธีเช้า (Morning Wedding)',
            style: 'Theater Style พร้อมเวทีรดน้ำสังข์ ปูพรมแดง\nจัดวางเก้าอี้แบบเว้นระยะห่าง ผูกโบว์สีทองทุกตัว',
            hk: 'เตรียมพานพุ่ม ดอกไม้สดสีขาว-ชมพู บริเวณทางเข้า\nสายกั้นประตูเงินประตูทอง 10 คู่\nมาลัยกรสำหรับบ่าวสาว',
            equipment: 'ไมโครโฟนไร้สาย 4 ตัว, ชุดเครื่องเสียงสำหรับดนตรีสด\nจอ LED เปิด Presentation บ่าวสาว\nไฟ Follow Light บนเวที',
            remark: 'แขกส่วนใหญ่เป็นผู้ใหญ่ เน้นบริการน้ำสมุนไพรและกาแฟร้อน',
            schedules: [{ h: '07:09', f: 'พิธีสงฆ์' }, { h: '09:09', f: 'แห่ขันหมาก' }, { h: '10:30', f: 'รดน้ำสังข์' }],
            kitchen: [{ t: 'Break', i: 'ข้าวต้มหมู/ปาท่องโก๋', q: 50 }, { t: 'Lunch', i: 'โต๊ะจีน Standard', q: 100 }],
            menus: [{ n: 'Wedding A', s: 'STD', d: 'Thai Set', q: '100', p: '850' }]
        },
        {
            name: 'สัมมนาวิชาการ (Annual Seminar)',
            style: 'Classroom Style (60 PAX) พร้อมโพเดียมด้านหน้า\nทุกที่นั่งจัดสมุดโน้ตและปากกาของโรงแรม',
            hk: 'ป้ายชื่อวิทยากรตั้งโต๊ะ\nน้ำดื่มตราโรงแรมแบบขวดแก้ว 2 ขวดต่อท่าน',
            equipment: 'Projector 5000 Lumens, สาย HDMI\nพอยเตอร์สำหรับพรีเซนต์\nWiFi ความเร็วสูง',
            remark: 'ต้องการความเงียบเป็นพิเศษช่วงบรรยาย',
            schedules: [{ h: '08:30', f: 'ลงทะเบียน' }, { h: '09:00', f: 'เริ่มบรรยาย' }],
            kitchen: [{ t: 'AM Break', i: 'แซนวิชทูน่า/ชาเขียว', q: 60 }, { t: 'Lunch', i: 'Inter Buffet', q: 60 }],
            menus: [{ n: 'Seminar Full Day', s: 'Full', d: '2 Break 1 Lunch', q: '60', p: '1200' }]
        },
        {
            name: 'งานเลี้ยงฉลองวันเกิด (Birthday Party)',
            style: 'Round Table ปูผ้าสีสันสดใส มีซุ้มลูกโป่ง\nจัดที่นั่งแบบเปิดโล่งให้มีพื้นที่เต้น',
            hk: 'แจกันดอกไม้สีสดบนโต๊ะ\nเตรียมที่ฝากของขวัญด้านหน้างาน',
            equipment: 'ชุดคาราโอเกะเต็มรูปแบบ\nไฟ Moving Head สร้างบรรยากาศ\nเครื่องทำควัน (Fog Machine)',
            remark: 'แขกวัยรุ่นเยอะ เน้นเครื่องดื่ม Soft Drink และค็อกเทล',
            schedules: [{ h: '18:00', f: 'ต้อนรับแขก' }, { h: '20:00', f: 'เป่าเค้ก/จับฉลาก' }],
            kitchen: [{ t: 'Dinner', i: 'BBQ Seafood & Cocktail', q: 40 }],
            menus: [{ n: 'Party Pack', s: 'Eco', d: 'Buffet + Free flow soft drink', q: '40', p: '550' }]
        },
        {
            name: 'พิธีสวดพระอภิธรรม (Funeral Service)',
            style: 'จัดเก้าอี้แบบแถวตอนเรียงหน้ากระดาน\nปูผ้าขาวเทาบริเวณอาสนะสงฆ์',
            hk: 'จัดดอกไม้หน้าศพโทนขาว-เขียว\nน้ำดื่มใส่แก้วพร้อมหลอดบริการแขกตลอดงาน',
            equipment: 'ไมค์สายสำหรับพระสงฆ์ 4 ตัว\nลำโพงกระจายเสียงรอบพื้นที่',
            remark: 'เน้นความสงบเรียบร้อย การแต่งกายโทนสุภาพ',
            schedules: [{ h: '18:00', f: 'ต้อนรับแขก' }, { h: '19:00', f: 'เริ่มพิธีสวด' }],
            kitchen: [{ t: 'Snack Box', i: 'เบเกอรี่ 2 ชิ้น/น้ำผลไม้', q: 100 }],
            menus: [{ n: 'Funeral Box', s: 'Simple', d: 'Snack Box Set A', q: '100', p: '65' }]
        },
        {
            name: 'งานทำบุญขึ้นบ้านใหม่ (Housewarming)',
            style: 'อาสนะสำหรับพระสงฆ์ 9 รูป ปูเสื่อแดง\nโต๊ะหมู่บูชาชุดใหญ่พร้อมเครื่องพุทธบูชา',
            hk: 'สายสิญจน์รอบบริเวณงาน\nขันน้ำมนต์และที่พรมน้ำมนต์',
            equipment: 'ไมค์ไร้สาย 2 ตัว\nพัดลมไอเย็น 4 ตัว (กรณีจัดกึ่ง Outdoor)',
            remark: 'นิมนต์พระวัดใกล้เคียง 9 รูป',
            schedules: [{ h: '10:00', f: 'พิธีเจริญพระพุทธมนต์' }, { h: '11:00', f: 'ถวายภัตตาหารเพล' }],
            kitchen: [{ t: 'Lunch', i: 'อาหารไทยมงคล (ลาบ, ขนมจีน, ทองหยิบ)', q: 30 }],
            menus: [{ n: 'Monk Food Set', s: 'Premium', d: 'ภัตตาหารเพล 9 รูป + แขก 20 ท่าน', q: '30', p: '450' }]
        },
        {
            name: 'งานเปิดตัวสินค้า (Product Launch)',
            style: 'Cocktail Stand พร้อมโต๊ะบาร์สูง\nมี Runway เล็กๆ สำหรับเดินโชว์สินค้า',
            hk: 'ดอกไม้ตกแต่งแนวโมเดิร์น\nป้าย Backdrop สกรีนโลโก้สินค้าขนาดใหญ่',
            equipment: 'จอ LED Wall 3x5 เมตร\nไฟสตูดิโอส่องสินค้า\nเครื่องเสียงระดับคอนเสิร์ต',
            remark: 'มีสื่อมวลชนและ Influencer เข้าร่วมงาน',
            schedules: [{ h: '13:00', f: 'Media Registration' }, { h: '14:30', f: 'Grand Opening' }],
            kitchen: [{ t: 'Cocktail', i: 'Canapé / Wine / Mocktail', q: 150 }],
            menus: [{ n: 'Grand Open Box', s: 'Luxury', d: 'Premium Canape Set', q: '150', p: '1200' }]
        },
        {
            name: 'งานแฟนมีตติ้ง (Fan Meeting)',
            style: 'Flat Floor พร้อมโซฟานั่งคุยบนเวที\nพื้นที่ด้านหน้าเวทีสำหรับกิจกรรมเกม',
            hk: 'ซุ้มแสดง Standee ศิลปิน\nจุดรับฝากของขวัญและจดหมาย',
            equipment: 'จอ Projector 2 ข้างเวที\nระบบถ่ายทอดสดผ่าน OBS\nไฟ Follow ส่องศิลปิน',
            remark: 'แขกเน้นถ่ายรูปและวีดีโอ ต้องการปลั๊กไฟสำรอง',
            schedules: [{ h: '10:00', f: 'Hi-Touch Activity' }, { h: '13:00', f: 'Main Stage Show' }],
            kitchen: [{ t: 'LunchBox', i: 'ข้าวหน้าหมูทอด/ชานมไข่มุก', q: 200 }],
            menus: [{ n: 'Fanclub Set', s: 'Cute', d: 'Bento Box + Drink', q: '200', p: '350' }]
        },
        {
            name: 'งานเลี้ยงเกษียณอายุ (Retirement Party)',
            style: 'Round Table สลับพื้นที่จัดแสดงนิทรรศการประวัติ\nโทนสีงานเน้นสีอบอุ่น (ทอง-ขาว)',
            hk: 'ช่อดอกไม้สำหรับมอบให้ผู้เกษียณ\nของที่ระลึกจัดเตรียมบนโต๊ะ',
            equipment: 'ไมค์สายสำหรับกล่าวสปีช\nวีดีทัศน์ประวัติการทำงาน',
            remark: 'เน้นเพลงย้อนยุค บรรยากาศซึ้งๆ',
            schedules: [{ h: '11:00', f: 'ชมวิดีทัศน์' }, { h: '12:00', f: 'รับประทานอาหารร่วมกัน' }],
            kitchen: [{ t: 'Lunch', i: 'อาหารไทยโต๊ะจีนสูตรดั้งเดิม', q: 80 }],
            menus: [{ n: 'Legacy Lunch', s: 'Elegant', d: 'Thai Fusion Course', q: '80', p: '950' }]
        },
        {
            name: 'พิธีมงคลสมรสค่ำ (Wedding Reception)',
            style: 'โต๊ะจีน/บุฟเฟต์ ตกแต่งสวยงามด้วยผ้าคลุมเก้าอี้\nซุ้มเค้กแต่งงานและโต๊ะแชมเปญ',
            hk: 'พรมทางเดินบ่าวสาว (White Carpet)\nกล่องรับซองและของชำร่วย',
            equipment: 'Dry Ice ควันขาวช่วงเปิดตัว\nBubble Machine\nวงดนตรี Full Band',
            remark: 'ระวังเรื่องแอลกอฮอล์และการจอดรถของแขก',
            schedules: [{ h: '18:30', f: 'เปิดตัวบ่าวสาว' }, { h: '20:00', f: 'ตัดเค้ก/โยนดอกไม้' }],
            kitchen: [{ t: 'Dinner', i: 'Inter Buffet / Free flow Beer', q: 300 }],
            menus: [{ n: 'Grand Wedding', s: 'Diamond', d: 'Inter Buffet Set C', q: '300', p: '1500' }]
        },
        {
            name: 'สัมมนาพนักงาน (Internal Workshop)',
            style: 'U-Shape Style เพื่อการพูดคุยที่ทั่วถึง\nมีกระดาน Flipchart 4 มุม',
            hk: 'เตรียม Post-it และปากกาเคมี\nน้ำดื่มและลูกอมประจำกลุ่ม',
            equipment: 'ลำโพงบลูทูธสำหรับกิจกรรม\nสายเชื่อมต่อ Mac/Windows ครบชุด',
            remark: 'เน้นกิจกรรมกลุ่ม ไม่เน้นพิธีการ',
            schedules: [{ h: '09:00', f: 'Ice Breaking' }, { h: '14:00', f: 'Group Brainstorming' }],
            kitchen: [{ t: 'Coffee Break', i: 'ปังปิ้ง/โกโก้ร้อน', q: 30 }],
            menus: [{ n: 'Simple Workshop', s: 'Eco', d: 'Lunch Box Only', q: '30', p: '150' }]
        },
        {
            name: 'งานมิตติ้งศิษย์เก่า (Reunion Party)',
            style: 'Open Space พร้อมโต๊ะกลม\nมีบอร์ดแสดงภาพถ่ายสมัยเรียน',
            hk: 'สายคล้องคอป้ายชื่อแยกตามรุ่น\nจุดถ่ายภาพย้อนวัย',
            equipment: 'Projector ฉายรูปเก่าๆ\nคาราโอเกะเพลงยุค 90s',
            remark: 'แขกดื่มหนัก เตรียมน้ำแข็งและโซดาให้พร้อม',
            schedules: [{ h: '18:00', f: 'ลงทะเบียนรุ่น' }, { h: '19:30', f: 'กิจกรรมบนเวที' }],
            kitchen: [{ t: 'Dinner', i: 'อาหารไทยรสจัดจ้าน / กับแกล้ม', q: 100 }],
            menus: [{ n: 'Reunion Buffet', s: 'Fun', d: 'Thai Buffet + Soda Free', q: '100', p: '650' }]
        },
        {
            name: 'พิธีประสาทปริญญาจำลอง (Mock Graduation)',
            style: 'Theater Style จัดเก้าอี้เว้นทางเดินกลาง\nมีแท่นรับมอบประกาศนียบัตร',
            hk: 'ซุ้มดอกไม้รับปริญญา\nพรมแดงปูยาวถึงเวที',
            equipment: 'เพลงมาร์ชสถาบัน\nไฟสปอร์ตไลท์ส่องบัณฑิต',
            remark: 'คุมเวลาให้แม่นยำช่วงเดินขึ้นเวที',
            schedules: [{ h: '08:00', f: 'ซ้อมเดิน' }, { h: '10:00', f: 'พิธีมอบจริง' }],
            kitchen: [{ t: 'Snack', i: 'แฮมเบอร์เกอร์/น้ำอัดลม', q: 50 }],
            menus: [{ n: 'Graduation Set', s: 'STD', d: 'Easy Snack Set', q: '50', p: '200' }]
        },
        {
            name: 'งานเลี้ยงปิดกล้องละคร (Wrap Party)',
            style: 'จัดแบบกันเอง (Chill Out) มีบีนแบค (Bean Bag)\nตกแต่งด้วยอุปกรณ์ประกอบฉาก',
            hk: 'เตรียมผ้าเย็นจำนวนมาก\nมุมนวดคลายเครียดให้ทีมงาน',
            equipment: 'ไมค์พูดความในใจ\nจอเปิดเบื้องหลังหลุดๆ (Behind the scene)',
            remark: 'บรรยากาศสบายๆ ไม่เป็นทางการ',
            schedules: [{ h: '19:00', f: 'เริ่มงานเลี้ยง' }, { h: '22:00', f: 'Lucky Draw' }],
            kitchen: [{ t: 'Dinner', i: 'หมูกระทะ/ชาบู Premium', q: 70 }],
            menus: [{ n: 'Wrap Party Pack', s: 'Special', d: 'Premium Grill Buffet', q: '70', p: '800' }]
        },
        {
            name: 'งานแถลงข่าวข่าวบันเทิง (Press Conference)',
            style: 'Theater สำหรับสื่อมวลชน และแท่นยืนด้านหลัง\nมีจุด Drop ไมค์เสียงรวม',
            hk: 'ป้ายชื่องานขนาดใหญ่ (Main Backdrop)\nจัดเตรียมเครื่องดื่มรองรับนักข่าว',
            equipment: 'ระบบเสียง Direct Out สำหรับสื่อ\nไฟ Studio สว่างทั่วเวที',
            remark: 'ต้องการความเป๊ะของแสงและเสียงมาก',
            schedules: [{ h: '13:00', f: 'ลงทะเบียนสื่อ' }, { h: '14:00', f: 'เริ่มแถลงข่าว' }],
            kitchen: [{ t: 'Afternoon Tea', i: 'ครัวซองต์/กาแฟดริป', q: 40 }],
            menus: [{ n: 'Press Pack', s: 'High', d: 'Tea & Coffee Set', q: '40', p: '350' }]
        },
        {
            name: 'งานบวชนาค (Ordination Ceremony)',
            style: 'อาสนะสงฆ์ 9 รูป ทรงเตี้ย\nพื้นที่สำหรับทำขวัญนาค ปูพรมเขียว',
            hk: 'เครื่องอัฐบริขาร พุ่มสลวย\nดอกไม้ธูปเทียนแพ',
            equipment: 'เครื่องขยายเสียงสำหรับหมอทำขวัญ\nพัดลมรอบเต็นท์ (กรณีจัดกลางแจ้ง)',
            remark: 'เน้นความเป็นไทยและพิธีกรรม',
            schedules: [{ h: '08:00', f: 'พิธีทำขวัญนาค' }, { h: '13:00', f: 'แห่นาคเข้าโบสถ์' }],
            kitchen: [{ t: 'Lunch', i: 'แกงเขียวหวาน/ขนมจีน/น้ำพริก', q: 120 }],
            menus: [{ n: 'Naka Buffet', s: 'Thai', d: 'Authentic Thai Buffet', q: '120', p: '400' }]
        },
        {
            name: 'งานแข่งขัน E-Sports (Gaming Tournament)',
            style: 'โต๊ะยาวสำหรับวางคอมพิวเตอร์/คอนโซล\nเก้าอี้ Gaming (ถ้ามี) หรือเก้าอี้นวม',
            hk: 'ป้ายชื่อทีมแข่งติดหน้าโต๊ะ\nปลั๊กพ่วงและระบบสายแลน (LAN)',
            equipment: 'Internet Fiber ความเร็วสูง\nจอทีวีขนาดใหญ่ 4-6 จอ\nระบบไฟ RGB รอบงาน',
            remark: 'ใช้กระแสไฟสูงมากเป็นพิเศษ',
            schedules: [{ h: '09:00', f: 'Check-in ทีม' }, { h: '15:00', f: 'รอบชิงชนะเลิศ' }],
            kitchen: [{ t: 'Energy Break', i: 'เครื่องดื่มชูกำลัง/พิซซ่า', q: 30 }],
            menus: [{ n: 'Gamer Pack', s: 'Fast', d: 'Pizza & Cola Set', q: '30', p: '300' }]
        },
        {
            name: 'งานสวดอภิธรรมศพผู้ใหญ่ (V.I.P Funeral)',
            style: 'จัดเก้าอี้แบบรัดกุม ปูพรมน้ำเงิน\nโต๊ะหมู่บูชาประดับมุก',
            hk: 'พวงหรีดจากหน่วยงานต่างๆ จัดเรียงสวยงาม\nจัดเตรียมผ้าเช็ดหน้ามอบเป็นของที่ระลึก',
            equipment: 'เครื่องเสียงแบบซ่อนลำโพง\nไมค์สีดำสุภาพ',
            remark: 'มีแขกระดับผู้ใหญ่เยอะ ต้องการการต้อนรับพิเศษ',
            schedules: [{ h: '18:30', f: 'รับแขกผู้ใหญ่' }, { h: '19:00', f: 'พิธีสวดอภิธรรม' }],
            kitchen: [{ t: 'Snack', i: 'เบเกอรี่เกรดพรีเมียม/น้ำสมุนไพร', q: 150 }],
            menus: [{ n: 'VIP Snack Box', s: 'Top', d: 'Premium Bakery Box', q: '150', p: '95' }]
        },
        {
            name: 'งานเลี้ยงปีใหม่บริษัท (Company New Year)',
            style: 'Round Table พร้อมเวทีการแสดงขนาดใหญ่\nมีจุดลงคะแนน Lucky Draw',
            hk: 'สายรุ้งและลูกโป่งธีมคริสต์มาส/ปีใหม่\nต้นคริสต์มาสประดับไฟที่ทางเข้า',
            equipment: 'ระบบไฟเทค แสง สี เสียง\nไมค์สำหรับพิธีกร 2 คู่',
            remark: 'เน้นความสนุกสนานและการจับรางวัล',
            schedules: [{ h: '19:00', f: 'Dinner Start' }, { h: '21:00', f: 'Lucky Draw Phase 1' }],
            kitchen: [{ t: 'Grand Buffet', i: 'บุฟเฟต์นานาชาติ / เบียร์ถัง', q: 250 }],
            menus: [{ n: 'New Year Grand', s: 'Max', d: 'International Buffet Set D', q: '250', p: '1800' }]
        },
        {
            name: 'งานเสวนาทิศทางเศรษฐกิจ (Economic Forum)',
            style: 'Talk Show Style (Sofa บนเวที)\nที่นั่งแขกเป็น Theater แต่มีโต๊ะเลคเชอร์',
            hk: 'ดอกไม้สดจัดใส่แจกันหรูหรา\nน้ำดื่ม Evian สำหรับวิทยากรบนเวที',
            equipment: 'ระบบแปลภาษา (Simultaneous Interpret)\nจอ LED สรุปกราฟสถิติ',
            remark: 'มีการบันทึกวีดีโอเพื่อนำไปลง Youtube',
            schedules: [{ h: '09:00', f: 'Keynote Speech' }, { h: '10:30', f: 'Panel Discussion' }],
            kitchen: [{ t: 'AM Break', i: 'ครัวซองต์เนยสด/กาแฟดำ', q: 100 }],
            menus: [{ n: 'Executive Set', s: 'High', d: 'Coffee Break + Premium Lunch', q: '100', p: '2200' }]
        },
        {
            name: 'งานเปิดตัวภาพยนตร์ (Movie Premiere)',
            style: 'Red Carpet ทางเดินยาว\nมีพื้นที่กำแพงภาพถ่าย (Photo Wall)',
            hk: 'Standee ดารานักแสดง\nไฟ Spotlight ส่องพรมแดง',
            equipment: 'เครื่องฉาย Laser Projector\nระบบเสียง Surround 7.1',
            remark: 'เตรียมพื้นที่สำหรับแฟนคลับด้านนอก',
            schedules: [{ h: '18:00', f: 'Red Carpet Walk' }, { h: '19:30', f: 'Movie Screening' }],
            kitchen: [{ t: 'Snack', i: 'ป๊อปคอร์น Premium/Hotdog', q: 200 }],
            menus: [{ n: 'Cinema Pack', s: 'Fun', d: 'Fast Food Cinema Style', q: '200', p: '450' }]
        }
    ];

    const customers = ['คุณอัครพล สุขสวัสดิ์', 'ดร.สมศักดิ์ รักไทย', 'คุณเจนนิเฟอร์ คิม', 'รศ.นภา พรรณนา'];
    const rooms = ['ห้องแกรนด์บอลรูม', 'ห้องราชพฤกษ์ ชั้น 2', 'ห้องจามจุรี', 'ห้องประชุมสุพรรณิการ์'];
    const organizations = ['บริษัท เอบีซี เทคโนโลยี จำกัด', 'กรมการปกครอง', 'มหาวิทยาลัยเกษตรศาสตร์', 'ธนาคารแห่งประเทศไทย'];

    const rand = (arr) => arr[Math.floor(Math.random() * arr.length)];
    const randInt = (min, max) => Math.floor(Math.random() * (max - min + 1) + min);

    const selectedTheme = rand(themes);
    const today = new Date();
    const eventDate = new Date(today.setDate(today.getDate() + randInt(7, 30))).toISOString().split('T')[0];

    // --- 2. ฟังก์ชันช่วยจัดการตาราง (สร้างแถวให้ครบตาม Data) ---
    const fillDynamicRows = (tableId, addFunc, dataArr, mappingFunc) => {
        const tbody = document.querySelector(`#${tableId} tbody`);
        if (!tbody) return;
        while (tbody.rows.length > 0) tbody.deleteRow(0); // ล้างแถวเก่า

        dataArr.forEach(item => {
            addFunc(); // เรียกฟังก์ชันเดิมที่จารมีในไฟล์ PHP
            const rows = tbody.querySelectorAll('tr');
            mappingFunc(rows[rows.length - 1], item);
        });
    };

    // --- 3. ลงมือกรอกข้อมูล (Fill Every Section) ---

    // 3.1 ข้อมูลส่วนตัวและรหัสงาน
    document.querySelector('input[name="function_code"]').value = 'F-' + randInt(10000, 99999);
    document.querySelector('input[name="function_name"]').value = selectedTheme.name;
    document.querySelector('input[name="booking_name"]').value = rand(customers);
    document.querySelector('input[name="organization"]').value = rand(organizations);
    document.querySelector('input[name="phone"]').value = '08' + randInt(11111111, 99999999);
    document.querySelector('input[name="room_name"]').value = rand(rooms);
    document.querySelector('input[name="booking_room"]').value = 'R-' + randInt(100, 999);
    document.querySelector('input[name="deposit"]').value = randInt(5, 50) * 1000;

    // 3.2 สุ่มเลือกบริษัทและเปลี่ยนโลโก้
    const companySelect = document.querySelector('select[name="company_id"]');
    if (companySelect && companySelect.options.length > 1) {
        companySelect.selectedIndex = randInt(1, companySelect.options.length - 1);
        updateCompanyLogo(companySelect); // เรียกฟังก์ชันเดิมของจาร
    }

    // 3.3 ตาราง Schedule
    fillDynamicRows('scheduleTable', addScheduleRow, selectedTheme.schedules, (row, data) => {
        row.querySelector('input[name="schedule_date[]"]').value = eventDate;
        row.querySelector('input[name="schedule_hour[]"]').value = data.h;
        row.querySelector('textarea[name="schedule_function[]"]').value = data.f;
        row.querySelector('input[name="schedule_guarantee[]"]').value = randInt(40, 100);
    });

    // 3.4 ตาราง Kitchen
    fillDynamicRows('kitchenTable', addKitchenRow, selectedTheme.kitchen, (row, data) => {
        row.querySelector('input[name="k_date[]"]').value = eventDate;
        row.querySelector('input[name="k_type[]"]').value = data.t;
        row.querySelector('textarea[name="k_item[]"]').value = data.i;
        row.querySelector('input[name="k_qty[]"]').value = data.q;
    });
    document.querySelector('textarea[name="main_kitchen_remark"]').value = "เน้นอาหารรสชาติกลางๆ ไม่เผ็ดมาก";

    // 3.5 Setup & Technical
    document.querySelector('textarea[name="banquet_style"]').value = selectedTheme.style;
    document.querySelector('textarea[name="equipment"]').value = selectedTheme.equipment;
    document.querySelector('textarea[name="remark"]').value = selectedTheme.remark;

    // 3.6 ตาราง Menu F&B
    fillDynamicRows('menuTable', addMenuRow, selectedTheme.menus, (row, data) => {
        row.querySelector('input[name="menu_time[]"]').value = eventDate;
        row.querySelector('input[name="menu_name[]"]').value = data.n;
        row.querySelector('input[name="menu_set[]"]').value = data.s;
        row.querySelector('textarea[name="menu_detail[]"]').value = data.d;
        row.querySelector('input[name="menu_qty[]"]').value = data.q;
        row.querySelector('input[name="menu_price[]"]').value = data.p;
    });

    // 3.7 Decoration & HK
    document.querySelector('textarea[name="backdrop_detail"]').value = "Backdrop อักษรโฟม: " + selectedTheme.name + "\nธีมสี: " + rand(['ครีม-ทอง', 'ฟ้า-ขาว', 'ชมพู-พาสเทล']);
    document.querySelector('textarea[name="hk_florist_detail"]').value = selectedTheme.hk;

    // --- 3.8 สุ่มรูปภาพ Backdrop (เวอร์ชั่นส่งค่าไปบันทึก) ---
    const previewImg = document.getElementById('imagePreview');
    const previewContainer = document.getElementById('imagePreviewContainer');
    const aiPathInput = document.getElementById('backdrop_img_path_ai'); // ช่องลับที่เพิ่มใหม่

    if (previewImg) {
        // สุ่มรูป (จารอาจจะหา URL รูปงานแต่งสวยๆ มาใส่แทนได้ครับ)
        const randomUrl = `https://picsum.photos/seed/${Math.random()}/800/600`;

        previewImg.src = randomUrl;

        // ใส่ค่าลงในช่องลับ เพื่อให้ PHP รู้ว่า "ถ้าไม่ได้อัปโหลดไฟล์ ให้ใช้รูปนี้แทน"
        if (aiPathInput) {
            aiPathInput.value = randomUrl;
        }

        previewContainer.classList.remove('d-none');
    }

    // --- เอฟเฟกต์ที่ปุ่ม ---
    const btn = document.getElementById('aiMagicFill');
    btn.innerHTML = '<i class="bi bi-check-all me-2"></i> กรอกให้ครบแล้วจาร!';
    btn.classList.replace('btn-warning', 'btn-success');
    setTimeout(() => {
        btn.innerHTML = '<i class="bi bi-robot me-2"></i> AI สุ่มให้ครับจาร!';
        btn.classList.replace('btn-success', 'btn-warning');
    }, 2000);
};

// ผูก Event เข้ากับปุ่ม
document.addEventListener('DOMContentLoaded', () => {
    const btn = document.getElementById('aiMagicFill');
    if (btn) btn.addEventListener('click', aiMagicFill);
});