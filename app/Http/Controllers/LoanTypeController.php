<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\LoanType;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class LoanTypeController extends Controller
{
    public function index(Request $request)
    {
        if ($request->ajax()) {
            $columns = [
                0 => 'id',
                1 => 'id',
                2 => 'name',
                3 => 'description',
                4 => 'status',
            ];

            // Total records without filtering
            $totalData = LoanType::count();
            $totalFiltered = $totalData;

            // DataTables parameters
            $limit = $request->input('length');
            $start = $request->input('start');
            $order = $columns[$request->input('order.0.column')] ?? 'id';
            $dir = $request->input('order.0.dir') ?? 'desc';

            // Build query
            $query = LoanType::query();

            // Search handling
            if (!empty($request->input('search.value'))) {
                $search = $request->input('search.value');

                $query->where(function ($q) use ($search) {
                    $q->where('name', 'LIKE', "%{$search}%")
                      ->orWhere('description', 'LIKE', "%{$search}%");
                });

                $totalFiltered = $query->count();
            }

            // Apply pagination and ordering
            $loanTypes = $query->offset($start)
                ->limit($limit)
                ->orderBy($order, $dir)
                ->get();

            $data = $loanTypes->map(function ($loanType) {
                return [
                    'id' => $loanType->id,
                    'name' => $loanType->name,
                    'description' => $loanType->description,
                    'icon' => $loanType->loan_type_icon ? asset('storage/' . $loanType->loan_type_icon) : null,
                    'image' => $loanType->loan_type_image ? asset('storage/' . $loanType->loan_type_image) : null,
                    'banner' => $loanType->loan_type_banner ? asset('storage/' . $loanType->loan_type_banner) : null,
                    'status' => $loanType->status ? 1 : 0,
                    'action' => ''
                ];
            });

            return response()->json([
                'draw' => intval($request->input('draw')),
                'recordsTotal' => intval($totalData),
                'recordsFiltered' => intval($totalFiltered),
                'data' => $data,
            ]);
        }

        $loanTypes = LoanType::all();
        return view('admin.setup-configuration.loan-types.loantypes', compact('loanTypes'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('admin.setup-configuration.loan-types.loantypes-create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try {
            $request->validate([
                'name' => 'required|string|max:255|unique:loan_types,name',
                'description' => 'required|string|min:5',
                'loan_type_icon' => 'nullable|image|mimes:jpeg,jpg,png,svg,webp|max:2048',
                'loan_type_image' => 'nullable|image|mimes:jpeg,jpg,png,svg,webp|max:2048',
                'loan_type_banner' => 'nullable|image|mimes:jpeg,jpg,png,svg,webp|max:2048'
            ], [

                'loan_type_icon.image' => 'The icon must be an image file.',
                'loan_type_image.image' => 'The image must be an image file.',
                'loan_type_banner.image' => 'The banner must be an image file.',
            ]);

            $data = [
                'name' => $request->name,
                'description' => $request->description,
                'status' => true
            ];

            // Handle icon upload
            if ($request->hasFile('loan_type_icon')) {
                $data['loan_type_icon'] = $this->handleFileUpload($request->file('loan_type_icon'), 'icon');
            }

            // Handle image upload
            if ($request->hasFile('loan_type_image')) {
                $data['loan_type_image'] = $this->handleFileUpload($request->file('loan_type_image'), 'image');
            }

            // Handle banner upload
            if ($request->hasFile('loan_type_banner')) {
                $data['loan_type_banner'] = $this->handleFileUpload($request->file('loan_type_banner'), 'banner');
            }

            LoanType::create($data);

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Loan type created successfully!'
                ]);
            }

            return redirect()->route('loan-types')->with('success', 'Loan type created successfully!');
        } catch (\Illuminate\Validation\ValidationException $e) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $e->errors()
                ], 422);
            }
            throw $e;
        } catch (\Exception $e) {
            Log::error('Loan Type Creation Error: ' . $e->getMessage());
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to create loan type: ' . $e->getMessage()
                ], 500);
            }
            return redirect()->back()->with('error', 'Failed to create loan type: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, string $id)
    {
        $loanType = LoanType::findOrFail($id);

        if ($request->ajax()) {
            return response()->json([
                'id' => $loanType->id,
                'name' => $loanType->name,
                'description' => $loanType->description,
                'icon' => $loanType->loan_type_icon ? asset('storage/' . $loanType->loan_type_icon) : null,
                'image' => $loanType->loan_type_image ? asset('storage/' . $loanType->loan_type_image) : null,
                'banner' => $loanType->loan_type_banner ? asset('storage/' . $loanType->loan_type_banner) : null,
                'icon_path' => $loanType->loan_type_icon,
                'image_path' => $loanType->loan_type_image,
                'banner_path' => $loanType->loan_type_banner,
                'status' => $loanType->status ? 1 : 0
            ]);
        } else {
            return view('admin.setup-configuration.loantypes-show', compact('loanType'));
        }
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:loan_types,name,' . $id,
            'description' => 'required|string|min:5',
            'loan_type_icon' => 'nullable|image|mimes:jpeg,jpg,png,svg,webp|max:2048',
            'loan_type_image' => 'nullable|image|mimes:jpeg,jpg,png,svg,webp|max:2048',
            'loan_type_banner' => 'nullable|image|mimes:jpeg,jpg,png,svg,webp|max:2048'
        ]);

        $loanType = LoanType::findOrFail($id);
        
        $data = [
            'name' => $request->name,
            'description' => $request->description
        ];

        // Handle icon upload
        if ($request->hasFile('loan_type_icon')) {
            $data['loan_type_icon'] = $this->handleFileUpload($request->file('loan_type_icon'), 'icon', $loanType->loan_type_icon);
        }

        // Handle image upload
        if ($request->hasFile('loan_type_image')) {
            $data['loan_type_image'] = $this->handleFileUpload($request->file('loan_type_image'), 'image', $loanType->loan_type_image);
        }

        // Handle banner upload
        if ($request->hasFile('loan_type_banner')) {
            $data['loan_type_banner'] = $this->handleFileUpload($request->file('loan_type_banner'), 'banner', $loanType->loan_type_banner);
        }

        $loanType->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Loan type updated successfully'
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function toggleStatus(Request $request, $id)
    {
        $loanType = LoanType::findOrFail($id);
        $loanType->status = $request->input('status', 0) ? 1 : 0;
        $loanType->save();
        return response()->json(['success' => true]);
    }

    public function destroy(string $id)
    {
        $loanType = LoanType::findOrFail($id);
        
        // Delete associated files
        if ($loanType->loan_type_icon) {
            Storage::disk('public')->delete($loanType->loan_type_icon);
        }
        if ($loanType->loan_type_image) {
            Storage::disk('public')->delete($loanType->loan_type_image);
        }
        if ($loanType->loan_type_banner) {
            Storage::disk('public')->delete($loanType->loan_type_banner);
        }
        
        $loanType->delete();
        return response()->json(['success' => true]);
    }

    /**
     * Handle file upload for loan type icon/image
     */
    private function handleFileUpload($file, $type, $oldFilePath = null)
    {
        // Delete old file if exists
        if ($oldFilePath && Storage::disk('public')->exists($oldFilePath)) {
            Storage::disk('public')->delete($oldFilePath);
        }

        // Create directory if it doesn't exist
        $directory = 'loan-types';
        if (!Storage::disk('public')->exists($directory)) {
            Storage::disk('public')->makeDirectory($directory);
        }

        // Generate unique filename
        $filename = time() . '_' . $type . '_' . $file->getClientOriginalName();
        
        // Store file
        $path = $file->storeAs($directory, $filename, 'public');
        
        return $path;
    }
}
