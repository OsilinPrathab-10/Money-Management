<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class SessionLockController extends Controller
{
    public function ping(Request $request): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'user' => $request->user()->email,
        ]);
    }

    public function unlock(Request $request): JsonResponse
    {
        $request->validate([
            'password' => ['required', 'string'],
        ]);

        if (! Auth::guard('web')->validate([
            'email' => $request->user()->email,
            'password' => $request->password,
        ])) {
            throw ValidationException::withMessages([
                'password' => __('The password you entered is incorrect.'),
            ]);
        }

        $request->session()->put('auth.password_confirmed_at', time());
        $request->session()->regenerateToken();

        return response()->json([
            'ok' => true,
            'csrf_token' => csrf_token(),
        ]);
    }
}
