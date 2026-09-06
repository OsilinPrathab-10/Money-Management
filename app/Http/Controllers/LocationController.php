<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Models\State;
use App\Models\District;
use App\Services\CityApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class LocationController extends Controller
{
    protected $cityApiService;

    public function __construct(CityApiService $cityApiService)
    {
        $this->cityApiService = $cityApiService;
    }

    /**
     * Display location management page (Tabbed)
     */
    public function index(): View
    {
        $totalLocations = Location::count();
        $totalDistricts = District::count();
        $totalStates = State::count();

        // Local data for dropdowns
        $localStates = State::orderBy('name')->get();

        return view('admin.location.index', [
            'totalLocations' => $totalLocations,
            'totalDistricts' => $totalDistricts,
            'totalStates'    => $totalStates,
            'states'         => $localStates,
        ]);
    }

    /* =========================================================================
     * VILLAGE / AREA METHODS
     * ========================================================================= */

    public function getData(Request $request): JsonResponse
    {
        $columns = [0 => 'id', 1 => 'name', 2 => 'city', 3 => 'state', 4 => 'pincode'];
        $totalData = Location::count();
        $totalFiltered = $totalData;

        $limit = $request->input('length');
        $start = $request->input('start');
        $orderColumnIndex = $request->input('order.0.column');
        $order = $columns[$orderColumnIndex] ?? 'id';
        $dir = $request->input('order.0.dir') ?? 'asc';

        $query = Location::with(['state', 'district']);

        if (!empty($request->input('search.value'))) {
            $search = $request->input('search.value');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                  ->orWhere('city', 'LIKE', "%{$search}%")
                  ->orWhere('state', 'LIKE', "%{$search}%")
                  ->orWhere('pincode', 'LIKE', "%{$search}%");
            });
            $totalFiltered = $query->count();
        }

        $locations = $query->offset($start)->limit($limit)->orderBy($order, $dir)->get();

        $data = [];
        foreach ($locations as $index => $location) {
            $data[] = [
                'id' => $location->id,
                's_no' => $start + $index + 1,
                'name' => $location->name,
                'city' => $location->district ? $location->district->name : $location->city,
                'state' => ($location->state instanceof \App\Models\State) ? $location->state->name : ($location->state ?? 'N/A'),
                'pincode' => $location->pincode ?? 'N/A',
            ];
        }

        return response()->json([
            'draw' => intval($request->input('draw')),
            'recordsTotal' => intval($totalData),
            'recordsFiltered' => intval($totalFiltered),
            'data' => $data,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'state_id' => 'required|exists:states,id',
            'district_id' => 'required|exists:districts,id',
            'pincode' => 'nullable|digits:6',
        ]);

        try {
            $state = State::find($request->state_id);
            $district = District::find($request->district_id);

            $location = Location::updateOrCreate(
                ['name' => $request->name, 'district_id' => $request->district_id],
                [
                    'state_id' => $request->state_id,
                    'city' => $district->name,
                    'state' => $state->name,
                    'pincode' => $request->pincode,
                ]
            );

            return response()->json(['success' => true, 'message' => 'Zone added successfully']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /* =========================================================================
     * DISTRICT METHODS
     * ========================================================================= */

    public function getDistrictData(Request $request): JsonResponse
    {
        $columns = [0 => 'id', 1 => 'name', 2 => 'state_id'];
        $order = $columns[$request->input('order.0.column')] ?? 'id';
        $dir = $request->input('order.0.dir') ?? 'desc';

        $totalData = District::count();
        $query = District::with('state');
        $totalFiltered = $totalData;

        if (!empty($request->input('search.value'))) {
            $search = $request->input('search.value');
            $query->where('name', 'LIKE', "%{$search}%")
                  ->orWhereHas('state', function($q) use ($search) { $q->where('name', 'LIKE', "%{$search}%"); });
            $totalFiltered = $query->count();
        }

        $districts = $query->offset($request->start)->limit($request->length)->orderBy($order, $dir)->get();
        $data = [];
        foreach ($districts as $index => $d) {
            $data[] = [
                'id' => $d->id,
                's_no' => $request->start + $index + 1,
                'name' => $d->name,
                'state' => $d->state->name ?? 'N/A',
                'state_id' => $d->state_id,
            ];
        }

        return response()->json(['draw' => intval($request->draw), 'recordsTotal' => $totalData, 'recordsFiltered' => $totalFiltered, 'data' => $data]);
    }

    public function storeDistrict(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'state_id' => 'required|exists:states,id',
            'name'     => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => $validator->errors()->first()], 422);
        }

        try {
            $district = District::updateOrCreate(
                ['state_id' => $request->state_id, 'name' => $request->name],
                ['state_id' => $request->state_id, 'name' => $request->name]
            );
            return response()->json(['success' => true, 'message' => 'District saved successfully', 'district' => $district]);
        } catch (\Exception $e) {
            Log::error('storeDistrict error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function updateDistrict(Request $request, $id): JsonResponse
    {
        $request->validate([
            'state_id' => 'required|exists:states,id',
            'name'     => 'required|string|max:255',
        ]);

        try {
            $district = District::findOrFail($id);
            $district->update([
                'state_id' => $request->state_id,
                'name'     => $request->name,
            ]);
            return response()->json(['success' => true, 'message' => 'District updated successfully']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /* =========================================================================
     * STATE METHODS
     * ========================================================================= */

    public function getStateData(Request $request): JsonResponse
    {
        $columns = [0 => 'id', 1 => 'name'];
        $order = $columns[$request->input('order.0.column')] ?? 'id';
        $dir = $request->input('order.0.dir') ?? 'desc';

        $totalData = State::count();
        $query = State::query();
        $totalFiltered = $totalData;

        if (!empty($request->input('search.value'))) {
            $search = $request->input('search.value');
            $query->where('name', 'LIKE', "%{$search}%");
            $totalFiltered = $query->count();
        }

        $states = $query->offset($request->start)->limit($request->length)->orderBy($order, $dir)->get();
        $data = [];
        foreach ($states as $index => $s) {
            $data[] = [
                'id' => $s->id,
                's_no' => $request->start + $index + 1,
                'name' => $s->name
            ];
        }

        return response()->json(['draw' => intval($request->draw), 'recordsTotal' => $totalData, 'recordsFiltered' => $totalFiltered, 'data' => $data]);
    }

    public function storeState(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|unique:states,name'
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => $validator->errors()->first()], 422);
        }

        State::create($request->all());
        return response()->json(['success' => true, 'message' => 'State saved successfully']);
    }

    public function updateState(Request $request, $id): JsonResponse
    {
        $request->validate(['name' => 'required|string|unique:states,name,' . $id]);
        try {
            $state = State::findOrFail($id);
            $state->update(['name' => $request->name]);
            return response()->json(['success' => true, 'message' => 'State updated successfully']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function getVillageData($id): JsonResponse
    {
        $village = Location::findOrFail($id);
        return response()->json([
            'success' => true, 
            'village' => [
                'id' => $village->id,
                'name' => $village->name,
                'district_id' => $village->district_id,
                'state_id' => $village->state_id
            ]
        ]);
    }

    public function updateVillage(Request $request, $id): JsonResponse
    {
        $request->validate([
            'name' => 'required|string',
            'district_id' => 'required|exists:districts,id',
            'state_id' => 'required|exists:states,id'
        ]);
        try {
            $village = Location::findOrFail($id);
            $district = District::findOrFail($request->district_id);
            $state = State::findOrFail($request->state_id);
            
            $village->update([
                'name' => $request->name,
                'district_id' => $request->district_id,
                'state_id' => $request->state_id,
                'city' => $district->name,
                'state' => $state->name
            ]);
            return response()->json(['success' => true, 'message' => 'Zone updated successfully']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /* =========================================================================
     * UTILITIES & FETCH
     * ========================================================================= */

    public function fetchLocations(Request $request): JsonResponse
    {
        $request->validate(['state' => 'required|string', 'district' => 'required|string']);
        $stateName = strtoupper(trim($request->state));
        $districtName = ucfirst(strtolower(trim($request->district)));

        try {
            DB::beginTransaction();
            // 1. Ensure local state exists
            $stateObj = State::firstOrCreate(['name' => $stateName]);
            // 2. Ensure local district exists
            $districtObj = District::firstOrCreate(['state_id' => $stateObj->id, 'name' => $districtName]);

            $talukas = $this->cityApiService->getTalukas($stateName, $districtName);
            if (empty($talukas)) {
                DB::rollBack();
                return response()->json(['success' => false, 'message' => "No data found for $districtName"], 404);
            }

            $storedCount = 0;
            foreach ($talukas as $taluka) {
                $villages = $this->cityApiService->fetchVillages($stateName, $districtName, $taluka['name']);
                foreach ($villages as $village) {
                    Location::updateOrCreate(
                        ['name' => $village['name'], 'district_id' => $districtObj->id],
                        [
                            'state_id' => $stateObj->id,
                            'city' => $districtName,
                            'state' => $stateName,
                        ]
                    );
                    $storedCount++;
                }
            }

            DB::commit();
            return response()->json(['success' => true, 'message' => "Successfully fetched $storedCount locations."]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function getDistrictsLocal($stateId): JsonResponse
    {
        $districts = District::where('state_id', $stateId)->orderBy('name')->get();
        return response()->json(['success' => true, 'districts' => $districts]);
    }

    public function destroy($type, $id): JsonResponse
    {
        try {
            if ($type == 'state') State::findOrFail($id)->delete();
            elseif ($type == 'district') District::findOrFail($id)->delete();
            else Location::findOrFail($id)->delete();
            return response()->json(['success' => true, 'message' => 'Deleted successfully']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function getDistricts(Request $request): JsonResponse
    {
        $request->validate(['state' => 'required|string']);
        try {
            $districts = $this->cityApiService->getDistricts($request->state);
            return response()->json(['success' => true, 'districts' => $districts]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function getStatesApi(): JsonResponse
    {
        try {
            $states = $this->cityApiService->getStates();
            return response()->json(['success' => true, 'states' => $states]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}
