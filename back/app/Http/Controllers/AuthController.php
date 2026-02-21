<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $request->validate(['password' => 'required|string']);

        try {
            $storedHash = Setting::get('app_password');
            Log::info('[auth.login] app_password found: ' . ($storedHash ? 'yes' : 'NO'));

            if (!$storedHash || !Hash::check($request->password, $storedHash)) {
                return $this->error('Неверный пароль', 401);
            }

            // Generate or return existing token
            $token = Setting::get('api_token');
            if (!$token) {
                $token = Str::random(64);
                Setting::set('api_token', $token);
            }

            return $this->success(['token' => $token], 'Вход выполнен');
        } catch (\Throwable $e) {
            Log::error('[auth.login] Exception: ' . $e->getMessage(), [
                'file'  => $e->getFile() . ':' . $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->error('Server error: ' . $e->getMessage(), 500);
        }
    }

    public function logout(): JsonResponse
    {
        Setting::set('api_token', null);
        return $this->success(message: 'Выход выполнен');
    }

    public function check(): JsonResponse
    {
        return $this->success(['authenticated' => true]);
    }
}
