<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class SettingsController extends Controller
{
    public function index(): JsonResponse
    {
        $settings = Setting::whereNotIn('key', ['app_password', 'api_token', 'ai_api_key'])
            ->get()
            ->pluck('value', 'key');

        // Indicate whether an AI key is stored without exposing it
        $settings['ai_api_key_set'] = (bool) Setting::get('ai_api_key');

        return response()->json($settings);
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'currency'        => 'sometimes|string|max:10',
            'currency_symbol' => 'sometimes|string|max:5',
            'new_password'    => 'sometimes|string|min:4',
            'initial_balance' => 'sometimes|numeric|min:0',
            'ai_provider'     => 'sometimes|string|in:anthropic,openai,groq',
            'ai_api_key'      => 'sometimes|string|max:500',
            'ai_model'        => 'sometimes|string|max:100',
        ]);

        foreach (['currency', 'currency_symbol', 'initial_balance', 'ai_provider', 'ai_api_key', 'ai_model'] as $key) {
            if (isset($data[$key])) {
                Setting::set($key, $data[$key]);
            }
        }

        if (isset($data['new_password'])) {
            Setting::set('app_password', Hash::make($data['new_password']));
            Setting::set('api_token', null);
        }

        return response()->json(['message' => 'Настройки сохранены']);
    }
}
