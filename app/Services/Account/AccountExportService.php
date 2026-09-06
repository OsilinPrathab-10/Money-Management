<?php

namespace App\Services\Account;

use Barryvdh\DomPDF\Facade\Pdf;

/**
 * Shared export engine for Accounting module screens.
 *
 * Exports are generated from Blade templates:
 * - `exportMode=csv`: a simple <table> structure used to build CSV rows
 * - `exportMode=excel`: an HTML table returned as an Excel-compatible .xls download
 * - `exportMode=pdf`: a printable HTML document rendered by dompdf
 */
class AccountExportService
{
    public function exportByFormat(string $format, string $bladeView, array $viewData, string $filenameBase)
    {
        $safeName = preg_replace('/[^a-zA-Z0-9._-]+/', '-', $filenameBase) ?? 'report';

        if ($format === 'pdf') {
            $pdf = Pdf::loadView($bladeView, $viewData)
                ->setPaper('a4', 'portrait')
                ->setOptions([
                    'isHtml5ParserEnabled' => true,
                    'isRemoteEnabled' => true,
                    'defaultFont' => 'sans-serif',
                    'tempDir' => storage_path('app/public'),
                    'chroot'  => [
                        base_path(),
                        public_path(),
                        storage_path('app/public')
                    ],
                ]);

            return $pdf->download($safeName . '.pdf');
        }

        if ($format === 'csv') {
            return response()->streamDownload(function () use ($bladeView, $viewData) {
                $out = fopen('php://output', 'w');

                // UTF-8 BOM for Excel compatibility
                fwrite($out, chr(0xEF) . chr(0xBB) . chr(0xBF));

                $this->renderReportAsCsv($out, $bladeView, $viewData);
                fclose($out);
            }, $safeName . '.csv', [
                'Content-Type' => 'text/csv; charset=UTF-8',
            ]);
        }

        // Default: xlsx/xls (HTML table with excel content-type)
        $html = view($bladeView, array_merge($viewData, ['exportMode' => 'excel']))->render();

        return response($html, 200, [
            'Content-Type' => 'application/vnd.ms-excel',
            'Content-Disposition' => 'attachment; filename="' . $safeName . '.xls"',
        ]);
    }

    /**
     * @param  resource  $out
     */
    protected function renderReportAsCsv($out, string $bladeView, array $viewData): void
    {
        $html = view($bladeView, array_merge($viewData, ['exportMode' => 'csv']))->render();

        // Extract table rows (simple & consistent with existing ReportsController export logic)
        if (preg_match_all('/<tr[^>]*>(.*?)<\/tr>/is', $html, $rows)) {
            foreach ($rows[1] as $rowHtml) {
                if (!preg_match_all('/<t[dh][^>]*>(.*?)<\/t[dh]>/is', $rowHtml, $cells)) {
                    continue;
                }

                $line = [];
                foreach ($cells[1] as $cell) {
                    $line[] = trim(html_entity_decode(
                        strip_tags(str_replace('<br>', ' ', $cell)),
                        ENT_QUOTES | ENT_HTML5,
                        'UTF-8'
                    ));
                }

                fputcsv($out, $line);
            }
        }
    }
}

