<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $request->validate(['password' => 'required|string']);

        $storedHash = Setting::get('app_password');

        if (!$storedHash || !Hash::check($request->password, $storedHash)) {
            return response()->json(['message' => 'Неверный пароль'], 401);
        }

        // Generate or return existing token
        $token = Setting::get('api_token');
        if (!$token) {
            $token = Str::random(64);
            Setting::set('api_token', $token);
        }

        return response()->json(['token' => $token]);
    }

    public function logout(): JsonResponse
    {
        Setting::set('api_token', null);
        return response()->json(['message' => 'Logged out']);
    }

    public function check(): JsonResponse
    {
        return response()->json(['authenticated' => true]);
    }
}
