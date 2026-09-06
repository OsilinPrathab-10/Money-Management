<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\Contact;
use Illuminate\Support\Facades\DB;

class ContactControllerApi extends Controller
{
    public function send(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name'    => 'required|string|max:100',
            'phone'   => 'required|string|max:20',
            'message' => 'required|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        DB::table('contacts')->insert([
            'name'    => $request->name,
            'phone'   => $request->phone,
            'message' => $request->message,
            'created_at' => now(),
        ]);

        // Return JSON response
        return response()->json([
            'status' => true,
            'message' => 'Thank you for contacting us!',
        ], 200);
    }
}
