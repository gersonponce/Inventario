<?php
namespace App\Support;

use RuntimeException;
use ZipArchive;

// Generador mínimo de .xlsx (una hoja) sin dependencias: ZipArchive + XML a mano.
// Columnas: ['titulo' => string, 'tipo' => texto|numero|entero|decimal|fecha, 'ancho' => float]
// Filas: arrays de valores en el mismo orden; 'fecha' recibe 'Y-m-d H:i:s'.
final class Xlsx
{
    // Índices de estilo en styles.xml (cellXfs)
    private const ESTILOS = ['texto' => 0, 'encabezado' => 1, 'decimal' => 2, 'entero' => 3, 'fecha' => 4, 'numero' => 5];

    public static function generar(string $hoja, array $columnas, array $filas): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'xlsx');
        $zip = new ZipArchive();
        if ($zip->open($tmp, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('No se pudo crear el archivo Excel.');
        }
        $ultima = self::columna(count($columnas) - 1) . (count($filas) + 1);
        $hoja = self::xml($hoja); // Excel admite hasta 31 caracteres; quien llama pasa un nombre corto

        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . '</Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>');
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets><sheet name="' . $hoja . '" sheetId="1" r:id="rId1"/></sheets>'
            . '<definedNames><definedName name="_xlnm._FilterDatabase" localSheetId="0" hidden="1">\'' . $hoja . '\'!$A$1:$'
            . preg_replace('/(\d+)$/', '\$$1', $ultima) . '</definedName></definedNames>'
            . '</workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . '</Relationships>');
        $zip->addFromString('xl/styles.xml', self::estilos());
        $zip->addFromString('xl/worksheets/sheet1.xml', self::hoja($columnas, $filas, $ultima));
        $zip->close();

        $contenido = file_get_contents($tmp);
        unlink($tmp);
        return $contenido;
    }

    private static function hoja(array $columnas, array $filas, string $ultima): string
    {
        $cols = '';
        foreach ($columnas as $i => $c) {
            $n = $i + 1;
            $cols .= '<col min="' . $n . '" max="' . $n . '" width="' . ($c['ancho'] ?? 14) . '" customWidth="1"/>';
        }

        $xml = '<row r="1">';
        foreach ($columnas as $i => $c) {
            $xml .= self::celdaTexto(self::columna($i) . '1', $c['titulo'], self::ESTILOS['encabezado']);
        }
        $xml .= '</row>';

        foreach ($filas as $f => $fila) {
            $r = $f + 2;
            $xml .= '<row r="' . $r . '">';
            foreach ($columnas as $i => $c) {
                $ref = self::columna($i) . $r;
                $valor = $fila[$i] ?? null;
                $tipo = $c['tipo'] ?? 'texto';
                if ($valor === null || $valor === '') {
                    continue;
                }
                if ($tipo === 'texto') {
                    $xml .= self::celdaTexto($ref, (string)$valor, self::ESTILOS['texto']);
                } else {
                    // Fecha → número de serie de Excel (días desde 1899-12-30)
                    $num = $tipo === 'fecha' ? strtotime($valor . ' UTC') / 86400 + 25569 : (float)$valor;
                    // json_encode usa la representación exacta más corta (el cast a string solo da 14 dígitos)
                    $xml .= '<c r="' . $ref . '" s="' . self::ESTILOS[$tipo] . '"><v>' . json_encode($num) . '</v></c>';
                }
            }
            $xml .= '</row>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
            . '<cols>' . $cols . '</cols>'
            . '<sheetData>' . $xml . '</sheetData>'
            . '<autoFilter ref="A1:' . $ultima . '"/>'
            . '</worksheet>';
    }

    private static function estilos(): string
    {
        $xf = fn (int $fmt, int $font = 0, int $fill = 0) =>
            '<xf numFmtId="' . $fmt . '" fontId="' . $font . '" fillId="' . $fill . '" borderId="0" xfId="0"'
            . ($fmt ? ' applyNumberFormat="1"' : '') . ($font ? ' applyFont="1"' : '') . ($fill ? ' applyFill="1"' : '') . '/>';

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<numFmts count="2"><numFmt numFmtId="164" formatCode="dd/mm/yyyy hh:mm"/><numFmt numFmtId="165" formatCode="#,##0.000"/></numFmts>'
            . '<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts>'
            . '<fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FFD9D9D9"/><bgColor indexed="64"/></patternFill></fill></fills>'
            . '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="6">'
            . $xf(0)          // 0 texto
            . $xf(0, 1, 2)    // 1 encabezado: negrita + fondo gris
            . $xf(165)        // 2 decimal: 1,234.567
            . $xf(3)          // 3 entero: 1,234
            . $xf(164)        // 4 fecha: dd/mm/yyyy hh:mm
            . $xf(0)          // 5 número general
            . '</cellXfs>'
            . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            . '</styleSheet>';
    }

    private static function celdaTexto(string $ref, string $texto, int $estilo): string
    {
        return '<c r="' . $ref . '" t="inlineStr" s="' . $estilo . '"><is><t xml:space="preserve">' . self::xml($texto) . '</t></is></c>';
    }

    // 0 → A, 25 → Z, 26 → AA
    private static function columna(int $i): string
    {
        $s = '';
        for ($i++; $i > 0; $i = intdiv($i - 1, 26)) {
            $s = chr(65 + ($i - 1) % 26) . $s;
        }
        return $s;
    }

    private static function xml(string $s): string
    {
        // Quita caracteres de control que el XML no admite
        return htmlspecialchars(preg_replace('/[^\x09\x0A\x0D\x20-\x{D7FF}\x{E000}-\x{FFFD}]/u', '', $s), ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
