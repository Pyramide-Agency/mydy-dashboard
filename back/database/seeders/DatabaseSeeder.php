<?php

namespace Database\Seeders;

use App\Models\Board;
use App\Models\Column;
use App\Models\FinanceCategory;
use App\Models\Setting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Default board with 3 columns (idempotent)
        $board = Board::firstOrCreate(
            ['is_default' => true],
            ['name' => 'Мои задачи', 'description' => 'Основная канбан-доска'],
        );

        $columns = [
            ['name' => 'Создано',  'status_key' => 'created',     'position' => 0],
            ['name' => 'В работе', 'status_key' => 'in_progress',  'position' => 1],
            ['name' => 'Готово',   'status_key' => 'done',          'position' => 2],
        ];

        foreach ($columns as $col) {
            Column::firstOrCreate(
                ['board_id' => $board->id, 'status_key' => $col['status_key']],
                ['name' => $col['name'], 'position' => $col['position']],
            );
        }

        // Finance categories (idempotent)
        $categories = [
            ['name' => 'Еда',         'color' => '#f59e0b', 'icon' => 'utensils'],
            ['name' => 'Транспорт',   'color' => '#3b82f6', 'icon' => 'car'],
            ['name' => 'Развлечения', 'color' => '#8b5cf6', 'icon' => 'gamepad-2'],
            ['name' => 'Коммуналка',  'color' => '#10b981', 'icon' => 'home'],
            ['name' => 'Прочее',      'color' => '#6b7280', 'icon' => 'more-horizontal'],
        ];

        foreach ($categories as $cat) {
            FinanceCategory::firstOrCreate(['name' => $cat['name']], $cat);
        }

        // Default settings (idempotent — only set if not already present)
        if (! Setting::where('key', 'app_password')->exists()) {
            Setting::set('app_password', Hash::make('secret'));
        }
        Setting::set('currency', Setting::get('currency') ?? 'USD');
        Setting::set('currency_symbol', Setting::get('currency_symbol') ?? '$');
        Setting::set('initial_balance', Setting::get('initial_balance') ?? '0');
    }
}
