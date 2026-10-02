<?php

namespace App\Services\Account;

use App\Services\ReportBrandingService;
use App\Support\ReportExporter;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * Shared export engine for Accounting module screens.
 */
class AccountExportService
{
    public function exportByFormat(string $format, string $bladeView, array $viewData, string $filenameBase)
    {
        $safeName = preg_replace('/[^a-zA-Z0-9._-]+/', '-', $filenameBase) ?? 'report';
        $viewData['reportBranding'] = app(ReportBrandingService::class)->get();
        $format = strtolower($format);

        if ($format === 'pdf') {
            $paperOrientation = $viewData['paperOrientation'] ?? 'landscape';
            $pdf = Pdf::loadView($bladeView, $viewData)
                ->setPaper('a4', $paperOrientation)
                ->setOptions([
                    'isHtml5ParserEnabled' => true,
                    'isRemoteEnabled' => true,
                    'defaultFont' => 'sans-serif',
                    'tempDir' => storage_path('app/public'),
                    'chroot' => [
                        base_path(),
                        public_path(),
                        storage_path('app/public'),
                    ],
                ]);

            return $pdf->download($safeName . '.pdf');
        }

        $matrix = $this->extractTableMatrix($bladeView, $viewData);
        $title = $viewData['pageTitle'] ?? str_replace('-', ' ', $safeName);

        if (in_array($format, ['xlsx', 'xls', 'excel'], true)) {
            return ReportExporter::excelFromMatrix($matrix, $safeName, $title);
        }

        return response()->streamDownload(function () use ($matrix) {
            $out = fopen('php://output', 'w');
            fwrite($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
            foreach ($matrix as $row) {
                fputcsv($out, $row['cells']);
            }
            fclose($out);
        }, $safeName . '.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @return list<array{cells: list<string>, header: bool}>
     */
    protected function extractTableMatrix(string $bladeView, array $viewData): array
    {
        $html = view($bladeView, array_merge($viewData, ['exportMode' => 'csv']))->render();
        $matrix = [];

        if (! preg_match_all('/<tr[^>]*>(.*?)<\/tr>/is', $html, $rows)) {
            return $matrix;
        }

        foreach ($rows[1] as $rowHtml) {
            if (! preg_match_all('/<(t[dh])[^>]*>(.*?)<\/\1>/is', $rowHtml, $cells, PREG_SET_ORDER)) {
                continue;
            }

            $line = [];
            $isHeader = true;
            foreach ($cells as $cell) {
                $isHeader = $isHeader && strcasecmp($cell[1], 'th') === 0;
                $line[] = trim(html_entity_decode(
                    strip_tags(str_replace(['<br>', '<br/>', '<br />'], ' ', $cell[2])),
                    ENT_QUOTES | ENT_HTML5,
                    'UTF-8'
                ));
            }

            $matrix[] = [
                'cells' => $line,
                'header' => $isHeader,
            ];
        }

        return $matrix;
    }
}
