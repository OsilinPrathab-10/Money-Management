<?php

namespace App\Services;

use App\Models\LoanAccount;
use App\Models\LoanDocumentTemplate;
use App\Models\ClientLoanDocument;
use App\Models\Appearance;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Mpdf\Mpdf;
use App\Helpers\AppearanceHelper;
use App\Services\EmiCalculator;

class LoanDocumentService
{
    /**
     * Generate document and save to storage and database
     */
    public function generateAndSaveDocument(int $loanId, string $documentType): array
    {
        if ($documentType === 'loan_agreement') {
            $loanAccount = LoanAccount::with(['loanApplication.product', 'product'])->find($loanId);
            $loanProduct = $loanAccount?->loanApplication?->product ?? $loanAccount?->product;
            if ($loanProduct) {
                $productSpecificType = 'loan_agreement_' . strtolower(str_replace(' ', '_', $loanProduct->loan_name ?? ''));
                if (LoanDocumentTemplate::whereRaw('LOWER(type) = ?', [strtolower($productSpecificType)])->exists()) {
                    $documentType = $productSpecificType;
                }
            }
        }
        // Generate the PDF document
        $document = $this->prepareDocument($loanId, $documentType);
        
        // Get loan account and client info
        $loanAccount = LoanAccount::with(['loanApplication.client', 'client'])->findOrFail($loanId);
        $client = $loanAccount->client ?? $loanAccount->loanApplication?->client;
        if (! $client) {
            throw new \RuntimeException('Cannot generate document: loan account has no linked client.');
        }

        $fileName = $document['fileName'];
        $directory = "loan_documents/{$loanAccount->account_number}";
        $filePath = "{$directory}/{$fileName}";

        Storage::disk('public')->makeDirectory($directory);
        Storage::disk('public')->put($filePath, $document['binary']);
        
        // Get file size
        $fileSize = Storage::disk('public')->size($filePath);
        
        // Get document title
        $template = LoanDocumentTemplate::whereRaw('LOWER(type) = ?', [strtolower($documentType)])->first();
        $documentTitle = $template ? $template->title : ucfirst(str_replace('_', ' ', $documentType));
        
        // Save or update document record in database
        ClientLoanDocument::updateOrCreate(
            [
                'loan_account_id' => $loanId,
                'document_type' => $documentType,
            ],
            [
                'client_id' => $client->id,
                'document_title' => $documentTitle,
                'file_path' => $filePath,
                'file_name' => $fileName,
                'file_size' => $fileSize,
                'generated_at' => now(),
                'generated_by' => auth()->id(),
            ]
        );
        
        Log::info('Document saved to database', [
            'loan_id' => $loanId,
            'document_type' => $documentType,
            'file_path' => $filePath
        ]);
        
        return $document;
    }

    /**
     * Get existing document from database
     */
    public function getExistingDocument(int $loanId, string $documentType): ?ClientLoanDocument
    {
        return ClientLoanDocument::where('loan_account_id', $loanId)
            ->whereRaw('LOWER(document_type) = ?', [strtolower($documentType)])
            ->first();
    }

    /**
     * Generate document binary without saving
     */
    public function generateDocumentBinary(int $loanId, string $documentType): array
    {
        return $this->prepareDocument($loanId, $documentType);
    }

    /**
     * Generate and save all applicable documents for a loan account
     */
    public function generateAndSaveAllDocuments(LoanAccount $loanAccount)
    {
        $availableTemplates = $this->getAvailableDocuments($loanAccount);
        
        foreach ($availableTemplates as $template) {
            $this->generateAndSaveDocument($loanAccount->id, $template->type);
        }
    }

    /**
     * Get available documents based on loan status and product
     */
    public function getAvailableDocuments(LoanAccount $loanAccount)
    {
        $loanStatus = strtolower($loanAccount->status ?? 'pending');
        $loanProduct = $loanAccount->loanApplication->product
            ?? $loanAccount->loanProduct
            ?? $loanAccount->product
            ?? null;
        $isForeclosed = $loanAccount->is_foreclosed ?? false;
        
        $openStatuses = ['active', 'disbursed', 'approved', 'process'];
        $closedStatuses = ['closed', 'foreclosed'];
        $allLiveStatuses = array_merge($openStatuses, $closedStatuses);

        $documentRules = [
            'loan_agreement' => $allLiveStatuses,
            'loan_sanction_letter' => array_merge(['pending'], $allLiveStatuses),
            'repayment_schedule' => $allLiveStatuses,
            'statement' => $allLiveStatuses,
            'payment_receipt' => $allLiveStatuses,
            'loan_closure_certificate' => $closedStatuses,
            'noc' => $closedStatuses,
            'foreclosure_letter' => ['foreclosed'],
        ];

        $availableDocuments = collect();
        
        foreach ($documentRules as $documentType => $allowedStatuses) {
            // Special handling for foreclosure letter - check is_foreclosed flag
            if ($documentType === 'foreclosure_letter') {
                if ($isForeclosed) {
                    $template = LoanDocumentTemplate::whereRaw('LOWER(type) = ?', [strtolower($documentType)])->first();
                    if ($template) {
                        $template->display_title = $this->formatDocumentTitle($template, $loanAccount, $documentType);
                        $availableDocuments->push($template);
                    }
                }
                continue;
            }

            // Special handling for payment receipt - only show if there are payments
            if ($documentType === 'payment_receipt' && ($loanAccount->paid_amount <= 0)) {
                continue;
            }
            
            if (in_array($loanStatus, $allowedStatuses)) {
                // For loan agreement, check if there's a product-specific template first
                if ($documentType === 'loan_agreement' && $loanProduct) {
                    $productSpecificType = 'loan_agreement_' . strtolower(str_replace(' ', '_', $loanProduct->loan_name ?? ''));
                    $productTemplate = LoanDocumentTemplate::whereRaw('LOWER(type) = ?', [strtolower($productSpecificType)])->first();
                    
                    if ($productTemplate) {
                        $productTemplate->display_title = $this->formatDocumentTitle($productTemplate, $loanAccount, $documentType);
                        $availableDocuments->push($productTemplate);
                        continue; // Skip the generic loan agreement
                    }
                }
                
                // Get the generic template (case-insensitive)
                $template = LoanDocumentTemplate::whereRaw('LOWER(type) = ?', [strtolower($documentType)])->first();
                
                // If template missing but it's an essential document, use a virtual one
                if (!$template && in_array($documentType, ['repayment_schedule', 'statement', 'loan_agreement', 'loan_sanction_letter', 'payment_receipt'])) {
                    $template = new \stdClass();
                    $template->type = $documentType;
                    $template->title = ucwords(str_replace('_', ' ', $documentType));
                    $template->display_title = $this->formatDocumentTitle($template, $loanAccount, $documentType);
                    $availableDocuments->push($template);
                } elseif ($template) {
                    $template->display_title = $this->formatDocumentTitle($template, $loanAccount, $documentType);
                    $availableDocuments->push($template);
                }
            }
        }

        return $availableDocuments;
    }

    /**
     * Merge available templates with saved files for the loan account documents table.
     */
    public function buildDocumentRows($availableTemplates, $savedDocuments)
    {
        $savedDocuments = collect($savedDocuments);
        $rows = collect();
        $seen = [];

        foreach ($availableTemplates as $template) {
            $type = (string) $template->type;
            $saved = $this->matchSavedDocument($savedDocuments, $type);
            $seen[strtolower($type)] = true;
            if ($saved) {
                $seen[strtolower((string) $saved->document_type)] = true;
            }
            $rows->push((object) [
                'document_type' => $type,
                'document_title' => $template->display_title ?? $template->title ?? ucwords(str_replace('_', ' ', $type)),
                'generated' => (bool) $saved,
                'saved' => $saved,
            ]);
        }

        foreach ($savedDocuments as $saved) {
            $key = strtolower((string) $saved->document_type);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $rows->push((object) [
                'document_type' => $saved->document_type,
                'document_title' => $saved->document_title ?? ucwords(str_replace('_', ' ', $saved->document_type)),
                'generated' => true,
                'saved' => $saved,
            ]);
        }

        return $rows->values();
    }

    protected function matchSavedDocument($savedDocuments, string $type)
    {
        $type = strtolower($type);

        return collect($savedDocuments)->first(function ($doc) use ($type) {
            $docType = strtolower((string) $doc->document_type);
            if ($docType === $type) {
                return true;
            }

            return str_starts_with($type, 'loan_agreement')
                && str_starts_with($docType, 'loan_agreement');
        });
    }

    /**
     * Prepare and generate PDF document
     */
    private function prepareDocument(int $loanId, string $documentType): array
    {
        $loanAccount = LoanAccount::with(['loanApplication.client', 'loanApplication.product', 'client', 'product'])
            ->findOrFail($loanId);

        if ($documentType === 'loan_agreement') {
            $loanProduct = $loanAccount->loanApplication?->product ?? $loanAccount->product;
            if ($loanProduct) {
                $productSpecificType = 'loan_agreement_' . strtolower(str_replace(' ', '_', $loanProduct->loan_name ?? ''));
                if (LoanDocumentTemplate::whereRaw('LOWER(type) = ?', [strtolower($productSpecificType)])->exists()) {
                    $documentType = $productSpecificType;
                }
            }
        }

        $template = LoanDocumentTemplate::whereRaw('LOWER(type) = ?', [strtolower($documentType)])->first();
        if (!$template) {
            $template = new \stdClass();
            $template->title = ucfirst(str_replace('_', ' ', $documentType));
            $template->type = $documentType;
            $template->header = '
                <table>
                    <tr>
                        <td style="width: 50%;">
                            <strong>Client Name:</strong> {{client_name}}<br>
                            <strong>Address:</strong> {{client_address}}
                        </td>
                        <td style="text-align: right;">
                            <strong>Loan Account No:</strong> {{loan_number}}<br>
                            <strong>Statement Date:</strong> {{current_date}}
                        </td>
                    </tr>
                </table>';
            $template->footer = '<p>Generated on {{current_date}}</p>';
            $template->body = $this->getDefaultDocumentBody($documentType);
        }

        $displayTitle = $this->formatDocumentTitle($template, $loanAccount, $documentType);

        // Use centralized placeholder service
        $replacements = DocumentPlaceholderService::getReplacements($loanAccount);

        // Handle both regular and HTML-encoded placeholders
        $templateHeader = html_entity_decode($template->header ?? '', ENT_QUOTES, 'UTF-8');
        $templateFooter = html_entity_decode($template->footer ?? '', ENT_QUOTES, 'UTF-8');
        $templateBody = html_entity_decode($template->body ?? '', ENT_QUOTES, 'UTF-8');

        // Special handling for repayment schedule - generate EMI table
        if ($documentType === 'repayment_schedule') {
            $emiData = $this->generateEmiTableHtml($loanAccount);
            
            // Replace Mustache loop with actual HTML
            $templateBody = preg_replace(
                '/\{\{#repayments\}\}.*?\{\{\/repayments\}\}/s',
                $emiData['rows'],
                $templateBody
            );
            
            // Add totals to replacements
            $replacements['{{total_installment_amount}}'] = $emiData['total_installment_amount'];
            $replacements['{{total_principal}}'] = $emiData['total_principal'];
            $replacements['{{total_interest}}'] = $emiData['total_interest'];
        }

        $header = DocumentPlaceholderService::replacePlaceholders($templateHeader, $replacements);
        $footer = DocumentPlaceholderService::replacePlaceholders($templateFooter, $replacements);
        $body = DocumentPlaceholderService::replacePlaceholders($templateBody, $replacements);

        $logoData = $this->resolveLogoData();

        // Generate HTML content
        $htmlContent = view('pdf.dynamic_document', [
            'header' => $header,
            'footer' => $footer,
            'body' => $body,
            'logo' => $logoData['logo'],
            'loan' => $loanAccount,
            'client' => $loanAccount->loanApplication->client ?? $loanAccount->client,
            'title' => $displayTitle,
            'company' => [
                'name' => AppearanceHelper::get('title', 'Loan App'),
                'subtitle' => AppearanceHelper::get('subtitle', '')
            ]
        ])->render();

        // Create mPDF instance with Unicode support
        $tempDir = storage_path('app/temp');
        if (! is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'orientation' => 'P',
            'margin_left' => 15,
            'margin_right' => 15,
            'margin_top' => 16,
            'margin_bottom' => 16,
            'margin_header' => 9,
            'margin_footer' => 9,
            'default_font' => 'dejavusans',
            'tempDir' => $tempDir
        ]);

        $mpdf->WriteHTML($htmlContent);

        $fileName = "{$documentType}_{$loanAccount->account_number}_" . now()->format('Ymd_His') . ".pdf";

        return [
            'binary' => $mpdf->Output('', 'S'),
            'fileName' => $fileName,
            'message' => ucfirst(str_replace('_', ' ', $documentType)) . ' generated successfully.',
            'logoDebug' => $logoData['debug']
        ];
    }

    /**
     * Format document title for display
     */
    private function formatDocumentTitle($template, LoanAccount $loanAccount, string $documentType): string
    {
        $title = trim($template->title ?? '');
        if ($title !== '') {
            return $title;
        }

        $baseType = Str::startsWith($documentType, 'loan_agreement_') ? 'loan_agreement' : $documentType;
        $label = ucwords(str_replace('_', ' ', $baseType));

        if (Str::startsWith($template->type ?? '', 'loan_agreement')) {
            $productName = trim($loanAccount->loanApplication->product->loan_name ?? '');
            if ($productName !== '') {
                return $productName . ' ' . $label;
            }
        }

        return $label;
    }

    /**
     * Resolve logo data for PDF
     */
    private function resolveLogoData(): array
    {
        $appearance = Appearance::where('type', 'web')->first();
        $debug = [
            'appearanceLogo' => $appearance ? $appearance->logo : 'none',
            'resolvedPath' => 'not found',
            'embedded' => 'no',
            'gdLoaded' => extension_loaded('gd') ? 'yes' : 'no'
        ];

        if (!$appearance || !$appearance->logo) {
            Log::warning('PDF Logo: no logo configured');
            return ['logo' => null, 'debug' => $debug];
        }

        if (!extension_loaded('gd')) {
            Log::warning('PDF Logo skipped because GD extension is unavailable.');
            return ['logo' => null, 'debug' => $debug];
        }

        $candidatePaths = [
            storage_path('app/public/' . $appearance->logo),
            public_path('storage/' . $appearance->logo)
        ];

        foreach ($candidatePaths as $path) {
            if ($path && file_exists($path)) {
                $debug['resolvedPath'] = $path;
                $logo = $this->embedLogoDataUri($path, $debug);
                if ($logo) {
                    $debug['embedded'] = 'yes';
                    Log::info('PDF Logo embedded', $debug);
                    return ['logo' => $logo, 'debug' => $debug];
                }
            }
        }

        Log::warning('PDF Logo file not found', $debug);
        return ['logo' => null, 'debug' => $debug];
    }

    /**
     * Embed a reasonably sized logo so mPDF does not overflow the page.
     */
    private function embedLogoDataUri(string $path, array &$debug): ?string
    {
        $maxWidth = 160;
        $maxHeight = 50;
        $info = @getimagesize($path);
        if (! $info) {
            return 'data:' . (mime_content_type($path) ?: 'image/png') . ';base64,' . base64_encode(file_get_contents($path));
        }

        [$width, $height] = $info;
        $debug['originalSize'] = "{$width}x{$height}";

        $src = null;
        switch ($info[2]) {
            case IMAGETYPE_JPEG:
                $src = imagecreatefromjpeg($path);
                break;
            case IMAGETYPE_PNG:
                $src = imagecreatefrompng($path);
                break;
            case IMAGETYPE_GIF:
                $src = imagecreatefromgif($path);
                break;
        }

        if (! $src) {
            return 'data:' . $info['mime'] . ';base64,' . base64_encode(file_get_contents($path));
        }

        $scale = min($maxWidth / max($width, 1), $maxHeight / max($height, 1), 1);
        $newWidth = max(1, (int) round($width * $scale));
        $newHeight = max(1, (int) round($height * $scale));
        $dst = imagecreatetruecolor($newWidth, $newHeight);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
        imagefilledrectangle($dst, 0, 0, $newWidth, $newHeight, $transparent);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

        ob_start();
        imagepng($dst);
        $png = ob_get_clean();
        imagedestroy($src);
        imagedestroy($dst);

        $debug['resizedTo'] = "{$newWidth}x{$newHeight}";

        return 'data:image/png;base64,' . base64_encode($png);
    }

    /**
     * Get default document body template
     */
    private function getDefaultDocumentBody($documentType)
    {
        switch ($documentType) {
            case 'loan_agreement':
                return '
                <h3>Loan Agreement</h3>
                <p><strong>Client Name:</strong> {client_name}</p>
                <p><strong>Loan Account Number:</strong> {loan_number}</p>
                <p><strong>Loan Amount:</strong> ₹{loan_amount}</p>
                <p><strong>Interest Rate:</strong> {interest_rate}% per annum</p>
                <p><strong>Tenure:</strong> {tenure} months</p>
                <p><strong>Product:</strong> {product_name}</p>
                <br>
                <p>This agreement is entered into between the lender and {client_name} for the loan amount of ₹{loan_amount}.</p>
                <p>The borrower agrees to repay the loan amount along with interest as per the agreed terms and conditions.</p>
                ';

            case 'repayment_schedule':
                return '
                <h3>Repayment Schedule</h3>
                <p><strong>Client Name:</strong> {client_name}</p>
                <p><strong>Loan Account Number:</strong> {loan_number}</p>
                <p><strong>Total Payable Amount:</strong> ₹{total_payable}</p>
                <p><strong>Outstanding Amount:</strong> ₹{outstanding_amount}</p>
                <p><strong>Paid Amount:</strong> ₹{paid_amount}</p>
                <br>
                <p>This document contains the complete repayment schedule for the loan account {loan_number}.</p>
                ';

            case 'statement':
                return '
                <h3>Loan Statement</h3>
                <p><strong>Client Name:</strong> {client_name}</p>
                <p><strong>Loan Account Number:</strong> {loan_number}</p>
                <p><strong>Statement Date:</strong> {current_date}</p>
                <br>
                <table border="1" style="width: 100%; border-collapse: collapse;">
                    <tr>
                        <th>Particulars</th>
                        <th>Amount (₹)</th>
                    </tr>
                    <tr>
                        <td>Loan Amount</td>
                        <td>{loan_amount}</td>
                    </tr>
                    <tr>
                        <td>Total Payable</td>
                        <td>{total_payable}</td>
                    </tr>
                    <tr>
                        <td>Paid Amount</td>
                        <td>{paid_amount}</td>
                    </tr>
                    <tr>
                        <td>Outstanding Amount</td>
                        <td>{outstanding_amount}</td>
                    </tr>
                </table>
                ';

            case 'noc':
                return '
                <h3>No Objection Certificate (NOC)</h3>
                <p><strong>Client Name:</strong> {client_name}</p>
                <p><strong>Loan Account Number:</strong> {loan_number}</p>
                <p><strong>Issue Date:</strong> {current_date}</p>
                <br>
                <p>This is to certify that Mr./Ms. {client_name} has successfully completed the repayment of loan account number {loan_number}.</p>
                <p>The total loan amount of ₹{loan_amount} along with applicable interest has been fully paid.</p>
                <p>We have no objection to the closure of this loan account.</p>
                <br>
                <p>This certificate is issued for all legal purposes.</p>
                ';

            case 'payment_receipt':
                return '
                <h3>Payment Receipt</h3>
                <p><strong>Client Name:</strong> {client_name}</p>
                <p><strong>Loan Account Number:</strong> {loan_number}</p>
                <p><strong>Payment Date:</strong> {payment_date}</p>
                <br>
                <table border="1" style="width: 100%; border-collapse: collapse;">
                    <tr>
                        <th>Particulars</th>
                        <th>Amount (₹)</th>
                    </tr>
                    <tr>
                        <td>Total Amount Paid</td>
                        <td>{paid_amount}</td>
                    </tr>
                    <tr>
                        <td>Outstanding Balance</td>
                        <td>{outstanding_amount}</td>
                    </tr>
                </table>
                <br>
                <p>Thank you for your payment.</p>
                ';

            default:
                return '
                <h3>' . ucfirst(str_replace('_', ' ', $documentType)) . '</h3>
                <p><strong>Client Name:</strong> {client_name}</p>
                <p><strong>Loan Account Number:</strong> {loan_number}</p>
                <p><strong>Date:</strong> {current_date}</p>
                <br>
                <p>This document is generated for loan account {loan_number} belonging to {client_name}.</p>
                ';
        }
    }

    /**
     * Generate EMI table HTML for repayment schedule
     */
    private function generateEmiTableHtml($loanAccount): array
    {
        $emis = $loanAccount->emis()->orderBy('instalment_number', 'asc')->get();
        
        // If no EMI records exist, calculate them dynamically
        if ($emis->isEmpty()) {
            return $this->calculateEmiScheduleDynamically($loanAccount);
        }
        
        $html = '';
        $openingPrincipal = $loanAccount->loan_amount;
        $totalInstallment = 0;
        $totalPrincipal = 0;
        $totalInterest = 0;
        
        foreach ($emis as $emi) {
            $closingPrincipal = $openingPrincipal - $emi->principal_amount;
            
            $html .= '<tr>';
            $html .= '<td>' . $emi->instalment_number . '</td>';
            $html .= '<td>' . ($emi->due_date ? $emi->due_date->format('d-m-Y') : 'N/A') . '</td>';
            $html .= '<td>₹ ' . number_format($emi->principal_amount, 2) . '</td>';
            $html .= '<td>₹ ' . number_format($emi->interest_amount, 2) . '</td>';
            $html .= '<td>₹ ' . number_format($emi->total_amount, 2) . '</td>';
            $html .= '</tr>';
            
            $openingPrincipal = $closingPrincipal;
            $totalInstallment += $emi->total_amount;
            $totalPrincipal += $emi->principal_amount;
            $totalInterest += $emi->interest_amount;
        }
        
        return [
            'rows' => $html,
            'total_installment_amount' => '₹ ' . number_format($totalInstallment, 2),
            'total_principal' => '₹ ' . number_format($totalPrincipal, 2),
            'total_interest' => '₹ ' . number_format($totalInterest, 2),
        ];
    }

    /**
     * Calculate EMI schedule dynamically when no EMI records exist
     */
    /**
     * Calculate EMI schedule dynamically using EmiCalculator service
     */
    private function calculateEmiScheduleDynamically($loanAccount): array
    {
        $principal = $loanAccount->loan_amount;
        $annualRate = $loanAccount->interest_rate;
        $tenureMonths = $loanAccount->tenure;
        $emiDay = $loanAccount->emi_day ?? optional($loanAccount->loanApplication)->emi_day;
        
        // Get generic start date
        $startDate = $loanAccount->disbursed_at ?? $loanAccount->created_at ?? now();

        // Handle Kandhuvatti / Interest-only (zero tenure)
        if ($tenureMonths <= 0) {
            $interestAmount = round($principal * ($annualRate / 100), 2);
            $formattedDate = \Carbon\Carbon::parse($startDate)->addMonth()->format('d-m-Y'); // Default next month for display
            
            $html = '<tr>';
            $html .= '<td>1</td>';
            $html .= '<td>' . $formattedDate . '</td>';
            $html .= '<td>₹ 0.00</td>'; // Principal is not paid in interest-only cycles
            $html .= '<td>₹ ' . number_format($interestAmount, 2) . '</td>';
            $html .= '<td>₹ ' . number_format($interestAmount, 2) . '</td>';
            $html .= '</tr>';

            return [
                'rows' => $html,
                'total_installment_amount' => 'Open Loan (₹ ' . number_format($interestAmount, 2) . ' per cycle)',
                'total_principal' => '₹ ' . number_format($principal, 2),
                'total_interest' => 'Dynamic',
            ];
        }

        $product = $loanAccount->loanApplication->product ?? null;
        $interestType = $product ? ($product->interest_type ?? 'flat') : 'flat';

        // Use EmiCalculator to ensure consistency with system logic
        $emiCalculator = new EmiCalculator();
        $scheduleData = $emiCalculator->generateSchedule(
            principal: (float)$principal,
            annualRate: (float)$annualRate,
            term: (int)$tenureMonths,
            startDate: $startDate,
            emiDay: $emiDay ? (int)$emiDay : null,
            interestType: $interestType
        );

        $html = '';
        foreach ($scheduleData['schedule'] as $item) {
            $formattedDate = \Carbon\Carbon::parse($item['due_date'])->format('d-m-Y');
            
            $html .= '<tr>';
            $html .= '<td>' . $item['month'] . '</td>';
            $html .= '<td>' . $formattedDate . '</td>';
            $html .= '<td>₹ ' . number_format($item['principal'], 2) . '</td>';
            $html .= '<td>₹ ' . number_format($item['interest'], 2) . '</td>';
            $html .= '<td>₹ ' . number_format($item['emi_amount'], 2) . '</td>';
            $html .= '</tr>';
        }
        
        return [
            'rows' => $html,
            'total_installment_amount' => '₹ ' . number_format($scheduleData['total_payment'], 2),
            'total_principal' => '₹ ' . number_format($principal, 2), // Should match principal usually
            'total_interest' => '₹ ' . number_format($scheduleData['total_interest'], 2),
        ];
    }
}
