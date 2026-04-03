<?php

namespace Database\Seeders;

use App\Models\FinanceRequestType;
use Illuminate\Database\Seeder;

class FinanceRequestTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['slug' => 'car-finance', 'name_en' => 'Car Finance', 'name_ar' => 'تمويل سيارة', 'sort_order' => 1],
            ['slug' => 'home-finance', 'name_en' => 'Home Finance', 'name_ar' => 'تمويل منزل', 'sort_order' => 2],
            ['slug' => 'personal-finance', 'name_en' => 'Personal Finance', 'name_ar' => 'تمويل شخصي', 'sort_order' => 3],
            ['slug' => 'business-finance', 'name_en' => 'Business Finance', 'name_ar' => 'تمويل أعمال', 'sort_order' => 4],
        ];

        foreach ($types as $type) {
            FinanceRequestType::updateOrCreate(
                ['slug' => $type['slug']],
                [
                    'name_en' => $type['name_en'],
                    'name_ar' => $type['name_ar'],
                    'description_en' => null,
                    'description_ar' => null,
                    'is_active' => true,
                    'sort_order' => $type['sort_order'],
                ]
            );
        }
    }
}
