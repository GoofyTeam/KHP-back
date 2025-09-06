<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Company;
use App\Models\Ingredient;
use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Class IngredientControllerTest
 *
 * Use cases couverts :
 * - Création : avec fichier image, avec URL d’image, sans image (échec), avec les deux (échec),
 *   unicité du nom par société, même nom dans une autre société (OK), sans catégorie (échec),
 *   normalisation de la catégorie (ucfirst), enregistrement de barcode/base_quantity,
 *   stockage S3 + chemin renvoyé.
 * - Image URL : type MIME invalide (échec), taille > max (échec).
 * - Update : champs simples, MAJ image (fichier ou URL), erreur si fichier + URL,
 *   catégories remplacées si fournies / inchangées sinon,
 *   quantités MAJ via syncWithoutDetaching.
 * - Delete : OK dans la même société, interdit si autre société.
 */
class IngredientControllerTest extends TestCase
{
    use RefreshDatabase;

    /** Création échoue sans image ni image_url. */
    public function test_it_fails_to_create_without_image_nor_url(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $location = Location::factory()->create(['company_id' => $company->id]);
        $category = Category::factory()->create(['company_id' => $company->id]);

        $payload = [
            'name' => 'Tomate',
            'unit' => 'kg',
            'base_quantity' => 1,
            'base_unit' => 'kg',
            'category_id' => $category->id,
            'quantities' => [['location_id' => $location->id, 'quantity' => 5]],
        ];

        $this->actingAs($user)
            ->postJson('/api/ingredients', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['image', 'image_url']);
    }

    /** Création échoue avec fichier + URL (mutual exclusivity). */
    public function test_it_fails_to_create_with_both_image_and_url(): void
    {
        Storage::fake('s3');
        Http::fake(); // pas appelé mais safe

        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $location = Location::factory()->create(['company_id' => $company->id]);
        $category = Category::factory()->create(['company_id' => $company->id]);

        $payload = [
            'name' => 'Tomate',
            'unit' => 'kg',
            'base_quantity' => 1,
            'base_unit' => 'kg',
            'category_id' => $category->id,
            'quantities' => [['location_id' => $location->id, 'quantity' => 5]],
            'image_url' => 'https://example.com/tomate.jpg',
        ];

        $file = UploadedFile::fake()->image('t.jpg', 400, 400);

        $this->actingAs($user)
            ->withHeaders(['Accept' => 'application/json']) // force 422 JSON au lieu de 302
            ->post('/api/ingredients', array_merge($payload, ['image' => $file]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['image', 'image_url']);
    }

    /** Création OK avec fichier uploadé : S3 + catégories + quantités. */
    public function test_it_creates_with_uploaded_file_and_sets_category_and_quantities(): void
    {
        Storage::fake('s3');

        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $loc1 = Location::factory()->create(['company_id' => $company->id]);
        $loc2 = Location::factory()->create(['company_id' => $company->id]);
        $category = Category::factory()->create(['company_id' => $company->id]);

        $file = UploadedFile::fake()->image('t.jpg', 300, 300);

        $payload = [
            'name' => 'Tomate',
            'unit' => 'kg',
            'base_quantity' => 1,
            'base_unit' => 'kg',
            'category_id' => $category->id,
            'quantities' => [
                ['location_id' => $loc1->id, 'quantity' => 10],
                ['location_id' => $loc2->id, 'quantity' => 4.5],
            ],
            'image' => $file,
        ];

        $resp = $this->actingAs($user)
            ->post('/api/ingredients', $payload)
            ->assertStatus(201)
            ->json();

        $ingredientId = $resp['ingredient_id'];
        $ingredient = Ingredient::find($ingredientId);

        $this->assertNotNull($ingredient);
        $this->assertNotNull($ingredient->image_url);
        $this->assertTrue(Storage::disk('s3')->exists($ingredient->image_url));

        $this->assertDatabaseHas('ingredients', ['id' => $ingredientId, 'category_id' => $category->id]);

        // pivot quantités
        $this->assertDatabaseHas('ingredient_location', [
            'ingredient_id' => $ingredientId,
            'location_id' => $loc1->id,
            'quantity' => 10,
        ]);
        $this->assertDatabaseHas('ingredient_location', [
            'ingredient_id' => $ingredientId,
            'location_id' => $loc2->id,
            'quantity' => 4.5,
        ]);
    }

    /** Création OK avec image_url : téléchargement + S3 + chemin stable (sha1). */
    public function test_it_creates_with_image_url_and_stores_to_s3(): void
    {
        Storage::fake('s3');

        $imageBytes = random_bytes(1280);
        Http::fake([
            'example.com/*' => Http::response($imageBytes, 200, [
                'Content-Type' => 'image/jpeg',
                'Content-Length' => strlen($imageBytes),
            ]),
        ]);

        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $loc = Location::factory()->create(['company_id' => $company->id]);
        $category = Category::factory()->create(['company_id' => $company->id]);

        $payload = [
            'name' => 'TomateURL',
            'unit' => 'kg',
            'base_quantity' => 1,
            'base_unit' => 'kg',
            'category_id' => $category->id,
            'quantities' => [['location_id' => $loc->id, 'quantity' => 3]],
            'image_url' => 'https://example.com/tomate.jpg',
        ];

        $resp = $this->actingAs($user)
            ->postJson('/api/ingredients', $payload)
            ->assertStatus(201)
            ->json();

        $ingredient = Ingredient::find($resp['ingredient_id']);
        $this->assertTrue(Storage::disk('s3')->exists($ingredient->image_url));
    }

    /** Création multiple d'ingrédients via endpoint bulk. */
    public function test_it_creates_multiple_ingredients_at_once(): void
    {
        Storage::fake('s3');

        $imageBytes = random_bytes(1280);
        Http::fake([
            'example.com/*' => Http::response($imageBytes, 200, [
                'Content-Type' => 'image/jpeg',
                'Content-Length' => strlen($imageBytes),
            ]),
        ]);

        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $loc = Location::factory()->create(['company_id' => $company->id]);
        $category = Category::factory()->create(['company_id' => $company->id]);

        $payload = [
            'ingredients' => [
                [
                    'name' => 'TomateBulk',
                    'unit' => 'kg',
                    'base_quantity' => 1,
                    'base_unit' => 'kg',
                    'category_id' => $category->id,
                    'quantities' => [['location_id' => $loc->id, 'quantity' => 5]],
                    'image_url' => 'https://example.com/tomate1.jpg',
                ],
                [
                    'name' => 'OignonBulk',
                    'unit' => 'kg',
                    'base_quantity' => 2,
                    'base_unit' => 'kg',
                    'category_id' => $category->id,
                    'quantities' => [['location_id' => $loc->id, 'quantity' => 7]],
                    'image_url' => 'https://example.com/oignon.jpg',
                ],
            ],
        ];

        $resp = $this->actingAs($user)
            ->postJson('/api/ingredients/bulk', $payload)
            ->assertStatus(201)
            ->json();

        $this->assertCount(2, $resp['ingredient_ids']);
        $this->assertDatabaseHas('ingredients', ['name' => 'TomateBulk']);
        $this->assertDatabaseHas('ingredients', ['name' => 'OignonBulk']);
    }

    /** Vérifie que la création multiple est annulée si un ingrédient échoue. */
    public function test_bulk_creation_rolls_back_when_one_fails(): void
    {
        Storage::fake('s3');
        Http::fake([
            'example.com/good.jpg' => Http::response(random_bytes(64), 200, [
                'Content-Type' => 'image/jpeg',
            ]),
            'example.com/not-image' => Http::response('<html></html>', 200, [
                'Content-Type' => 'text/html',
            ]),
        ]);

        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $loc = Location::factory()->create(['company_id' => $company->id]);
        $category = Category::factory()->create(['company_id' => $company->id]);

        $payload = [
            'ingredients' => [
                [
                    'name' => 'TomateRollback',
                    'unit' => 'kg',
                    'base_quantity' => 1,
                    'base_unit' => 'kg',
                    'category_id' => $category->id,
                    'quantities' => [['location_id' => $loc->id, 'quantity' => 5]],
                    'image_url' => 'https://example.com/good.jpg',
                ],
                [
                    'name' => 'OignonRollback',
                    'unit' => 'kg',
                    'base_quantity' => 2,
                    'base_unit' => 'kg',
                    'category_id' => $category->id,
                    'quantities' => [['location_id' => $loc->id, 'quantity' => 7]],
                    // URL qui renvoie un contenu non image pour déclencher une ValidationException après création du premier ingrédient
                    'image_url' => 'https://example.com/not-image',
                ],
            ],
        ];

        $this->actingAs($user)
            ->postJson('/api/ingredients/bulk', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['image_url']);

        $this->assertDatabaseCount('ingredients', 0);
    }

    /** Unicité du nom par société : échec même nom dans même company. */
    public function test_it_enforces_unique_name_per_company(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $loc = Location::factory()->create(['company_id' => $company->id]);
        $category = Category::factory()->create(['company_id' => $company->id]);

        Ingredient::factory()->create(['company_id' => $company->id, 'name' => 'Tomate']);

        $payload = [
            'name' => 'Tomate',
            'unit' => 'kg',
            'base_quantity' => 1,
            'base_unit' => 'kg',
            'category_id' => $category->id,
            'quantities' => [['location_id' => $loc->id, 'quantity' => 1]],
            'image_url' => 'https://example.com/t.jpg',
        ];

        Http::fake([
            'example.com/*' => Http::response(random_bytes(64), 200, ['Content-Type' => 'image/jpeg']),
        ]);

        $this->actingAs($user)
            ->postJson('/api/ingredients', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors('name');
    }

    /** Même nom dans une autre société : OK. */
    public function test_it_allows_same_name_in_other_company(): void
    {
        Storage::fake('s3');
        Http::fake([
            'example.com/*' => Http::response(random_bytes(64), 200, ['Content-Type' => 'image/jpeg']),
        ]);

        $company1 = Company::factory()->create();
        $company2 = Company::factory()->create();
        Ingredient::factory()->create(['company_id' => $company1->id, 'name' => 'Tomate']);

        $user = User::factory()->create(['company_id' => $company2->id]);
        $loc = Location::factory()->create(['company_id' => $company2->id]);
        $category = Category::factory()->create(['company_id' => $company2->id]);

        $payload = [
            'name' => 'Tomate',
            'unit' => 'kg',
            'base_quantity' => 1,
            'base_unit' => 'kg',
            'category_id' => $category->id,
            'quantities' => [['location_id' => $loc->id, 'quantity' => 1]],
            'image_url' => 'https://example.com/t.jpg',
        ];

        $this->actingAs($user)
            ->postJson('/api/ingredients', $payload)
            ->assertStatus(201);
    }

    /** Création échoue sans catégories. */
    public function test_it_fails_to_create_without_category(): void
    {
        Storage::fake('s3');
        Http::fake([
            'example.com/*' => Http::response(random_bytes(64), 200, ['Content-Type' => 'image/jpeg']),
        ]);

        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $loc = Location::factory()->create(['company_id' => $company->id]);

        $payload = [
            'name' => 'SansCat',
            'unit' => 'kg',
            'base_quantity' => 1,
            'base_unit' => 'kg',
            'quantities' => [['location_id' => $loc->id, 'quantity' => 2]],
            'image_url' => 'https://example.com/ok.jpg',
        ];

        $this->actingAs($user)
            ->postJson('/api/ingredients', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors('category_id');
    }

    /** Image URL échoue si MIME non image. */
    public function test_it_fails_when_image_url_is_not_image(): void
    {
        Storage::fake('s3');
        Http::fake([
            'example.com/*' => Http::response('<html></html>', 200, ['Content-Type' => 'text/html']),
        ]);

        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $loc = Location::factory()->create(['company_id' => $company->id]);
        $category = Category::factory()->create(['company_id' => $company->id]);

        $payload = [
            'name' => 'BadMime',
            'unit' => 'kg',
            'base_quantity' => 1,
            'base_unit' => 'kg',
            'category_id' => $category->id,
            'quantities' => [['location_id' => $loc->id, 'quantity' => 1]],
            'image_url' => 'https://example.com/not-image',
        ];

        $this->actingAs($user)
            ->postJson('/api/ingredients', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors('image_url');
    }

    /** Image URL échoue si taille > max. */
    public function test_it_fails_when_image_url_is_too_large(): void
    {
        Storage::fake('s3');
        $big = str_repeat('a', 2_048_001);
        Http::fake([
            'example.com/*' => Http::response($big, 200, [
                'Content-Type' => 'image/jpeg',
                'Content-Length' => strlen($big),
            ]),
        ]);

        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $loc = Location::factory()->create(['company_id' => $company->id]);
        $category = Category::factory()->create(['company_id' => $company->id]);

        $payload = [
            'name' => 'TooBig',
            'unit' => 'kg',
            'base_quantity' => 1,
            'base_unit' => 'kg',
            'category_id' => $category->id,
            'quantities' => [['location_id' => $loc->id, 'quantity' => 1]],
            'image_url' => 'https://example.com/too-big.jpg',
        ];

        $this->actingAs($user)
            ->postJson('/api/ingredients', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors('image_url');
    }

    /** Update champs simples sans toucher relations si non fournies. */
    public function test_it_updates_basic_fields_without_touching_relations(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);

        $ing = Ingredient::factory()->create([
            'company_id' => $company->id,
            'name' => 'Old',
            'unit' => 'kg',
            'base_quantity' => 1,
            'base_unit' => 'kg',
        ]);

        $cat = Category::factory()->create(['company_id' => $company->id, 'name' => 'OldCat']);
        $ing->category()->associate($cat);
        $ing->save();

        $loc = Location::factory()->create(['company_id' => $company->id]);
        $ing->locations()->syncWithoutDetaching([$loc->id => ['quantity' => 2]]);

        $payload = ['name' => 'New', 'unit' => 'g'];

        $this->actingAs($user)
            ->putJson("/api/ingredients/{$ing->id}", $payload)
            ->assertStatus(200);

        $this->assertDatabaseHas('ingredients', ['id' => $ing->id, 'name' => 'New', 'unit' => 'g']);
        $this->assertDatabaseHas('ingredients', ['id' => $ing->id, 'category_id' => $cat->id]);
        $this->assertDatabaseHas('ingredient_location', ['ingredient_id' => $ing->id, 'location_id' => $loc->id, 'quantity' => 2]);
    }

    /** Update catégories : remplacées si fournies. */
    public function test_it_updates_category_when_provided(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);

        $ing = Ingredient::factory()->create(['company_id' => $company->id, 'base_quantity' => 1, 'base_unit' => 'kg']);
        $old = Category::factory()->create(['company_id' => $company->id, 'name' => 'OldCat']);
        $ing->category()->associate($old);
        $ing->save();

        $new = Category::factory()->create(['company_id' => $company->id, 'name' => 'NewCat']);

        $payload = ['category_id' => $new->id];

        $this->actingAs($user)
            ->putJson("/api/ingredients/{$ing->id}", $payload)
            ->assertStatus(200);

        $this->assertDatabaseHas('ingredients', ['id' => $ing->id, 'category_id' => $new->id]);
    }

    /** Update échoue si catégorie nulle. */
    public function test_it_fails_to_update_with_null_category(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);

        $ing = Ingredient::factory()->create(['company_id' => $company->id, 'base_quantity' => 1, 'base_unit' => 'kg']);

        $payload = ['category_id' => null];

        $this->actingAs($user)
            ->putJson("/api/ingredients/{$ing->id}", $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors('category_id');
    }

    /** Update quantités : syncWithoutDetaching met à jour et/ou ajoute. */
    public function test_it_updates_quantities_sync_without_detaching(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);

        $ing = Ingredient::factory()->create(['company_id' => $company->id, 'base_quantity' => 1, 'base_unit' => 'kg']);

        $loc1 = Location::factory()->create(['company_id' => $company->id]);
        $loc2 = Location::factory()->create(['company_id' => $company->id]);

        // quantité initiale sur loc1
        $ing->locations()->syncWithoutDetaching([$loc1->id => ['quantity' => 1]]);

        $payload = [
            'quantities' => [
                ['location_id' => $loc1->id, 'quantity' => 5], // update
                ['location_id' => $loc2->id, 'quantity' => 3], // add
            ],
        ];

        $this->actingAs($user)
            ->putJson("/api/ingredients/{$ing->id}", $payload)
            ->assertStatus(200);

        $this->assertDatabaseHas('ingredient_location', ['ingredient_id' => $ing->id, 'location_id' => $loc1->id, 'quantity' => 5]);
        $this->assertDatabaseHas('ingredient_location', ['ingredient_id' => $ing->id, 'location_id' => $loc2->id, 'quantity' => 3]);
    }

    /** Update image via upload (multipart PUT). */
    public function test_it_updates_image_with_uploaded_file(): void
    {
        Storage::fake('s3');

        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $ing = Ingredient::factory()->create(['company_id' => $company->id, 'image_url' => null, 'base_quantity' => 1, 'base_unit' => 'kg']);

        $file = UploadedFile::fake()->image('new.jpg', 320, 320);

        $this->actingAs($user)
            ->post("/api/ingredients/{$ing->id}", [
                '_method' => 'PUT',
                'image' => $file,
            ])
            ->assertStatus(200);

        $ing->refresh();
        $this->assertNotNull($ing->image_url);
        $this->assertTrue(Storage::disk('s3')->exists($ing->image_url));
    }

    /** Update image via URL. */
    public function test_it_updates_image_with_url(): void
    {
        Storage::fake('s3');
        $bytes = random_bytes(256);
        Http::fake([
            'example.com/*' => Http::response($bytes, 200, ['Content-Type' => 'image/png']),
        ]);

        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $ing = Ingredient::factory()->create(['company_id' => $company->id, 'image_url' => null, 'base_quantity' => 1, 'base_unit' => 'kg']);

        $payload = ['image_url' => 'https://example.com/new.png'];

        $this->actingAs($user)
            ->putJson("/api/ingredients/{$ing->id}", $payload)
            ->assertStatus(200);

        $ing->refresh();
        $this->assertNotNull($ing->image_url);
        $this->assertTrue(Storage::disk('s3')->exists($ing->image_url));
    }

    /** Update échoue si fichier + URL fournis. */
    public function test_it_fails_update_when_both_file_and_url_are_provided(): void
    {
        Storage::fake('s3');
        Http::fake();

        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $ing = Ingredient::factory()->create(['company_id' => $company->id, 'base_quantity' => 1, 'base_unit' => 'kg']);

        $file = UploadedFile::fake()->image('x.jpg');

        $this->actingAs($user)
            ->withHeaders(['Accept' => 'application/json']) // force 422 JSON
            ->post("/api/ingredients/{$ing->id}", [
                '_method' => 'PUT',
                'image' => $file,
                'image_url' => 'https://example.com/x.jpg',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['image', 'image_url']);
    }

    /** Delete : OK dans la même société. */
    public function test_it_deletes_ingredient_in_same_company(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $ing = Ingredient::factory()->create(['company_id' => $company->id, 'base_quantity' => 1, 'base_unit' => 'kg']);

        $this->actingAs($user)
            ->deleteJson("/api/ingredients/{$ing->id}")
            ->assertStatus(200);

        $this->assertDatabaseMissing('ingredients', ['id' => $ing->id]);
    }

    /** Delete : interdit si l’ingrédient appartient à une autre société (403). */
    public function test_it_fails_to_delete_ingredient_of_other_company(): void
    {
        $company1 = Company::factory()->create();
        $company2 = Company::factory()->create();

        $user = User::factory()->create(['company_id' => $company1->id]);
        $ing = Ingredient::factory()->create(['company_id' => $company2->id, 'base_quantity' => 1, 'base_unit' => 'kg']);

        $this->actingAs($user)
            ->deleteJson("/api/ingredients/{$ing->id}")
            ->assertStatus(403);

        $this->assertDatabaseHas('ingredients', ['id' => $ing->id, 'company_id' => $company2->id]);
    }

    /** Création : barcode et base_quantity sont enregistrés. */
    public function test_it_saves_barcode_and_base_quantity_on_store(): void
    {
        Storage::fake('s3');
        Http::fake([
            'example.com/*' => Http::response(random_bytes(64), 200, ['Content-Type' => 'image/jpeg']),
        ]);

        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $loc = Location::factory()->create(['company_id' => $company->id]);

        $category = Category::factory()->create(['company_id' => $company->id]);

        $payload = [
            'name' => 'AvecMeta',
            'unit' => 'kg',
            'base_quantity' => 1.25,
            'base_unit' => 'kg',
            'category_id' => $category->id,
            'quantities' => [['location_id' => $loc->id, 'quantity' => 2]],
            'barcode' => '123456789',
            'image_url' => 'https://example.com/pic.jpg',
        ];

        $resp = $this->actingAs($user)
            ->postJson('/api/ingredients', $payload)
            ->assertStatus(201)
            ->json();

        $this->assertDatabaseHas('ingredients', [
            'id' => $resp['ingredient_id'],
            'barcode' => '123456789',
            'base_quantity' => 1.25,
            'base_unit' => 'kg',
        ]);
    }
}
