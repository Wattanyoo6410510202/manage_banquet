<?php
/**
 * ต้นทุนครัว (อาหารหลัก + เบรก) ของงาน EO หนึ่งงาน — คำนวณอัตโนมัติจากรายการเมนู/เบรกที่บันทึกไว้
 *
 * แยกออกมาจาก print_finance_report.php เพื่อให้รายงานอื่นๆ (เช่น sales_staff_report.php)
 * ใช้กติกาต้นทุนชุดเดียวกัน ไม่ต้องคัดลอกโค้ดซ้ำแล้วเสี่ยงตัวเลขเพี้ยนไปคนละทาง
 *
 * ต้อง include menu_cost_lookup.php มาก่อนเสมอ (ใช้ menuLineCostSplit / buildSubItems)
 */
if (!function_exists('getKitchenCostDetailed')) {
    function getKitchenCostDetailed($conn, $function_id)
    {
        $main_list = [];
        $break_list = [];
        $sum_main = 0;
        $sum_main_cost = 0;
        $sum_break = 0;
        $sum_break_cost = 0;

        $sql_m = "SELECT fm.menu_detail, fm.menu_qty, fm.menu_price, fm.menu_cost,
                         mt.type_name, mc.category_name
                  FROM function_menus fm
                  LEFT JOIN master_menu_types mt ON fm.menu_set_id = mt.id
                  LEFT JOIN master_menu_categories mc ON mt.category_id = mc.id
                  WHERE fm.function_id = $function_id";
        $res_m = $conn->query($sql_m);
        while ($row = $res_m->fetch_assoc()) {
            $direct_price = (float)($row['menu_price'] ?? 0);
            $direct_cost = (float)($row['menu_cost'] ?? 0);
            $qty = (float)$row['menu_qty'];
            $lines = preg_split('/\r\n|\r|\n/', $row['menu_detail']);
            if ($direct_price > 0) {
                $names = [];
                foreach ($lines as $l) {
                    $n = trim(preg_replace('/^[0-9\.\-\s]+/', '', $l));
                    if (!empty($n)) $names[] = $n;
                }
                $unit_cost = $direct_cost;
                if ($unit_cost <= 0) {
                    foreach ($names as $n) { $unit_cost += menuLineCostSplit($conn, $n); }
                }
                if ($unit_cost <= 0) $unit_cost = $direct_price;

                $set_name = trim($row['category_name'] ?? '');
                if ($set_name === '') $set_name = trim($row['type_name'] ?? '');
                $name = $set_name !== '' ? $set_name : array_shift($names);
                $total = $direct_price * $qty;
                $cost = $unit_cost * $qty;
                $main_list[] = ['name' => $name ?: 'ค่าอาหาร', 'sub' => buildSubItems($conn, $names), 'qty' => $qty, 'price' => $direct_price, 'cost' => $unit_cost, 'total' => $total, 'cost_total' => $cost];
                $sum_main += $total;
                $sum_main_cost += $cost;
            } else {
                foreach ($lines as $l) {
                    $name = trim(preg_replace('/^[0-9\.\-\s]+/', '', $l));
                    if (empty($name)) continue;
                    $name_esc = $conn->real_escape_string($name);
                    $q_p = $conn->query("SELECT price_per_pax, cost_per_pax FROM function_menu_details WHERE menu_items LIKE '%$name_esc%' LIMIT 1");
                    $price = 0; $cost = 0;
                    if ($p = $q_p->fetch_assoc()) {
                        $price = (float)$p['price_per_pax'];
                        $cost = (float)($p['cost_per_pax'] ?? 0);
                    }
                    $total = $price * $qty;
                    $cost_total = $cost > 0 ? $cost * $qty : $total;
                    $main_list[] = ['name' => $name, 'qty' => $qty, 'price' => $price, 'cost' => $cost > 0 ? $cost : $price, 'total' => $total, 'cost_total' => $cost_total];
                    $sum_main += $total;
                    $sum_main_cost += $cost_total;
                }
            }
        }

        $sql_k = "SELECT fk.k_item, fk.k_qty, fk.k_price, fk.k_cost, bt.type_name
                  FROM function_kitchens fk
                  LEFT JOIN master_break_types bt ON fk.k_type_id = bt.id
                  WHERE fk.function_id = $function_id";
        $res_k = $conn->query($sql_k);
        while ($row = $res_k->fetch_assoc()) {
            $price = (float)($row['k_price'] ?? 0);
            $cost = (float)($row['k_cost'] ?? 0);
            $qty = (float)$row['k_qty'];
            if ($price > 0) {
                $k_names = [];
                foreach (preg_split('/\r\n|\r|\n/', $row['k_item']) as $l) {
                    $n = trim(preg_replace('/^[0-9\.\-\s]+/', '', $l));
                    if (!empty($n)) $k_names[] = $n;
                }
                $k_unit_cost = $cost;
                if ($k_unit_cost <= 0) {
                    foreach ($k_names as $n) { $k_unit_cost += menuLineCostSplit($conn, $n); }
                }
                if ($k_unit_cost <= 0) $k_unit_cost = $price;

                $k_set = trim($row['type_name'] ?? '');
                $k_name = $k_set !== '' ? $k_set : array_shift($k_names);
                $total = $price * $qty;
                $cost_total = $k_unit_cost * $qty;
                $break_list[] = ['name' => $k_name ?: 'ค่าเบรก', 'sub' => buildSubItems($conn, $k_names), 'qty' => $qty, 'price' => $price, 'cost' => $k_unit_cost, 'total' => $total, 'cost_total' => $cost_total];
                $sum_break += $total;
                $sum_break_cost += $cost_total;
            } else {
                foreach (preg_split('/\r\n|\r|\n/', $row['k_item']) as $l) {
                    $name = trim(preg_replace('/^[0-9\.\-\s]+/', '', $l));
                    if (empty($name)) continue;
                    $name_esc = $conn->real_escape_string($name);
                    $item_price = 0; $item_cost = 0;
                    $q_b = $conn->query("SELECT break_price, break_cost FROM function_breaks WHERE break_menu LIKE '%$name_esc%' LIMIT 1");
                    if ($p = $q_b->fetch_assoc()) {
                        $item_price = (float)$p['break_price'];
                        $item_cost = (float)($p['break_cost'] ?? 0);
                    } else {
                        $q_d = $conn->query("SELECT price_per_pax, cost_per_pax FROM function_menu_details WHERE menu_items LIKE '%$name_esc%' LIMIT 1");
                        if ($d = $q_d->fetch_assoc()) {
                            $item_price = (float)$d['price_per_pax'];
                            $item_cost = (float)($d['cost_per_pax'] ?? 0);
                        }
                    }
                    $total = $item_price * $qty;
                    $cost_total = $item_cost > 0 ? $item_cost * $qty : $total;
                    $break_list[] = ['name' => $name, 'qty' => $qty, 'price' => $item_price, 'cost' => $item_cost > 0 ? $item_cost : $item_price, 'total' => $total, 'cost_total' => $cost_total];
                    $sum_break += $total;
                    $sum_break_cost += $cost_total;
                }
            }
        }

        return compact('main_list', 'break_list', 'sum_main', 'sum_main_cost', 'sum_break', 'sum_break_cost');
    }
}
