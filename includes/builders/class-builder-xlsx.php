<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/**
 * XLSX builder.
 *
 * Strategy:
 *   1. If PhpSpreadsheet is installed (composer install --no-dev), use it for
 *      a real .xlsx with proper headers, autosize, and freeze pane.
 *   2. Else, fall back to writing a minimal Office Open XML structure as a ZIP
 *      (works in Excel, Numbers, LibreOffice for simple data).
 *
 * @package Pelican
 */
class Pelican_Builder_XLSX {
    public static function build( $columns, $rows, $path ) {
        if ( class_exists( '\PhpOffice\PhpSpreadsheet\Spreadsheet' ) ) {
            return self::build_with_phpspreadsheet( $columns, $rows, $path );
        }
        return self::build_minimal( $columns, $rows, $path );
    }

    protected static function build_with_phpspreadsheet( $columns, $rows, $path ) {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle( 'Orders' );

        $headers = array_map( function ( $c ) {
            return is_array( $c ) ? ( $c['label'] ?? $c['key'] ?? '' ) : (string) $c;
        }, $columns );

        $col_letter = 'A';
        foreach ( $headers as $h ) {
            $sheet->setCellValue( $col_letter . '1', $h );
            $sheet->getStyle( $col_letter . '1' )->getFont()->setBold( true );
            $col_letter++;
        }
        $row_num = 2;
        foreach ( $rows as $row ) {
            $col_letter = 'A';
            foreach ( $row as $val ) {
                $sheet->setCellValue( $col_letter . $row_num, $val );
                $col_letter++;
            }
            $row_num++;
        }
        foreach ( range( 'A', $col_letter ) as $c ) {
            $sheet->getColumnDimension( $c )->setAutoSize( true );
        }
        $sheet->freezePane( 'A2' );

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx( $spreadsheet );
        $writer->save( $path );
        return $path;
    }

    protected static function build_minimal( $columns, $rows, $path ) {
        if ( ! class_exists( 'ZipArchive' ) ) {
            /* Last-resort fallback — write CSV + .xlsx extension. Excel will warn but still open. */
            return Pelican_Builder_CSV::build( $columns, $rows, $path, ',' );
        }
        $headers = array_map( function ( $c ) {
            return is_array( $c ) ? ( $c['label'] ?? $c['key'] ?? '' ) : (string) $c;
        }, $columns );

        $sheet_xml  = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";
        $sheet_xml .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';
        $r = 0;
        $r++;
        $sheet_xml .= '<row r="' . $r . '">';
        $col = 'A';
        foreach ( $headers as $h ) {
            $sheet_xml .= '<c r="' . $col . $r . '" t="inlineStr"><is><t>' . self::xml_esc( $h ) . '</t></is></c>';
            $col++;
        }
        $sheet_xml .= '</row>';
        foreach ( $rows as $row ) {
            $r++;
            $sheet_xml .= '<row r="' . $r . '">';
            $col = 'A';
            foreach ( $row as $val ) {
                $sheet_xml .= '<c r="' . $col . $r . '" t="inlineStr"><is><t>' . self::xml_esc( $val ) . '</t></is></c>';
                $col++;
            }
            $sheet_xml .= '</row>';
        }
        $sheet_xml .= '</sheetData></worksheet>';

        $rels =
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n" .
            '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' .
            '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>' .
            '</Relationships>';
        $content_types =
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n" .
            '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">' .
            '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>' .
            '<Default Extension="xml" ContentType="application/xml"/>' .
            '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>' .
            '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>' .
            '</Types>';
        $workbook =
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n" .
            '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">' .
            '<sheets><sheet name="Orders" sheetId="1" r:id="rId1"/></sheets>' .
            '</workbook>';
        $workbook_rels =
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n" .
            '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' .
            '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>' .
            '</Relationships>';

        $zip = new \ZipArchive();
        if ( true !== $zip->open( $path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE ) ) {
            throw new \RuntimeException( 'Cannot create xlsx zip ' . $path );
        }
        $zip->addFromString( '[Content_Types].xml', $content_types );
        $zip->addFromString( '_rels/.rels', $rels );
        $zip->addFromString( 'xl/workbook.xml', $workbook );
        $zip->addFromString( 'xl/_rels/workbook.xml.rels', $workbook_rels );
        $zip->addFromString( 'xl/worksheets/sheet1.xml', $sheet_xml );
        $zip->close();
        return $path;
    }

    protected static function xml_esc( $s ) {
        return htmlspecialchars( (string) $s, ENT_QUOTES | ENT_XML1, 'UTF-8' );
    }
}
