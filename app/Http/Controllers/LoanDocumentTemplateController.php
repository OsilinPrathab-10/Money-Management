<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\LoanDocumentTemplate;
use App\Models\Loan;
use App\Models\LoanAccount;
use App\Models\Appearance;
use App\Services\TemplateRenderer;
use App\Services\DocumentPlaceholderService;
use Illuminate\Support\Facades\Storage;
use Barryvdh\DomPDF\Facade\Pdf;

class LoanDocumentTemplateController extends Controller
{
    public function index()
    {
        $templates = LoanDocumentTemplate::orderBy('type')->orderBy('title')->get();
        return view('admin.setup-configuration.loan-document-template.loan-document-template', compact('templates'));
    }

    public function create()
    {
        $loanProducts = \App\Models\LoanProduct::select('id', 'loan_name')->orderBy('loan_name')->get();
        return view('admin.setup-configuration.loan-document-template.loan-document-template-create', compact('loanProducts'));
    }

    public function store(Request $request)
    {
        try {
            $request->validate([
                'title' => 'required|string|max:255|unique:loan_document_templates,title',
                'type' => [
                    'required',
                    'string',
                    function ($attribute, $value, $fail) use ($request) {
                        // If it's not a loan agreement, it must be unique
                        if ($value !== 'loan_agreement') {
                            if (LoanDocumentTemplate::where('type', $value)->exists()) {
                                $fail('This document type has already been taken.');
                            }
                        }
                    }
                ],
                'body' => 'required|string',
                'loan_product_id' => 'required_if:type,loan_agreement|nullable|exists:loan_products,id',
            ]);

            $data = $request->only('title', 'type', 'body');

            // If it's a loan agreement with a specific product, modify the type
            if ($request->type === 'loan_agreement' && $request->loan_product_id) {
                $loanProduct = \App\Models\LoanProduct::find($request->loan_product_id);
                if ($loanProduct) {
                    $computedType = 'loan_agreement_' . strtolower(str_replace(' ', '_', $loanProduct->loan_name));
                    
                    // Check if this computed type already exists
                    if (LoanDocumentTemplate::where('type', $computedType)->exists()) {
                        return redirect()->back()
                            ->withErrors(['type' => 'A template for this loan product already exists.'])
                            ->withInput();
                    }
                    
                    $data['type'] = $computedType;
                }
            }

            LoanDocumentTemplate::create($data);

            return redirect()->route('loan-document-templates.index')->with('success', 'Template created successfully');
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            return redirect()->back()
                ->withErrors(['error' => 'An unexpected error occurred: ' . $e->getMessage()])
                ->withInput();
        }
    }

    public function show(LoanDocumentTemplate $loanDocumentTemplate)
    {
        return view('admin.setup-configuration.loan-document-template.loan-document-template-view', [
            'template' => $loanDocumentTemplate
        ]);
    }

    public function edit(LoanDocumentTemplate $loanDocumentTemplate)
    {
        $loanProducts = \App\Models\LoanProduct::select('id', 'loan_name')->orderBy('loan_name')->get();
        return view('admin.setup-configuration.loan-document-template.loan-document-template-edit', [
            'template' => $loanDocumentTemplate,
            'loanProducts' => $loanProducts
        ]);
    }

    public function update(Request $request, LoanDocumentTemplate $loanDocumentTemplate)
    {
        try {
            $request->validate([
                'title' => 'required|string|max:255|unique:loan_document_templates,title,' . $loanDocumentTemplate->id,
                'type' => [
                    'required',
                    'string',
                    function ($attribute, $value, $fail) use ($request, $loanDocumentTemplate) {
                        if ($value !== 'loan_agreement') {
                            if (LoanDocumentTemplate::where('type', $value)->where('id', '!=', $loanDocumentTemplate->id)->exists()) {
                                $fail('This document type has already been taken.');
                            }
                        }
                    }
                ],
                'body' => 'required|string',
                'loan_product_id' => 'required_if:type,loan_agreement|nullable|exists:loan_products,id',
            ]);

            $data = $request->only('title', 'type', 'body');

            // If it's a loan agreement with a specific product, modify the type
            if ($request->type === 'loan_agreement' && $request->loan_product_id) {
                $loanProduct = \App\Models\LoanProduct::find($request->loan_product_id);
                if ($loanProduct) {
                    $computedType = 'loan_agreement_' . strtolower(str_replace(' ', '_', $loanProduct->loan_name));
                    
                    // Check if this computed type already exists (excluding current template)
                    if (LoanDocumentTemplate::where('type', $computedType)->where('id', '!=', $loanDocumentTemplate->id)->exists()) {
                        return redirect()->back()
                            ->withErrors(['type' => 'A template for this loan product already exists.'])
                            ->withInput();
                    }
                    
                    $data['type'] = $computedType;
                }
            }

            $loanDocumentTemplate->update($data);

            return redirect()->route('loan-document-templates.index')->with('success', 'Template updated successfully');
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            return redirect()->back()
                ->withErrors(['error' => 'An unexpected error occurred: ' . $e->getMessage()])
                ->withInput();
        }
    }

    public function destroy(LoanDocumentTemplate $loanDocumentTemplate)
    {
        try {
            $loanDocumentTemplate->delete();

            return response()->json([
                'success' => true,
                'message' => 'Template deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete template. Please try again.'
            ], 500);
        }
    }

    public function generate($loanAccountId, $documentType)
    {
        $loan = LoanAccount::findOrFail($loanAccountId);
        $template = LoanDocumentTemplate::where('type', $documentType)->first();
        
        if (!$template && $documentType === 'loan_agreement') {
            $loanProduct = $loan->loanApplication->product ?? null;
            if ($loanProduct) {
                $productSpecificType = 'loan_agreement_' . strtolower(str_replace(' ', '_', $loanProduct->loan_name ?? ''));
                $template = LoanDocumentTemplate::where('type', $productSpecificType)->first();
            }
        }
        
        if (!$template) {
            abort(404, 'Document template not found.');
        }

        // Use centralized placeholder service
        $replacements = DocumentPlaceholderService::getReplacements($loan);

        // Handle both regular and HTML-encoded placeholders
        $templateHeader = html_entity_decode($template->header ?? '', ENT_QUOTES, 'UTF-8');
        $templateFooter = html_entity_decode($template->footer ?? '', ENT_QUOTES, 'UTF-8');
        $templateBody = html_entity_decode($template->body ?? '', ENT_QUOTES, 'UTF-8');

        // Normalize placeholders in template (convert single brace to double brace for consistency if needed, 
        // but better to just support both or ensure seeder uses double)
        // For now, we'll just replace the double brace keys. 
        // If seeder uses single braces, we should fix the seeder.

        $header = DocumentPlaceholderService::replacePlaceholders($templateHeader, $replacements);
        $footer = DocumentPlaceholderService::replacePlaceholders($templateFooter, $replacements);
        $body   = DocumentPlaceholderService::replacePlaceholders($templateBody, $replacements);

        // Use logo from appearances table instead of template logo
        $appearance = \App\Models\Appearance::where('type', 'web')->first();
        $logo = null;
        $is_base64 = false;

        if ($appearance && $appearance->logo) {
            $logoPath = $appearance->logo;
            $candidatePaths = [
                storage_path('app/public/' . $logoPath),
                public_path('storage/' . $logoPath),
                public_path($logoPath)
            ];

            foreach ($candidatePaths as $path) {
                if (file_exists($path) && is_file($path)) {
                    try {
                        $type = pathinfo($path, PATHINFO_EXTENSION);
                        $data = file_get_contents($path);
                        $logo = 'data:image/' . $type . ';base64,' . base64_encode($data);
                        $is_base64 = true;
                        break;
                    } catch (\Exception $e) {
                        \Illuminate\Support\Facades\Log::warning('Failed to base64 encode logo: ' . $e->getMessage());
                    }
                }
            }
            
            if (!$is_base64) {
                $logo = asset('storage/' . $logoPath);
            }
        }

        $pdf = PDF::loadView('pdf.dynamic_document', [
            'header' => $header,
            'footer' => $footer,
            'body' => $body,
            'logo' => $logo,
            'is_base64' => $is_base64,
            'loan' => $loan,
        ])->setOptions([
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

        $pdf->setPaper('A4', 'portrait');

        $fileName = "{$documentType}_{$loan->account_number}.pdf";
        $filePath = "documents/{$fileName}";

        Storage::disk('public')->put($filePath, $pdf->output());

        return response()->json([
            'success' => true,
            'message' => ucfirst($documentType) . ' PDF created successfully.',
            'pdf_url' => asset('storage/' . $filePath),
        ]);
    }
}
