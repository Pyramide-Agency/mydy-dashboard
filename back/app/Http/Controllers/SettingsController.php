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
        $settings = Setting::whereNotIn('key', ['app_password', 'api_token', 'ai_api_key', 'groq_api_key', 'jina_api_key'])
            ->get()
            ->pluck('value', 'key');

        // Indicate whether keys are stored without exposing them
        $settings['ai_api_key_set']   = (bool) Setting::get('ai_api_key');
        $settings['groq_api_key_set'] = (bool) Setting::get('groq_api_key');
        $settings['jina_api_key_set'] = (bool) Setting::get('jina_api_key');

        // Indicate whether Telegram is connected
        $settings['telegram_connected'] = (bool) Setting::get('telegram_bot_token');

        return $this->success($settings->toArray());
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'currency'                => 'sometimes|string|max:10',
            'currency_symbol'         => 'sometimes|string|max:5',
            'new_password'            => 'sometimes|string|min:4',
            'initial_balance'         => 'sometimes|numeric|min:0',
            'ai_provider'             => 'sometimes|string|in:anthropic,openai,groq',
            'ai_api_key'              => 'sometimes|string|max:500',
            'ai_model'                => 'sometimes|string|max:100',
            'groq_api_key'            => 'sometimes|string|max:500',
            'jina_api_key'            => 'sometimes|string|max:500',
            'deadline_notifications'  => 'sometimes|boolean',
        ]);

        foreach ([
            'currency', 'currency_symbol', 'initial_balance',
            'ai_provider', 'ai_api_key', 'ai_model',
            'groq_api_key', 'jina_api_key',
        ] as $key) {
            if (isset($data[$key])) {
                Setting::set($key, $data[$key]);
            }
        }

        if (isset($data['deadline_notifications'])) {
            Setting::set('deadline_notifications', $data['deadline_notifications'] ? '1' : '0');
        }

        if (isset($data['new_password'])) {
            Setting::set('app_password', Hash::make($data['new_password']));
            Setting::set('api_token', null);
        }

        return $this->success(message: 'Настройки сохранены');
    }
}
