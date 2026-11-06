<?php
// includes/lead_api.php
// เรียก API ของระบบ Lead Management (อ่านอย่างเดียว) และเทียบลูกค้ากับตาราง customers
// key ต้องอยู่ฝั่งเซิร์ฟเวอร์เท่านั้น: define('LEAD_API_KEY', '...') ใน config.php (ไฟล์นี้ไม่ขึ้น Git)

define('LEAD_API_BASE', 'https://nas909ssf.myqnapcloud.com:8081/lead_management/public/api/v1');

/**
 * คืน ['data' => ..., 'meta' => ...] เมื่อสำเร็จ หรือ ['error' => ข้อความภาษาไทย] เมื่อไม่สำเร็จ
 */
function leadApiGet(string $path): array
{
    if (!defined('LEAD_API_KEY') || LEAD_API_KEY === '') {
        return ['error' => 'ยังไม่ได้ตั้งค่า LEAD_API_KEY ใน config.php'];
    }

    $ch = curl_init(LEAD_API_BASE . $path);
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER => [
            // NAS ตัด header Authorization ทิ้งก่อนถึง PHP จึงต้องใช้ X-Api-Key
            'X-Api-Key: ' . LEAD_API_KEY,
            'Accept: application/json',
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 15,
    ]);
    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($result === false) {
        return ['error' => 'เชื่อมต่อ Lead Management ไม่สำเร็จ: ' . $curlError];
    }

    $body = json_decode($result, true);
    if ($httpCode !== 200 || !is_array($body)) {
        $msg = $body['error']['message'] ?? "HTTP {$httpCode}";
        return ['error' => 'Lead Management ตอบกลับผิดพลาด: ' . $msg];
    }

    return ['data' => $body['data'] ?? [], 'meta' => $body['meta'] ?? []];
}

function normalizePhone(string $phone): string
{
    $digits = preg_replace('/\D/', '', $phone);
    if (strlen($digits) === 11 && str_starts_with($digits, '66')) {
        $digits = '0' . substr($digits, 2);
    }
    return strlen($digits) >= 9 ? $digits : '';
}

function normalizeText(?string $text): string
{
    return mb_strtolower(trim(preg_replace('/\s+/u', ' ', (string) $text)));
}

/**
 * เทียบกับตาราง customers ตามลำดับความแม่น: เบอร์โทร > อีเมล > ชื่อ
 * in_customers: true/false, หรือ null เมื่อ key ไม่มีสิทธิ์ customers.contact จึงไม่มีข้อมูลให้เทียบ
 * ถ้าตรงหลายราย matched_customer_id จะเป็นรายที่เก่าที่สุด
 */
function markExistingCustomers(mysqli $conn, array $rows): array
{
    $phones = $emails = $names = [];
    $result = $conn->query("SELECT id, cust_name, cust_phone, cust_email FROM customers ORDER BY id ASC");
    while ($local = $result->fetch_assoc()) {
        $id = (int) $local['id'];
        // ช่องเบอร์โทรในระบบอาจมีหลายเบอร์
        foreach (preg_split('/[,\/;]/', (string) $local['cust_phone']) as $phone) {
            $phones[normalizePhone($phone)] ??= $id;
        }
        $emails[normalizeText($local['cust_email'])] ??= $id;
        $names[normalizeText($local['cust_name'])] ??= $id;
    }
    unset($phones[''], $emails[''], $names['']);

    foreach ($rows as &$row) {
        if (!array_key_exists('phone', $row)) {
            $row['in_customers'] = null;
            continue;
        }
        $matchedBy = null;
        $matchedId = null;
        $phoneKey = normalizePhone((string) $row['phone']);
        $emailKey = normalizeText($row['email']);
        $orgKey = normalizeText($row['organization_name']);
        $nameKey = normalizeText($row['display_name']);
        if (isset($phones[$phoneKey])) {
            [$matchedBy, $matchedId] = ['เบอร์โทร', $phones[$phoneKey]];
        } elseif (isset($emails[$emailKey])) {
            [$matchedBy, $matchedId] = ['อีเมล', $emails[$emailKey]];
        } elseif (isset($names[$orgKey])) {
            [$matchedBy, $matchedId] = ['ชื่อ', $names[$orgKey]];
        } elseif (isset($names[$nameKey])) {
            [$matchedBy, $matchedId] = ['ชื่อ', $names[$nameKey]];
        }
        $row['in_customers'] = $matchedBy !== null;
        $row['matched_by'] = $matchedBy;
        $row['matched_customer_id'] = $matchedId;
    }
    unset($row);
    return $rows;
}
