<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\QuickAccess;
use Illuminate\Database\Seeder;

class QuickAccessSeeder extends Seeder
{
    /**
     * Available quick access shortcuts keyed by their url_key.
     *
     * @var array<string, array{name: string, icon: string, icon_color: string}>
     */
    public const OPTIONS = [
        'add_to_stock' => [
            'name' => 'Add to stock',
            'icon' => 'Plus',
            'icon_color' => 'primary',
        ],
        'menu_card' => [
            'name' => 'Menu Card',
            'icon' => 'Notebook',
            'icon_color' => 'info',
        ],
        'stock' => [
            'name' => 'Stock',
            'icon' => 'Check',
            'icon_color' => 'primary',
        ],
        'order' => [
            'name' => 'Orders',
            'icon' => 'Utensils',
            'icon_color' => 'primary',
        ],
        'waiters_page' => [
            'name' => 'Waiters',
            'icon' => 'User',
            'icon_color' => 'info',
        ],
        'chefs_page' => [
            'name' => 'Chefs',
            'icon' => 'ChefHat',
            'icon_color' => 'primary',
        ],
    ];

    /**
     * Default quick access order (5 buttons).
     *
     * @var list<string>
     */
    public const DEFAULT_KEYS = [
        'add_to_stock',
        'menu_card',
        'stock',
        'order',
        'waiters_page',
    ];

    public function run(): void
    {
        $defaults = array_values(self::defaults());
        $allowedIndexes = array_column($defaults, 'index');

        Company::all()->each(function (Company $company) use ($defaults, $allowedIndexes) {
            foreach ($defaults as $row) {
                QuickAccess::updateOrCreate(
                    [
                        'company_id' => $company->id,
                        'index' => $row['index'],
                    ],
                    [
                        'name' => $row['name'],
                        'icon' => $row['icon'],
                        'icon_color' => $row['icon_color'],
                        'url_key' => $row['url_key'],
                    ]
                );
            }

            QuickAccess::where('company_id', $company->id)
                ->whereNotIn('index', $allowedIndexes)
                ->delete();
        });
    }

    /**
     * Return the default quick access payload indexed by position (1-5).
     *
     * @return array<int, array<string, string|int>>
     */
    public static function defaults(): array
    {
        $defaults = [];

        foreach (self::DEFAULT_KEYS as $offset => $key) {
            $defaults[$offset + 1] = self::optionWithIndex($key, $offset + 1);
        }

        return $defaults;
    }

    /**
     * Retrieve all available quick access options keyed by url_key.
     *
     * @return array<string, array<string, string>>
     */
    public static function available(): array
    {
        $options = [];

        foreach (self::OPTIONS as $key => $data) {
            $options[$key] = $data + ['url_key' => $key];
        }

        return $options;
    }

    /**
     * Retrieve a single quick access option with its url_key.
     *
     * @return array<string, string>
     */
    public static function option(string $key): array
    {
        if (! isset(self::OPTIONS[$key])) {
            throw new \InvalidArgumentException("Unknown quick access option [{$key}]");
        }

        return self::OPTIONS[$key] + ['url_key' => $key];
    }

    /**
     * Retrieve an option and assign an index.
     *
     * @return array<string, string|int>
     */
    public static function optionWithIndex(string $key, int $index): array
    {
        return ['index' => $index] + self::option($key);
    }
}
