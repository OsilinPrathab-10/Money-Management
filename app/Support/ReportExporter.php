<?php

namespace App\Support;

use App\Services\ReportBrandingService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportExporter
{
    /**
     * @param  iterable<int, array<string, mixed>>  $rows
     */
    public static function excel(iterable $rows, string $filename, ?string $title = null): StreamedResponse
    {
        [$headers, $data] = self::normalize($rows);

        return response()->streamDownload(function () use ($headers, $data, $title) {
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Report');

            $startRow = 1;
            if ($title) {
                $lastCol = max(1, count($headers));
                $sheet->setCellValue('A1', $title);
                $sheet->mergeCells('A1:' . Coordinate::stringFromColumnIndex($lastCol) . '1');
                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
                $sheet->getRowDimension(1)->setRowHeight(22);
                $startRow = 3;
            }

            if ($headers) {
                $sheet->fromArray($headers, null, 'A' . $startRow);
                $headerRange = 'A' . $startRow . ':' . Coordinate::stringFromColumnIndex(count($headers)) . $startRow;
                $sheet->getStyle($headerRange)->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => '666CFF'],
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                        'wrapText' => true,
                    ],
                ]);
                $sheet->getRowDimension($startRow)->setRowHeight(22);
            }

            $rowNumber = $startRow + 1;
            foreach ($data as $row) {
                $sheet->fromArray($row, null, 'A' . $rowNumber);
                $rowNumber++;
            }

            $lastDataRow = max($startRow, $rowNumber - 1);
            $lastColLetter = Coordinate::stringFromColumnIndex(max(1, count($headers)));
            $usedRange = 'A' . $startRow . ':' . $lastColLetter . $lastDataRow;

            $sheet->getStyle($usedRange)->applyFromArray([
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['rgb' => 'D9DBE5'],
                    ],
                ],
                'alignment' => [
                    'vertical' => Alignment::VERTICAL_CENTER,
                    'wrapText' => true,
                ],
            ]);

            foreach (array_keys($headers) as $index) {
                $col = Coordinate::stringFromColumnIndex($index + 1);
                $header = (string) ($headers[$index] ?? '');
                $sheet->getColumnDimension($col)->setAutoSize(true);
                $width = (float) $sheet->getColumnDimension($col)->getWidth();
                $min = self::isNumericHeader($header) ? 14 : 16;
                $max = self::isNumericHeader($header) ? 22 : 36;
                $sheet->getColumnDimension($col)->setAutoSize(false);
                $sheet->getColumnDimension($col)->setWidth(min($max, max($min, $width + 2)));

                if (self::isNumericHeader($header)) {
                    $sheet->getStyle($col . ($startRow + 1) . ':' . $col . $lastDataRow)
                        ->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                }
            }

            if ($headers) {
                $sheet->setAutoFilter('A' . $startRow . ':' . $lastColLetter . $lastDataRow);
                $sheet->freezePane('A' . ($startRow + 1));
            }

            $sheet->getDefaultRowDimension()->setRowHeight(18);

            (new Xlsx($spreadsheet))->save('php://output');
            $spreadsheet->disconnectWorksheets();
        }, self::safeFilename($filename) . '.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * @param  iterable<int, array<string, mixed>>  $rows
     */
    public static function pdf(iterable $rows, string $filename, string $title, array $filters = []): \Illuminate\Http\Response
    {
        [$headers, $data] = self::normalize($rows);

        return Pdf::loadView('admin.reports.export-table', [
            'title' => $title,
            'headers' => $headers,
            'rows' => $data,
            'filters' => self::visibleFilters($filters),
            'generatedAt' => now(),
            'reportBranding' => app(ReportBrandingService::class)->get(),
        ])->setPaper('a4', 'landscape')->download(self::safeFilename($filename) . '.pdf');
    }

    /**
     * @param  iterable<int, array<string, mixed>>  $rows
     */
    public static function csv(iterable $rows, string $filename): StreamedResponse
    {
        [$headers, $data] = self::normalize($rows);

        return response()->streamDownload(function () use ($headers, $data) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            if ($headers) {
                fputcsv($out, $headers);
            }
            foreach ($data as $row) {
                fputcsv($out, $row);
            }
            fclose($out);
        }, self::safeFilename($filename) . '.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Write a raw table matrix (rows of cells). Shorter rows are padded so columns stay aligned.
     *
     * @param  list<array{cells: list<string>, header?: bool}>|list<list<string>>  $matrix
     */
    public static function excelFromMatrix(array $matrix, string $filename, ?string $title = null): StreamedResponse
    {
        $normalized = [];
        foreach ($matrix as $row) {
            if (is_array($row) && array_key_exists('cells', $row)) {
                $normalized[] = [
                    'cells' => array_map('strval', $row['cells']),
                    'header' => ! empty($row['header']),
                ];
            } else {
                $normalized[] = [
                    'cells' => array_map('strval', is_array($row) ? array_values($row) : [(string) $row]),
                    'header' => false,
                ];
            }
        }

        $colCount = 1;
        foreach ($normalized as $row) {
            $colCount = max($colCount, count($row['cells']));
        }
        foreach ($normalized as $index => $row) {
            $normalized[$index]['cells'] = array_pad($row['cells'], $colCount, '');
        }

        if ($normalized !== [] && ! collect($normalized)->contains(fn ($row) => $row['header'])) {
            $normalized[0]['header'] = true;
        }

        return response()->streamDownload(function () use ($normalized, $title, $colCount) {
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Report');

            $startRow = 1;
            if ($title) {
                $sheet->setCellValue('A1', $title);
                $sheet->mergeCells('A1:' . Coordinate::stringFromColumnIndex($colCount) . '1');
                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
                $sheet->getRowDimension(1)->setRowHeight(22);
                $startRow = 3;
            }

            $rowNumber = $startRow;
            foreach ($normalized as $row) {
                $sheet->fromArray($row['cells'], null, 'A' . $rowNumber);
                if ($row['header']) {
                    $headerRange = 'A' . $rowNumber . ':' . Coordinate::stringFromColumnIndex($colCount) . $rowNumber;
                    $sheet->getStyle($headerRange)->applyFromArray([
                        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                        'fill' => [
                            'fillType' => Fill::FILL_SOLID,
                            'startColor' => ['rgb' => '666CFF'],
                        ],
                        'alignment' => [
                            'horizontal' => Alignment::HORIZONTAL_CENTER,
                            'vertical' => Alignment::VERTICAL_CENTER,
                            'wrapText' => true,
                        ],
                    ]);
                    $sheet->getRowDimension($rowNumber)->setRowHeight(22);
                }
                $rowNumber++;
            }

            $lastDataRow = max($startRow, $rowNumber - 1);
            $lastColLetter = Coordinate::stringFromColumnIndex($colCount);
            $usedRange = 'A' . $startRow . ':' . $lastColLetter . $lastDataRow;

            $sheet->getStyle($usedRange)->applyFromArray([
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['rgb' => 'D9DBE5'],
                    ],
                ],
                'alignment' => [
                    'vertical' => Alignment::VERTICAL_CENTER,
                    'wrapText' => true,
                ],
            ]);

            for ($index = 0; $index < $colCount; $index++) {
                $col = Coordinate::stringFromColumnIndex($index + 1);
                $sheet->getColumnDimension($col)->setAutoSize(true);
                $width = (float) $sheet->getColumnDimension($col)->getWidth();
                $sheet->getColumnDimension($col)->setAutoSize(false);
                $sheet->getColumnDimension($col)->setWidth(min(36, max(14, $width + 2)));
            }

            $sheet->freezePane('A' . ($startRow + 1));
            $sheet->getDefaultRowDimension()->setRowHeight(18);

            (new Xlsx($spreadsheet))->save('php://output');
            $spreadsheet->disconnectWorksheets();
        }, self::safeFilename($filename) . '.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * @param  iterable<int, array<string, mixed>>  $rows
     */
    public static function download(iterable $rows, string $format, string $filename, string $title, array $filters = [])
    {
        $format = strtolower($format);

        return match ($format) {
            'pdf' => self::pdf($rows, $filename, $title, $filters),
            'xlsx', 'xls', 'excel' => self::excel($rows, $filename, $title),
            default => self::csv($rows, $filename),
        };
    }

    /**
     * Keep only scalar filter values that belong on a PDF header.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, string>
     */
    protected static function visibleFilters(array $filters): array
    {
        $skip = [
            '_token', 'format', 'page', '_export', 'draw', 'columns', 'order', 'search',
            'start', 'length', 'sort', '_method',
        ];
        $visible = [];

        foreach ($filters as $key => $value) {
            if (in_array($key, $skip, true) || is_array($value) || ! filled($value) || ! is_scalar($value)) {
                continue;
            }
            if ($key === 'status' && strtolower((string) $value) === 'all') {
                continue;
            }
            if ($key === 'date_preset' && in_array(strtolower((string) $value), ['all', 'all_time'], true)) {
                continue;
            }
            $visible[$key] = (string) $value;
        }

        return $visible;
    }

    /**
     * @param  iterable<int, mixed>  $rows
     * @return array{0: list<string>, 1: list<list<string>>}
     */
    protected static function normalize(iterable $rows): array
    {
        $list = Collection::make($rows)->map(function ($row) {
            if ($row instanceof Collection) {
                return $row->toArray();
            }

            return is_array($row) ? $row : (array) $row;
        })->values();

        $headers = $list->isNotEmpty() ? array_map('strval', array_keys($list->first())) : [];
        $data = $list->map(function (array $row) use ($headers) {
            return array_map(function ($header) use ($row) {
                $value = $row[$header] ?? '';
                if ($value === null) {
                    return '';
                }
                if (is_bool($value)) {
                    return $value ? 'Yes' : 'No';
                }

                return is_scalar($value) ? (string) $value : '';
            }, $headers);
        })->all();

        return [$headers, $data];
    }

    protected static function isNumericHeader(string $header): bool
    {
        $header = strtolower($header);

        return str_contains($header, 'amount')
            || str_contains($header, '₹')
            || str_contains($header, 'principal')
            || str_contains($header, 'interest')
            || str_contains($header, 'balance')
            || str_contains($header, 'paid')
            || str_contains($header, 'outstanding');
    }

    protected static function safeFilename(string $filename): string
    {
        $safe = preg_replace('/[^a-zA-Z0-9._-]+/', '-', $filename) ?: 'report';

        return trim($safe, '-') . '_' . now()->format('Y-m-d');
    }
}
