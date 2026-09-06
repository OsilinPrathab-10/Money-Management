<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\SmsTemplate;
use App\Models\EmailTemplate;
use App\Models\WhatsappTemplate;
use App\Services\GallaboxService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TemplateController extends Controller
{
    public function smsTemplateIndex()
    {
        $smsTemplates = SmsTemplate::orderBy('id', 'desc')->get();
        return view('admin.templates.sms-templates.sms-template', compact('smsTemplates'));
    }

    /**
     * Show create SMS Template form
     */
    public function smsTemplateCreate()
    {
        return view('admin.templates.sms-templates.sms-template-edit', [
            'templateId' => null,
            'templateName' => '',
            'templateIdentifier' => '',
            'templateIdValue' => '',
            'templateBody' => '',
            'templateStatus' => true
        ]);
    }

    /**
     * Store new SMS Template
     */
    public function smsTemplateStore(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'identifier' => 'required|string|unique:sms_templates,identifier',
            'template_id' => 'nullable|string|max:255',
            'sms_body' => 'required|string',
            'status' => 'boolean',
        ]);

        try {
            SmsTemplate::create([
                'name' => $request->name,
                'identifier' => $request->identifier,
                'template_id' => $request->template_id,
                'sms_body' => $request->sms_body,
                'status' => $request->has('status') ? true : false
            ]);

            return redirect()->route('sms-template-index')->with('success', 'SMS Template created successfully');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to create SMS Template. Please try again.');
        }
    }

    /**
     * Show edit SMS Template form
     */
    public function smsTemplateEdit($id)
    {
        $template = SmsTemplate::findOrFail($id);
        
        return view('admin.templates.sms-templates.sms-template-edit', [
            'templateId' => $template->id,
            'templateName' => $template->name,
            'templateIdentifier' => $template->identifier,
            'templateIdValue' => $template->template_id,
            'templateBody' => $template->sms_body,
            'templateStatus' => $template->status
        ]);
    }

    /**
     * Update SMS Template
     */
    public function smsTemplateUpdate(Request $request, $id)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'identifier' => 'required|string|unique:sms_templates,identifier,' . $id,
            'template_id' => 'nullable|string|max:255',
            'sms_body' => 'required|string',
            'status' => 'boolean',
        ]);

        try {
            $template = SmsTemplate::findOrFail($id);
            $template->update([
                'name' => $request->name,
                'identifier' => $request->identifier,
                'template_id' => $request->template_id,
                'sms_body' => $request->sms_body,
                'status' => $request->has('status') ? true : false
            ]);

            return redirect()->route('sms-template-index')->with('success', 'SMS Template updated successfully');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to update SMS Template. Please try again.');
        }
    }

    /**
     * Delete SMS Template
     */
    public function smsTemplateDestroy($id)
    {
        try {
            $template = SmsTemplate::findOrFail($id);
            $template->delete();

            return response()->json([
                'success' => true,
                'message' => 'SMS Template deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete SMS Template. Please try again.'
            ], 500);
        }
    }

    // ==================== EMAIL TEMPLATE METHODS ====================

    /**
     * Display Email Templates listing page
     */
    public function emailTemplateIndex()
    {
        $emailTemplates = EmailTemplate::orderBy('id', 'asc')->get();
        return view('admin.templates.email-templates.email-template', compact('emailTemplates'));
    }

    /**
     * Show create Email Template form
     */
    public function emailTemplateCreate()
    {
        return view('admin.templates.email-templates.email-template-edit', [
            'templateId' => null,
            'templateName' => '',
            'templateIdentifier' => '',
            'templateSubject' => '',
            'templateBody' => '',
            'templateImage' => null,
            'templateStatus' => true
        ]);
    }

    /**
     * Store new Email Template
     */
    public function emailTemplateStore(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'identifier' => 'required|string|unique:email_templates,identifier',
            'subject' => 'required|string|max:255',
            'email_body' => 'required|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'status' => 'boolean',
        ]);

        try {
            $data = [
                'name' => $request->name,
                'identifier' => $request->identifier,
                'subject' => $request->subject,
                'email_body' => $request->email_body,
                'status' => $request->has('status') ? true : false
            ];

            // Handle image upload
            if ($request->hasFile('image')) {
                $imagePath = $request->file('image')->store('email-templates', 'public');
                $data['image_path'] = $imagePath;
            }

            EmailTemplate::create($data);

            return redirect()->route('email-template-index')->with('success', 'Email Template created successfully');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to create Email Template. Please try again.');
        }
    }

    /**
     * Show edit Email Template form
     */
    public function emailTemplateEdit($id)
    {
        $template = EmailTemplate::findOrFail($id);
        
        return view('admin.templates.email-templates.email-template-edit', [
            'templateId' => $template->id,
            'templateName' => $template->name,
            'templateIdentifier' => $template->identifier,
            'templateSubject' => $template->subject,
            'templateBody' => $template->email_body,
            'templateImage' => $template->image_path,
            'templateStatus' => $template->status
        ]);
    }

    /**
     * Update Email Template
     */
    public function emailTemplateUpdate(Request $request, $id)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'identifier' => 'required|string|unique:email_templates,identifier,' . $id,
            'subject' => 'required|string|max:255',
            'email_body' => 'required|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'status' => 'boolean',
        ]);

        try {
            $template = EmailTemplate::findOrFail($id);
            
            $data = [
                'name' => $request->name,
                'identifier' => $request->identifier,
                'subject' => $request->subject,
                'email_body' => $request->email_body,
                'status' => $request->has('status') ? true : false
            ];

            // Handle image upload
            if ($request->hasFile('image')) {
                // Delete old image if exists
                if ($template->image_path) {
                    Storage::disk('public')->delete($template->image_path);
                }
                
                $imagePath = $request->file('image')->store('email-templates', 'public');
                $data['image_path'] = $imagePath;
            }

            $template->update($data);

            return redirect()->route('email-template-index')->with('success', 'Email Template updated successfully');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to update Email Template. Please try again.');
        }
    }

    /**
     * Delete Email Template
     */
    public function emailTemplateDestroy($id)
    {
        try {
            $template = EmailTemplate::findOrFail($id);
            
            // Delete image if exists
            if ($template->image_path) {
                Storage::disk('public')->delete($template->image_path);
            }
            
            $template->delete();

            return response()->json([
                'success' => true,
                'message' => 'Email Template deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete Email Template. Please try again.'
            ], 500);
        }
    }


    public function whatsappTemplateIndex()
    {
        $whatsappTemplates = WhatsappTemplate::orderBy('template_name', 'asc')->get();
        return view('admin.templates.whatsapp-templates.whatsapp-template', compact('whatsappTemplates'));
    }

    /**
     * Show create WhatsApp Template form
     */
    public function whatsappTemplateCreate(Request $request)
    {
        // Get template name from query parameter if provided (from Gallabox fetch)
        $templateName = $request->query('template_name', '');
        $language = $request->query('language', 'en');
        
        return view('admin.templates.whatsapp-templates.whatsapp-template-edit', [
            'template' => null,
            'prefillTemplateName' => $templateName,
            'prefillLanguage' => $language
        ]);
    }

    /**
     * Store new WhatsApp Template
     */
    public function whatsappTemplateStore(Request $request)
    {
        $validated = $request->validate([
            'template_name' => 'required|string|max:255',
            'event_type' => 'required|string|max:255',
            'provider' => 'required|string|max:255',
            'provider_template_name' => 'required|string|max:255',
        ]);

        // Validate variables separately if provided
        if ($request->filled('variables')) {
            $request->validate([
                'variables' => 'json'
            ]);
        }

        try {
            // Log incoming data for debugging
            Log::info('WhatsApp Template Store Request:', [
                'template_name' => $request->template_name,
                'event_type' => $request->event_type,
                'provider' => $request->provider,
                'provider_template_name' => $request->provider_template_name,
                'variables_raw' => $request->variables,
                'is_active' => $request->has('is_active')
            ]);

            $variablesData = null;
            if ($request->filled('variables')) {
                $variablesData = json_decode($request->variables, true);
                Log::info('Decoded variables:', ['variables' => $variablesData]);
            }

            WhatsappTemplate::create([
                'template_name' => $request->template_name,
                'event_type' => $request->event_type,
                'provider' => $request->provider,
                'provider_template_name' => $request->provider_template_name,
                'variables' => $variablesData,
                'is_active' => $request->has('is_active') ? true : false
            ]);

            return redirect()->route('whatsapp-template-index')->with('success', 'WhatsApp Template created successfully');
        } catch (\Exception $e) {
            Log::error('WhatsApp Template Store Error:', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return redirect()->back()->with('error', 'Failed to create WhatsApp Template: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Show edit WhatsApp Template form
     */
    public function whatsappTemplateEdit($id)
    {
        $template = WhatsappTemplate::findOrFail($id);
        return view('admin.templates.whatsapp-templates.whatsapp-template-edit', compact('template'));
    }

    /**
     * Update WhatsApp Template
     */
    public function whatsappTemplateUpdate(Request $request, $id)
    {
        $validated = $request->validate([
            'template_name' => 'required|string|max:255',
            'event_type' => 'required|string|max:255',
            'provider' => 'required|string|max:255',
            'provider_template_name' => 'required|string|max:255',
        ]);

        // Validate variables separately if provided
        if ($request->filled('variables')) {
            $request->validate([
                'variables' => 'json'
            ]);
        }

        try {
            $template = WhatsappTemplate::findOrFail($id);
            $template->update([
                'template_name' => $request->template_name,
                'event_type' => $request->event_type,
                'provider' => $request->provider,
                'provider_template_name' => $request->provider_template_name,
                'variables' => $request->variables ? json_decode($request->variables, true) : null,
                'is_active' => $request->has('is_active') ? true : false
            ]);

            return redirect()->route('whatsapp-template-index')->with('success', 'WhatsApp Template updated successfully');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to update WhatsApp Template: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Show WhatsApp Template details (read-only)
     */
    public function whatsappTemplateView($id)
    {
        $template = WhatsappTemplate::findOrFail($id);
        return view('admin.templates.whatsapp-templates.whatsapp-template-view', compact('template'));
    }

    /**
     * Toggle WhatsApp Template status
     */
    public function whatsappTemplateToggleStatus($id)
    {
        try {
            $template = WhatsappTemplate::findOrFail($id);
            $template->is_active = !$template->is_active;
            $template->save();

            return response()->json([
                'success' => true,
                'message' => 'Template status updated successfully',
                'is_active' => $template->is_active
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update template status'
            ], 500);
        }
    }

    /**
     * Delete WhatsApp Template
     */
    public function whatsappTemplateDestroy($id)
    {
        try {
            $template = WhatsappTemplate::findOrFail($id);
            $templateName = $template->template_name;
            $template->delete();

            return response()->json([
                'success' => true,
                'message' => "Template '{$templateName}' deleted successfully"
            ]);
        } catch (\Exception $e) {
            Log::error('WhatsApp Template Delete Error:', [
                'id' => $id,
                'message' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete template'
            ], 500);
        }
    }

    /**
     * Fetch templates from Gallabox API
     */
    public function fetchGallaboxTemplates()
    {
        try {
            $gallaboxService = new GallaboxService();
            $templates = $gallaboxService->fetchTemplates();

            return response()->json([
                'success' => true,
                'templates' => $templates,
                'count' => count($templates)
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch Gallabox templates in controller', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch templates from Gallabox: ' . $e->getMessage()
            ], 500);
        }
    }
}
