<?php
if (!class_exists('wms_TCPDF')) {
    require_once("tcpdf/wmsTcpdf.php");
}

if (!class_exists('wms_FPDI')) {
    require_once("FPDI/wmsFpdi.php");
}

class PDF_lib
{
    const DESTINATION__INLINE = "I";
    const DESTINATION__DOWNLOAD = "D";
    const DESTINATION__DISK = "F";
    const DESTINATION__DISK_INLINE = "FI";
    const DESTINATION__DISK_DOWNLOAD = "FD";
    const DESTINATION__BASE64_RFC2045 = "E";
    const DESTINATION__STRING = "S";

    const DEFAULT_DESTINATION = self::DESTINATION__INLINE;
    const DEFAULT_MERGED_FILE_NAME = __DIR__."/merged-files.pdf";

    public static function merge($files, $destination = null, $output_path = null)
    {
        if (empty($destination)) {
            $destination = self::DEFAULT_DESTINATION;
        }

        if (empty($output_path)) {
            $output_path = self::DEFAULT_MERGED_FILE_NAME;
        }

        $pdf = new wms_FPDI();
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        self::join($pdf, $files);
        $pdf->Output($output_path, $destination);
    }

    private static function join($pdf, $fileList)
    {
        if (empty($fileList) || !is_array($fileList)) {
            die("invalid file list");
        }

        foreach ($fileList as $file) {
            self::addFile($pdf, $file);
        }
    }

    private static function addFile($pdf, $file)
    {
        $numPages = $pdf->setSourceFile($file);

        if (empty($numPages) || $numPages < 1) {
            return;
        }

        for ($x = 1; $x <= $numPages; $x++) {
            $pdf->AddPage();
            $pdf->useTemplate($pdf->importPage($x), null, null, 0, 0, true);
            $pdf->endPage();
        }
    }


    public static function split_pdf($file_name, $destination = null, $output_path = null)
    {

        if (empty($destination)) {
            $destination = self::DEFAULT_DESTINATION;
        }

        if (empty($output_path)) {
            $output_path = self::DEFAULT_MERGED_FILE_NAME;
        }

        $pdf = new wms_FPDI();
        $pagecount = $pdf->setSourceFile($file_name); // How many pages?
        $pdf_pages = [];


        for ($i = 1; $i <= $pagecount; $i++) {
            $new_pdf = new wms_FPDI();
            $new_pdf->AddPage();
            $new_pdf->setSourceFile($file_name);
            $new_pdf->useTemplate($new_pdf->importPage($i), null, null, 0, 0, true);
            $new_pdf->endPage();

            try {
                $pdf_pages[] = $new_pdf->Output($output_path, $destination, true);
            } catch (\Exception $e) {
                echo 'Caught exception: ', $e->getMessage(), "\n";
            }
        }

        return $pdf_pages;
    }

    public static function gif_to_pdf($file_name, $destination = null, $output_path = null)
    {

        if (empty($destination)) {
            $destination = self::DEFAULT_DESTINATION;
        }

        if (empty($output_path)) {
            $output_path = self::DEFAULT_MERGED_FILE_NAME;
        }
        $new_pdf = new wms_FPDI();
        $new_pdf->AddPage();

        $new_pdf->Image($file_name, 0, 0, 250, 150);
        $new_pdf->endPage();
        $pdf_pages = $new_pdf->Output($output_path, $destination, true);

        return $pdf_pages;
    }
}
