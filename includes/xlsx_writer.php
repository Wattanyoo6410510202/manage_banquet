<?php
/**
 * เขียนไฟล์ .xlsx แบบเบา ๆ ด้วย ZipArchive (ไม่ต้องติดตั้งไลบรารีเพิ่ม)
 *
 *   $x = new XlsxWriter();
 *   $x->addSheet('ชื่อชีต', $rows, ['cols' => [8, 30], 'merges' => ['A1:D1'], 'freeze' => 'C3', 'filter' => 'A2:D10']);
 *   $x->download('report.xlsx');
 *
 * แต่ละเซลล์ใน $rows เป็นค่าเดี่ยว (string/number/null) หรือ array:
 *   ['v' => ค่า, 's' => ชื่อสไตล์, 'f' => 'สูตรไม่ต้องมี =', 'date' => true]
 * วันที่: ส่ง 'Y-m-d' พร้อม 'date' => true จะแปลงเป็นวันที่ของ Excel
 */
class XlsxWriter
{
    /** ชื่อสไตล์ → ลำดับใน cellXfs ของ styles.xml ด้านล่าง */
    const STYLES = [
        'default' => 0, 'title' => 1, 'header' => 2, 'text' => 3, 'num' => 4, 'date' => 5, 'int' => 6,
        'calc' => 7, 'total_label' => 8, 'total_num' => 9, 'date_late' => 10, 'won' => 11, 'lost' => 12,
        'calc_text' => 13, 'label' => 14, 'sub' => 15, 'pct' => 16,
    ];

    private $sheets = [];

    public function addSheet($name, array $rows, array $opt = [])
    {
        $this->sheets[] = ['name' => mb_substr(preg_replace('/[\\\\\/\?\*\[\]:]/u', ' ', $name), 0, 31), 'rows' => $rows, 'opt' => $opt];
    }

    public static function col($n)
    {
        $s = '';
        for ($n++; $n > 0; $n = intdiv($n - 1, 26)) $s = chr(65 + ($n - 1) % 26) . $s;
        return $s;
    }

    private static function esc($s)
    {
        // ตัดอักขระควบคุมที่ XML ไม่ยอมรับ
        $s = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', (string) $s);
        return htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private function sheetXml($sh)
    {
        $o = $sh['opt'];
        $x = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">';
        $x .= '<sheetViews><sheetView workbookViewId="0"' . (!empty($o['grid']) ? '' : ' showGridLines="0"') . '>';
        if (!empty($o['freeze']) && preg_match('/^([A-Z]+)(\d+)$/', $o['freeze'], $m)) {
            $cx = 0;
            foreach (str_split($m[1]) as $ch) $cx = $cx * 26 + (ord($ch) - 64);
            $xs = $cx - 1;
            $ys = (int) $m[2] - 1;
            $pane = $xs && $ys ? 'bottomRight' : ($ys ? 'bottomLeft' : 'topRight');
            $x .= '<pane' . ($xs ? ' xSplit="' . $xs . '"' : '') . ($ys ? ' ySplit="' . $ys . '"' : '')
                . ' topLeftCell="' . $o['freeze'] . '" activePane="' . $pane . '" state="frozen"/>';
        }
        $x .= '</sheetView></sheetViews><sheetFormatPr defaultRowHeight="18"/>';
        if (!empty($o['cols'])) {
            $x .= '<cols>';
            foreach ($o['cols'] as $i => $w) $x .= '<col min="' . ($i + 1) . '" max="' . ($i + 1) . '" width="' . $w . '" customWidth="1"/>';
            $x .= '</cols>';
        }
        $x .= '<sheetData>';
        foreach ($sh['rows'] as $ri => $row) {
            $r = $ri + 1;
            $ht = $o['heights'][$r] ?? null;
            $x .= '<row r="' . $r . '"' . ($ht ? ' ht="' . $ht . '" customHeight="1"' : '') . '>';
            foreach ($row as $ci => $cell) {
                if ($cell === null || $cell === '') continue;
                $c = is_array($cell) ? $cell : ['v' => $cell];
                $ref = self::col($ci) . $r;
                $s = self::STYLES[$c['s'] ?? ''] ?? 0;
                $v = $c['v'] ?? null;
                if (!empty($c['date']) && $v) {
                    // นับเป็นวันเต็มแบบ UTC ไม่ให้ timezone ทำให้วันเลื่อน
                    $ts = strtotime(substr($v, 0, 10) . ' 00:00:00 UTC');
                    $v = $ts !== false ? (int) ($ts / 86400) + 25569 : $v;
                }
                $attr = ' r="' . $ref . '"' . ($s ? ' s="' . $s . '"' : '');
                if (isset($c['f'])) {
                    $isNum = is_int($v) || is_float($v);
                    $x .= '<c' . $attr . ($isNum || $v === null ? '' : ' t="str"') . '><f>' . self::esc($c['f']) . '</f>'
                        . ($v !== null ? '<v>' . self::esc($v) . '</v>' : '') . '</c>';
                } elseif (is_int($v) || is_float($v)) {
                    $x .= '<c' . $attr . '><v>' . $v . '</v></c>';
                } elseif ($v === null) {
                    $x .= '<c' . $attr . '/>';
                } else {
                    $x .= '<c' . $attr . ' t="inlineStr"><is><t xml:space="preserve">' . self::esc($v) . '</t></is></c>';
                }
            }
            $x .= '</row>';
        }
        $x .= '</sheetData>';
        if (!empty($o['filter'])) $x .= '<autoFilter ref="' . $o['filter'] . '"/>';
        if (!empty($o['merges'])) {
            $x .= '<mergeCells count="' . count($o['merges']) . '">';
            foreach ($o['merges'] as $m) $x .= '<mergeCell ref="' . $m . '"/>';
            $x .= '</mergeCells>';
        }
        $x .= '<pageMargins left="0.4" right="0.4" top="0.5" bottom="0.5" header="0.3" footer="0.3"/>'
            . '<pageSetup paperSize="9" orientation="' . ($o['landscape'] ?? true ? 'landscape' : 'portrait') . '" fitToWidth="1" fitToHeight="0"/>';
        return $x . '</worksheet>';
    }

    private function stylesXml()
    {
        $b = '<border><left style="thin"><color rgb="FFD8D2C5"/></left><right style="thin"><color rgb="FFD8D2C5"/></right>'
            . '<top style="thin"><color rgb="FFD8D2C5"/></top><bottom style="thin"><color rgb="FFD8D2C5"/></bottom><diagonal/></border>';
        $fill = fn($rgb) => '<fill><patternFill patternType="solid"><fgColor rgb="FF' . $rgb . '"/><bgColor indexed="64"/></patternFill></fill>';
        // xf: [numFmt, font, fill, border, halign, valign, wrap]
        $xfs = [
            [0, 0, 0, 0, '', '', 0],            // default
            [0, 2, 0, 0, 'left', 'center', 0],  // title
            [0, 3, 2, 1, 'center', 'center', 1],// header
            [0, 0, 0, 1, '', 'top', 1],         // text
            [164, 0, 0, 1, '', 'top', 0],       // num
            [165, 0, 0, 1, 'center', 'top', 0], // date
            [1, 0, 0, 1, 'center', 'top', 0],   // int
            [164, 0, 6, 1, '', 'top', 0],       // calc
            [0, 1, 3, 1, '', 'center', 1],      // total_label
            [164, 1, 3, 1, '', 'center', 0],    // total_num
            [165, 4, 4, 1, 'center', 'top', 0], // date_late
            [0, 0, 5, 1, '', 'top', 1],         // won
            [0, 0, 4, 1, '', 'top', 1],         // lost
            [0, 0, 6, 1, '', 'top', 1],         // calc_text
            [0, 1, 0, 1, '', 'top', 1],         // label
            [0, 5, 0, 0, 'left', 'center', 0],  // sub
            [166, 0, 0, 1, 'center', 'top', 0], // pct
        ];
        $x = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<numFmts count="3"><numFmt numFmtId="164" formatCode="#,##0.00"/><numFmt numFmtId="165" formatCode="dd/mm/yyyy"/><numFmt numFmtId="166" formatCode="0&quot;%&quot;"/></numFmts>'
            . '<fonts count="6">'
            . '<font><sz val="10"/><name val="Tahoma"/></font>'
            . '<font><b/><sz val="10"/><name val="Tahoma"/></font>'
            . '<font><b/><sz val="14"/><color rgb="FF15181C"/><name val="Tahoma"/></font>'
            . '<font><b/><sz val="10"/><color rgb="FFFFFFFF"/><name val="Tahoma"/></font>'
            . '<font><b/><sz val="10"/><color rgb="FFB42318"/><name val="Tahoma"/></font>'
            . '<font><sz val="9"/><color rgb="FF50565F"/><name val="Tahoma"/></font>'
            . '</fonts>'
            . '<fills count="7"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill>'
            . $fill('3A3F47') . $fill('F5ECD6') . $fill('FDECEB') . $fill('E5F4EC') . $fill('F2F0EB') . '</fills>'
            . '<borders count="2"><border><left/><right/><top/><bottom/><diagonal/></border>' . $b . '</borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="' . count($xfs) . '">';
        foreach ($xfs as [$nf, $fo, $fi, $bo, $ha, $va, $wr]) {
            $al = ($ha || $va || $wr) ? '<alignment' . ($ha ? ' horizontal="' . $ha . '"' : '') . ($va ? ' vertical="' . $va . '"' : '') . ($wr ? ' wrapText="1"' : '') . '/>' : '';
            $x .= '<xf numFmtId="' . $nf . '" fontId="' . $fo . '" fillId="' . $fi . '" borderId="' . $bo . '" xfId="0"'
                . ($nf ? ' applyNumberFormat="1"' : '') . ($fo ? ' applyFont="1"' : '') . ($fi ? ' applyFill="1"' : '') . ($bo ? ' applyBorder="1"' : '')
                . ($al ? ' applyAlignment="1">' . $al . '</xf>' : '/>');
        }
        return $x . '</cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>';
    }

    public function save($path)
    {
        $z = new ZipArchive();
        if ($z->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) throw new RuntimeException('cannot create xlsx');
        $n = count($this->sheets);
        $ct = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>';
        $wb = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>';
        $rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';
        $defined = '';
        foreach ($this->sheets as $i => $sh) {
            $k = $i + 1;
            $ct .= '<Override PartName="/xl/worksheets/sheet' . $k . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
            $wb .= '<sheet name="' . self::esc($sh['name']) . '" sheetId="' . $k . '" r:id="rId' . $k . '"/>';
            $rels .= '<Relationship Id="rId' . $k . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . $k . '.xml"/>';
            if (!empty($sh['opt']['filter'])) {
                $ref = preg_replace('/([A-Z]+)(\d+)/', '\$$1\$$2', $sh['opt']['filter']);
                $defined .= '<definedName name="_xlnm._FilterDatabase" localSheetId="' . $i . '" hidden="1">\'' . self::esc($sh['name']) . '\'!' . $ref . '</definedName>';
            }
            $z->addFromString("xl/worksheets/sheet$k.xml", $this->sheetXml($sh));
        }
        $wb .= '</sheets>' . ($defined ? "<definedNames>$defined</definedNames>" : '') . '<calcPr calcId="191029" fullCalcOnLoad="1"/></workbook>';
        $rels .= '<Relationship Id="rId' . ($n + 1) . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>';
        $z->addFromString('[Content_Types].xml', $ct . '</Types>');
        $z->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
        $z->addFromString('xl/workbook.xml', $wb);
        $z->addFromString('xl/_rels/workbook.xml.rels', $rels);
        $z->addFromString('xl/styles.xml', $this->stylesXml());
        $z->close();
    }

    public function download($filename)
    {
        $tmp = tempnam(sys_get_temp_dir(), 'xlsx');
        $this->save($tmp);
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header("Content-Disposition: attachment; filename*=UTF-8''" . rawurlencode($filename));
        header('Content-Length: ' . filesize($tmp));
        header('Cache-Control: max-age=0');
        readfile($tmp);
        unlink($tmp);
    }
}
