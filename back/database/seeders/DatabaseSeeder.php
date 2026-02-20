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
        // Default board with 3 columns
        $board = Board::create([
            'name'       => 'Мои задачи',
            'description' => 'Основная канбан-доска',
            'is_default' => true,
        ]);

        Column::insert([
            ['board_id' => $board->id, 'name' => 'Создано',   'status_key' => 'created',     'position' => 0, 'created_at' => now(), 'updated_at' => now()],
            ['board_id' => $board->id, 'name' => 'В работе',  'status_key' => 'in_progress',  'position' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['board_id' => $board->id, 'name' => 'Готово',    'status_key' => 'done',          'position' => 2, 'created_at' => now(), 'updated_at' => now()],
        ]);

        // Finance categories
        FinanceCategory::insert([
            ['name' => 'Еда',          'color' => '#f59e0b', 'icon' => 'utensils',      'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Транспорт',    'color' => '#3b82f6', 'icon' => 'car',            'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Развлечения',  'color' => '#8b5cf6', 'icon' => 'gamepad-2',      'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Коммуналка',   'color' => '#10b981', 'icon' => 'home',           'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Прочее',       'color' => '#6b7280', 'icon' => 'more-horizontal', 'created_at' => now(), 'updated_at' => now()],
        ]);

        // Default password setting
        Setting::set('app_password', Hash::make('secret'));
        Setting::set('currency', 'USD');
        Setting::set('currency_symbol', '$');
        Setting::set('initial_balance', '0');
    }
}
