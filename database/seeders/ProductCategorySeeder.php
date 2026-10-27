<?php

namespace Database\Seeders;

use App\Models\ProductCategory;
use Illuminate\Database\Seeder;

class ProductCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        ProductCategory::query()->upsert([
            ['name' => 'Boissons', 'description' => 'Eaux, jus, sodas', 'is_active' => true],
            ['name' => 'Epicerie seche', 'description' => 'Pates, riz, legumes secs', 'is_active' => true],
            ['name' => 'Huiles et sauces', 'description' => 'Huiles, vinaigre, sauces', 'is_active' => true],
            ['name' => 'Conserves', 'description' => 'Conserves et bocaux', 'is_active' => true],
            ['name' => 'Produits laitiers', 'description' => 'Lait et derives', 'is_active' => true],
            ['name' => 'Boulangerie', 'description' => 'Pain, viennoiseries, biscuits secs', 'is_active' => true],
            ['name' => 'Produits menagers', 'description' => 'Nettoyage maison', 'is_active' => true],
            ['name' => 'Hygiene', 'description' => 'Savons, shampoings, hygiene quotidienne', 'is_active' => true],
            ['name' => 'Snacks', 'description' => 'Biscuits et friandises', 'is_active' => true],
            ['name' => 'Confiserie', 'description' => 'Bonbons, chocolats, chewing-gums', 'is_active' => true],
            ['name' => 'Petit dejeuner', 'description' => 'Cafe, the, sucre, cereales', 'is_active' => true],
            ['name' => 'Tabac', 'description' => 'Cigarettes, tabac et accessoires', 'is_active' => true],
        ], ['name'], ['description', 'is_active']);
    }
}
