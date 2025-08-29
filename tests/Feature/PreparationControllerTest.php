<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Company;
use App\Models\Ingredient;
use App\Models\Location;
use App\Models\LocationType;
use App\Models\Perishable;
use App\Models\Preparation;
use App\Models\PreparationEntity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Class PreparationControllerTest
 *
 * Cette suite de tests couvre les scénarios de création, mise à jour
 * (avec entities_to_add / entities_to_remove), destruction,
 * la préparation elle-même avec prélèvement de composants,
 * et la gestion d'image (upload OU URL) avec exclusivité.
 */
class PreparationControllerTest extends TestCase
{
    use RefreshDatabase;

    /** Scénario : création échoue avec une seule entité. */
    public function test_it_fails_to_create_with_single_entity(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $ing = Ingredient::factory()->create(['company_id' => $company->id]);
        $category = Category::factory()->create(['company_id' => $company->id]);

        $payload = [
            'name' => 'Solo Entity',
            'unit' => 'g',
            'entities' => [['id' => $ing->id, 'type' => 'ingredient']],
            'category_id' => $category->id,
        ];

        $this->actingAs($user)
            ->postJson('/api/preparations', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors('entities');
    }

    /** Scénario : création échoue sans catégories. */
    public function test_it_fails_to_create_without_category(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $ing1 = Ingredient::factory()->create(['company_id' => $company->id]);
        $ing2 = Ingredient::factory()->create(['company_id' => $company->id]);

        $payload = [
            'name' => 'No Categories',
            'unit' => 'g',
            'entities' => [
                ['id' => $ing1->id, 'type' => 'ingredient'],
                ['id' => $ing2->id, 'type' => 'ingredient'],
            ],
        ];

        $this->actingAs($user)
            ->postJson('/api/preparations', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors('category_id');
    }

    /** Scénario : création avec deux ingrédients. */
    public function test_it_creates_with_two_ingredients(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $ing1 = Ingredient::factory()->create(['company_id' => $company->id]);
        $ing2 = Ingredient::factory()->create(['company_id' => $company->id]);
        $category = Category::factory()->create(['company_id' => $company->id]);

        $payload = [
            'name' => 'Dual Ingredient',
            'unit' => 'kg',
            'entities' => [
                ['id' => $ing1->id, 'type' => 'ingredient'],
                ['id' => $ing2->id, 'type' => 'ingredient'],
            ],
            'category_id' => $category->id,
        ];

        $this->actingAs($user)
            ->postJson('/api/preparations', $payload)
            ->assertStatus(201);

        $this->assertDatabaseHas('preparation_entities', [
            'entity_id' => $ing1->id,
            'entity_type' => Ingredient::class,
        ]);
        $this->assertDatabaseHas('preparation_entities', [
            'entity_id' => $ing2->id,
            'entity_type' => Ingredient::class,
        ]);
    }

    /** Scénario : création avec deux préparations. */
    public function test_it_creates_with_two_preparations(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $pre1 = Preparation::factory()->create(['company_id' => $company->id]);
        $pre2 = Preparation::factory()->create(['company_id' => $company->id]);
        $category = Category::factory()->create(['company_id' => $company->id]);

        $payload = [
            'name' => 'Dual Preparation',
            'unit' => 'L',
            'entities' => [
                ['id' => $pre1->id, 'type' => 'preparation'],
                ['id' => $pre2->id, 'type' => 'preparation'],
            ],
            'category_id' => $category->id,
        ];

        $this->actingAs($user)
            ->postJson('/api/preparations', $payload)
            ->assertStatus(201);

        $this->assertDatabaseHas('preparation_entities', [
            'entity_id' => $pre1->id,
            'entity_type' => Preparation::class,
        ]);
        $this->assertDatabaseHas('preparation_entities', [
            'entity_id' => $pre2->id,
            'entity_type' => Preparation::class,
        ]);
    }

    /** Scénario : création mixte (1 ingrédient + 1 préparation). */
    public function test_it_creates_with_mixed_entities(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $ing = Ingredient::factory()->create(['company_id' => $company->id]);
        $pre = Preparation::factory()->create(['company_id' => $company->id]);
        $category = Category::factory()->create(['company_id' => $company->id]);

        $payload = [
            'name' => 'Mixed',
            'unit' => 'g',
            'entities' => [
                ['id' => $ing->id, 'type' => 'ingredient'],
                ['id' => $pre->id, 'type' => 'preparation'],
            ],
            'category_id' => $category->id,
        ];

        $this->actingAs($user)
            ->postJson('/api/preparations', $payload)
            ->assertStatus(201);

        $this->assertDatabaseHas('preparation_entities', [
            'entity_id' => $ing->id,
            'entity_type' => Ingredient::class,
        ]);
        $this->assertDatabaseHas('preparation_entities', [
            'entity_id' => $pre->id,
            'entity_type' => Preparation::class,
        ]);
    }

    /** Scénario image : création avec upload fichier (S3). */
    public function test_it_creates_with_uploaded_image_and_stores_to_s3(): void
    {
        Storage::fake('s3');

        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);

        $ing1 = Ingredient::factory()->create(['company_id' => $company->id]);
        $ing2 = Ingredient::factory()->create(['company_id' => $company->id]);
        $category = Category::factory()->create(['company_id' => $company->id]);

        $file = UploadedFile::fake()->image('prep.jpg', 400, 400);

        $payload = [
            'name' => 'Avec Image Upload',
            'unit' => 'kg',
            'entities' => [
                ['id' => $ing1->id, 'type' => 'ingredient'],
                ['id' => $ing2->id, 'type' => 'ingredient'],
            ],
            'category_id' => $category->id,
            'image' => $file,
        ];

        $resp = $this->actingAs($user)
            ->post('/api/preparations', $payload)
            ->assertStatus(201)
            ->json();

        $prep = Preparation::find($resp['preparation']['id']);
        $this->assertNotNull($prep->image_url);
        $this->assertTrue(Storage::disk('s3')->exists($prep->image_url));
    }

    /** Scénario image : création avec image_url distante (S3). */
    public function test_it_creates_with_image_url_and_stores_to_s3(): void
    {
        Storage::fake('s3');
        $bytes = random_bytes(1024);
        Http::fake([
            'example.com/*' => Http::response($bytes, 200, ['Content-Type' => 'image/jpeg']),
        ]);

        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);

        $ing1 = Ingredient::factory()->create(['company_id' => $company->id]);
        $ing2 = Ingredient::factory()->create(['company_id' => $company->id]);

        $category = Category::factory()->create(['company_id' => $company->id]);

        $payload = [
            'name' => 'Avec Image URL',
            'unit' => 'kg',
            'entities' => [
                ['id' => $ing1->id, 'type' => 'ingredient'],
                ['id' => $ing2->id, 'type' => 'ingredient'],
            ],
            'category_id' => $category->id,
            'image_url' => 'https://example.com/p.jpg',
        ];

        $resp = $this->actingAs($user)
            ->postJson('/api/preparations', $payload)
            ->assertStatus(201)
            ->json();

        $prep = Preparation::find($resp['preparation']['id']);
        $this->assertNotNull($prep->image_url);
        $this->assertTrue(Storage::disk('s3')->exists($prep->image_url));
    }

    /** Scénario image : création échoue si upload + URL fournis. */
    public function test_it_fails_create_when_both_file_and_url_are_provided(): void
    {
        Storage::fake('s3');
        Http::fake();

        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);

        $ing1 = Ingredient::factory()->create(['company_id' => $company->id]);
        $ing2 = Ingredient::factory()->create(['company_id' => $company->id]);

        $category = Category::factory()->create(['company_id' => $company->id]);

        $file = UploadedFile::fake()->image('x.jpg');

        $payload = [
            'name' => 'Bad Both',
            'unit' => 'kg',
            'entities' => [
                ['id' => $ing1->id, 'type' => 'ingredient'],
                ['id' => $ing2->id, 'type' => 'ingredient'],
            ],
            'category_id' => $category->id,
            'image_url' => 'https://example.com/x.jpg',
        ];

        $this->actingAs($user)
            ->withHeaders(['Accept' => 'application/json'])
            ->post('/api/preparations', array_merge($payload, ['image' => $file]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['image', 'image_url']);
    }

    /** Scénario : mise à jour sans clés d'entités. */
    public function test_it_updates_without_entities(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $prep = Preparation::factory()->create(['company_id' => $company->id]);

        // Liaison initiale
        $ing = Ingredient::factory()->create(['company_id' => $company->id]);
        PreparationEntity::create([
            'preparation_id' => $prep->id,
            'entity_id' => $ing->id,
            'entity_type' => Ingredient::class,
        ]);

        $payload = ['name' => 'New Name'];

        $this->actingAs($user)
            ->putJson("/api/preparations/{$prep->id}", $payload)
            ->assertStatus(200);

        $this->assertDatabaseHas('preparations', [
            'id' => $prep->id,
            'name' => 'New Name',
        ]);
        $this->assertDatabaseHas('preparation_entities', [
            'preparation_id' => $prep->id,
            'entity_id' => $ing->id,
        ]);
    }

    /** Scénario : mise à jour des catégories d'une préparation. */
    public function test_it_updates_category(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);

        // Créer une catégorie existante
        $oldCategory = Category::factory()->create([
            'name' => 'OldCategory',
            'company_id' => $company->id,
        ]);

        // Créer une préparation avec la catégorie existante
        $prep = Preparation::factory()->create([
            'company_id' => $company->id,
            'category_id' => $oldCategory->id,
        ]);

        // Mise à jour avec de nouvelles catégories
        $newCategory = Category::factory()->create(['company_id' => $company->id, 'name' => 'NewCategory']);
        $payload = [
            'category_id' => $newCategory->id,
        ];

        $this->actingAs($user)
            ->putJson("/api/preparations/{$prep->id}", $payload)
            ->assertStatus(200);

        // Vérifier que la nouvelle catégorie a été associée
        $this->assertDatabaseHas('preparations', [
            'id' => $prep->id,
            'category_id' => $newCategory->id,
        ]);

        // Vérifier que l'ancienne catégorie a été remplacée
        $this->assertDatabaseMissing('preparations', [
            'id' => $prep->id,
            'category_id' => $oldCategory->id,
        ]);
    }

    /** Scénario : mise à jour échoue si catégorie nulle. */
    public function test_it_fails_to_update_with_null_category(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $prep = Preparation::factory()->create(['company_id' => $company->id]);

        $payload = ['category_id' => null];

        $this->actingAs($user)
            ->putJson("/api/preparations/{$prep->id}", $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors('category_id');
    }

    /** Scénario : suppression d'une entité via entities_to_remove. */
    public function test_it_removes_entities_on_update(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $prep = Preparation::factory()->create(['company_id' => $company->id]);

        $ing1 = Ingredient::factory()->create(['company_id' => $company->id]);
        $ing2 = Ingredient::factory()->create(['company_id' => $company->id]);
        PreparationEntity::create([
            'preparation_id' => $prep->id,
            'entity_id' => $ing1->id,
            'entity_type' => Ingredient::class,
        ]);
        PreparationEntity::create([
            'preparation_id' => $prep->id,
            'entity_id' => $ing2->id,
            'entity_type' => Ingredient::class,
        ]);

        $payload = [
            'entities_to_remove' => [
                ['id' => $ing2->id, 'type' => 'ingredient'],
            ],
        ];

        $this->actingAs($user)
            ->putJson("/api/preparations/{$prep->id}", $payload)
            ->assertStatus(200);

        $this->assertDatabaseMissing('preparation_entities', [
            'preparation_id' => $prep->id,
            'entity_id' => $ing2->id,
        ]);
        $this->assertDatabaseHas('preparation_entities', [
            'preparation_id' => $prep->id,
            'entity_id' => $ing1->id,
        ]);
    }

    /** Scénario : ajout d'une entité via entities_to_add. */
    public function test_it_adds_entities_on_update(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $prep = Preparation::factory()->create(['company_id' => $company->id]);

        $oldIng = Ingredient::factory()->create(['company_id' => $company->id]);
        PreparationEntity::create([
            'preparation_id' => $prep->id,
            'entity_id' => $oldIng->id,
            'entity_type' => Ingredient::class,
        ]);

        $newIng = Ingredient::factory()->create(['company_id' => $company->id]);
        $payload = [
            'entities_to_add' => [
                ['id' => $newIng->id, 'type' => 'ingredient'],
            ],
        ];

        $this->actingAs($user)
            ->putJson("/api/preparations/{$prep->id}", $payload)
            ->assertStatus(200);

        $this->assertDatabaseHas('preparation_entities', [
            'preparation_id' => $prep->id,
            'entity_id' => $oldIng->id,
        ]);
        $this->assertDatabaseHas('preparation_entities', [
            'preparation_id' => $prep->id,
            'entity_id' => $newIng->id,
            'entity_type' => Ingredient::class,
        ]);
    }

    /** Scénario : suppression et ajout simultanés. */
    public function test_it_adds_and_removes_entities_on_update(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $prep = Preparation::factory()->create(['company_id' => $company->id]);

        $ing1 = Ingredient::factory()->create(['company_id' => $company->id]);
        $ing2 = Ingredient::factory()->create(['company_id' => $company->id]);
        PreparationEntity::create([
            'preparation_id' => $prep->id,
            'entity_id' => $ing1->id,
            'entity_type' => Ingredient::class,
        ]);
        PreparationEntity::create([
            'preparation_id' => $prep->id,
            'entity_id' => $ing2->id,
            'entity_type' => Ingredient::class,
        ]);

        $ing3 = Ingredient::factory()->create(['company_id' => $company->id]);
        $payload = [
            'entities_to_remove' => [
                ['id' => $ing2->id, 'type' => 'ingredient'],
            ],
            'entities_to_add' => [
                ['id' => $ing3->id, 'type' => 'ingredient'],
            ],
        ];

        $this->actingAs($user)
            ->putJson("/api/preparations/{$prep->id}", $payload)
            ->assertStatus(200);

        $this->assertDatabaseMissing('preparation_entities', [
            'preparation_id' => $prep->id,
            'entity_id' => $ing2->id,
        ]);
        $this->assertDatabaseHas('preparation_entities', [
            'preparation_id' => $prep->id,
            'entity_id' => $ing1->id,
        ]);
        $this->assertDatabaseHas('preparation_entities', [
            'preparation_id' => $prep->id,
            'entity_id' => $ing3->id,
        ]);
    }

    /** Scénario : mise à jour des quantités par emplacement. */
    public function test_it_updates_quantities_by_location(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $prep = Preparation::factory()->create(['company_id' => $company->id]);
        $location = Location::factory()->create(['company_id' => $company->id]);

        // Quantité initiale
        $prep->locations()->updateExistingPivot($location->id, ['quantity' => 5.0]);

        $payload = [
            'quantities' => [
                ['location_id' => $location->id, 'quantity' => 10.0],
            ],
        ];

        $this->actingAs($user)
            ->putJson("/api/preparations/{$prep->id}", $payload)
            ->assertStatus(200);

        $this->assertDatabaseHas('location_preparation', [
            'preparation_id' => $prep->id,
            'location_id' => $location->id,
            'quantity' => 10.0,
        ]);
    }

    /** Scénario : entities_to_remove présent mais vide -> ne fait rien. */
    public function test_it_does_not_remove_anything_when_remove_list_empty(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $prep = Preparation::factory()->create(['company_id' => $company->id]);

        $ing = Ingredient::factory()->create(['company_id' => $company->id]);
        PreparationEntity::create([
            'preparation_id' => $prep->id,
            'entity_id' => $ing->id,
            'entity_type' => Ingredient::class,
        ]);

        $payload = ['entities_to_remove' => []];

        $this->actingAs($user)
            ->putJson("/api/preparations/{$prep->id}", $payload)
            ->assertStatus(200);

        // L'entité existante doit toujours être présente
        $this->assertDatabaseHas('preparation_entities', [
            'preparation_id' => $prep->id,
            'entity_id' => $ing->id,
        ]);
    }

    /** Scénario image : update via upload (multipart PUT). */
    public function test_it_updates_image_with_uploaded_file(): void
    {
        Storage::fake('s3');

        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $prep = Preparation::factory()->create(['company_id' => $company->id, 'image_url' => null]);

        $file = UploadedFile::fake()->image('new.jpg', 320, 320);

        $this->actingAs($user)
            ->post("/api/preparations/{$prep->id}", [
                '_method' => 'PUT',
                'image' => $file,
            ])
            ->assertStatus(200);

        $prep->refresh();
        $this->assertNotNull($prep->image_url);
        $this->assertTrue(Storage::disk('s3')->exists($prep->image_url));
    }

    /** Scénario image : update via URL distante. */
    public function test_it_updates_image_with_url(): void
    {
        Storage::fake('s3');
        $bytes = random_bytes(256);
        Http::fake([
            'example.com/*' => Http::response($bytes, 200, ['Content-Type' => 'image/png']),
        ]);

        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $prep = Preparation::factory()->create(['company_id' => $company->id, 'image_url' => null]);

        $payload = ['image_url' => 'https://example.com/new.png'];

        $this->actingAs($user)
            ->putJson("/api/preparations/{$prep->id}", $payload)
            ->assertStatus(200);

        $prep->refresh();
        $this->assertNotNull($prep->image_url);
        $this->assertTrue(Storage::disk('s3')->exists($prep->image_url));
    }

    /** Scénario image : update échoue si fichier + URL fournis. */
    public function test_it_fails_update_when_both_file_and_url_are_provided(): void
    {
        Storage::fake('s3');
        Http::fake();

        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $prep = Preparation::factory()->create(['company_id' => $company->id]);

        $file = UploadedFile::fake()->image('x.jpg');

        $this->actingAs($user)
            ->withHeaders(['Accept' => 'application/json'])
            ->post("/api/preparations/{$prep->id}", [
                '_method' => 'PUT',
                'image' => $file,
                'image_url' => 'https://example.com/x.jpg',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['image', 'image_url']);
    }

    /** Scénario : suppression d'une préparation. */
    public function test_it_deletes_a_preparation(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $prep = Preparation::factory()->create(['company_id' => $company->id]);

        $this->actingAs($user)
            ->deleteJson("/api/preparations/{$prep->id}")
            ->assertStatus(204);

        $this->assertDatabaseMissing('preparations', [
            'id' => $prep->id,
        ]);
        $this->assertDatabaseMissing('preparation_entities', [
            'preparation_id' => $prep->id,
        ]);
    }

    /** Scénario : suppression d'une préparation non existante. */
    public function test_it_fails_to_delete_non_existent_preparation(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $badId = 9999;

        $this->actingAs($user)
            ->deleteJson("/api/preparations/{$badId}")
            ->assertStatus(404)
            ->assertJsonStructure(['message']);
    }

    /** Scénario : suppression d'une préparation hors société. */
    public function test_it_fails_to_delete_preparation_not_belonging_to_company(): void
    {
        $company1 = Company::factory()->create();
        $company2 = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company1->id]);
        $prep = Preparation::factory()->create(['company_id' => $company2->id]);

        $this->actingAs($user)
            ->deleteJson("/api/preparations/{$prep->id}")
            ->assertStatus(404)
            ->assertJsonStructure(['message']);

        // La préparation reste en base
        $this->assertDatabaseHas('preparations', [
            'id' => $prep->id,
            'company_id' => $company2->id,
        ]);
    }

    /** Scénario : préparation avec vérification de la catégorie dans la réponse. */
    public function test_prepare_response_includes_category(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);

        // Créer un ingrédient
        $ing = Ingredient::factory()->create([
            'company_id' => $company->id,
            'name' => 'Farine',
            'unit' => 'kg',
        ]);

        // Créer des emplacements
        $source = Location::factory()->create([
            'company_id' => $company->id,
            'name' => 'Réserve',
        ]);

        $destination = Location::factory()->create([
            'company_id' => $company->id,
            'name' => 'Cuisine',
        ]);

        // Ajouter du stock
        $ing->locations()->updateExistingPivot($source->id, ['quantity' => 10.0]);

        // Créer une catégorie
        $category = Category::factory()->create([
            'name' => 'TestCategory',
            'company_id' => $company->id,
        ]);

        // Créer une préparation avec une catégorie
        $preparation = Preparation::factory()->create([
            'company_id' => $company->id,
            'name' => 'Simple preparation',
            'unit' => 'kg',
        ]);

        // Associer la catégorie à la préparation
        $preparation->category()->associate($category);

        // Lier l'ingrédient à la préparation
        PreparationEntity::create([
            'preparation_id' => $preparation->id,
            'entity_id' => $ing->id,
            'entity_type' => Ingredient::class,
        ]);

        // Effectuer la préparation
        $payload = [
            'quantity' => 2.5,
            'location_id' => $destination->id,
            'components' => [
                [
                    'entity_id' => $ing->id,
                    'entity_type' => 'ingredient',
                    'quantity' => 3.0,
                    'sources' => [
                        ['location_id' => $source->id, 'quantity' => 3.0],
                    ],
                ],
            ],
        ];

        $response = $this->actingAs($user)
            ->postJson("/api/preparations/{$preparation->id}/prepare", $payload)
            ->assertStatus(200);

        // Vérifier que la catégorie est incluse dans la réponse
        $this->assertArrayHasKey('category', $response->json('preparation'));
        $this->assertEquals('TestCategory', $response->json('preparation.category.name'));
    }

    /** Scénario : préparation réussie avec un seul emplacement source. */
    public function test_it_prepares_successfully_with_single_source(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);

        // Créer des ingrédients
        $ing1 = Ingredient::factory()->create([
            'company_id' => $company->id,
            'name' => 'Farine',
            'unit' => 'kg',
        ]);

        $ing2 = Ingredient::factory()->create([
            'company_id' => $company->id,
            'name' => 'Sucre',
            'unit' => 'kg',
        ]);

        // Créer des emplacements
        $location1 = Location::factory()->create(['company_id' => $company->id, 'name' => 'Réserve']);
        $location2 = Location::factory()->create(['company_id' => $company->id, 'name' => 'Cuisine']);

        // Stocks
        $ing1->locations()->updateExistingPivot($location1->id, ['quantity' => 10.0]);
        $ing2->locations()->updateExistingPivot($location1->id, ['quantity' => 8.0]);

        // Préparation
        $preparation = Preparation::factory()->create([
            'company_id' => $company->id,
            'name' => 'Pâte à gâteau',
            'unit' => 'kg',
        ]);

        // Liaisons
        PreparationEntity::create(['preparation_id' => $preparation->id, 'entity_id' => $ing1->id, 'entity_type' => Ingredient::class]);
        PreparationEntity::create(['preparation_id' => $preparation->id, 'entity_id' => $ing2->id, 'entity_type' => Ingredient::class]);

        // Prepare
        $payload = [
            'quantity' => 2.5,
            'location_id' => $location2->id,
            'components' => [
                ['entity_id' => $ing1->id, 'entity_type' => 'ingredient', 'quantity' => 3.0, 'sources' => [
                    ['location_id' => $location1->id, 'quantity' => 3.0],
                ]],
                ['entity_id' => $ing2->id, 'entity_type' => 'ingredient', 'quantity' => 2.0, 'sources' => [
                    ['location_id' => $location1->id, 'quantity' => 2.0],
                ]],
            ],
        ];

        $this->actingAs($user)
            ->postJson("/api/preparations/{$preparation->id}/prepare", $payload)
            ->assertStatus(200);

        // Stocks réduits
        $this->assertDatabaseHas('ingredient_location', ['ingredient_id' => $ing1->id, 'location_id' => $location1->id, 'quantity' => 7.0]);
        $this->assertDatabaseHas('ingredient_location', ['ingredient_id' => $ing2->id, 'location_id' => $location1->id, 'quantity' => 6.0]);

        // Quantité destination
        $this->assertDatabaseHas('location_preparation', ['preparation_id' => $preparation->id, 'location_id' => $location2->id, 'quantity' => 2.5]);
    }

    public function test_prepare_removes_perime_quantities(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);

        $ingredient = Ingredient::factory()->create(['company_id' => $company->id]);
        $source = Location::factory()->create(['company_id' => $company->id]);
        $destination = Location::factory()->create(['company_id' => $company->id]);

        $ingredient->locations()->updateExistingPivot($source->id, ['quantity' => 5]);

        Perishable::create([
            'ingredient_id' => $ingredient->id,
            'location_id' => $source->id,
            'company_id' => $company->id,
            'quantity' => 5,
        ]);

        $preparation = Preparation::factory()->create(['company_id' => $company->id]);
        PreparationEntity::create([
            'preparation_id' => $preparation->id,
            'entity_id' => $ingredient->id,
            'entity_type' => Ingredient::class,
        ]);

        $payload = [
            'quantity' => 1,
            'location_id' => $destination->id,
            'components' => [
                [
                    'entity_id' => $ingredient->id,
                    'entity_type' => 'ingredient',
                    'quantity' => 2,
                    'sources' => [
                        ['location_id' => $source->id, 'quantity' => 2],
                    ],
                ],
            ],
        ];

        $this->actingAs($user)
            ->postJson("/api/preparations/{$preparation->id}/prepare", $payload)
            ->assertStatus(200);

        $this->assertDatabaseHas('perishables', [
            'ingredient_id' => $ingredient->id,
            'location_id' => $source->id,
            'company_id' => $company->id,
            'quantity' => 3,
        ]);
    }

    /** Scénario : préparation avec plusieurs emplacements sources. */
    public function test_it_prepares_with_multiple_sources(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);

        $ing = Ingredient::factory()->create(['company_id' => $company->id, 'name' => 'Farine', 'unit' => 'kg']);
        $location1 = Location::factory()->create(['company_id' => $company->id, 'name' => 'Réserve 1']);
        $location2 = Location::factory()->create(['company_id' => $company->id, 'name' => 'Réserve 2']);
        $destination = Location::factory()->create(['company_id' => $company->id, 'name' => 'Cuisine']);

        $ing->locations()->updateExistingPivot($location1->id, ['quantity' => 5.0]);
        $ing->locations()->updateExistingPivot($location2->id, ['quantity' => 3.0]);

        $preparation = Preparation::factory()->create(['company_id' => $company->id, 'name' => 'Simple preparation', 'unit' => 'kg']);
        PreparationEntity::create(['preparation_id' => $preparation->id, 'entity_id' => $ing->id, 'entity_type' => Ingredient::class]);

        $payload = [
            'quantity' => 2.0,
            'location_id' => $destination->id,
            'components' => [
                ['entity_id' => $ing->id, 'entity_type' => 'ingredient', 'quantity' => 6.0, 'sources' => [
                    ['location_id' => $location1->id, 'quantity' => 4.0],
                    ['location_id' => $location2->id, 'quantity' => 2.0],
                ]],
            ],
        ];

        $this->actingAs($user)
            ->postJson("/api/preparations/{$preparation->id}/prepare", $payload)
            ->assertStatus(200);

        $this->assertDatabaseHas('ingredient_location', ['ingredient_id' => $ing->id, 'location_id' => $location1->id, 'quantity' => 1.0]);
        $this->assertDatabaseHas('ingredient_location', ['ingredient_id' => $ing->id, 'location_id' => $location2->id, 'quantity' => 1.0]);
        $this->assertDatabaseHas('location_preparation', ['preparation_id' => $preparation->id, 'location_id' => $destination->id, 'quantity' => 2.0]);
    }

    /** Scénario : échec de préparation avec stock insuffisant. */
    public function test_it_fails_to_prepare_with_insufficient_stock(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);

        $ing = Ingredient::factory()->create(['company_id' => $company->id, 'name' => 'Farine', 'unit' => 'kg']);
        $location1 = Location::factory()->create(['company_id' => $company->id, 'name' => 'Réserve']);
        $location2 = Location::factory()->create(['company_id' => $company->id, 'name' => 'Cuisine']);
        $ing->locations()->updateExistingPivot($location1->id, ['quantity' => 2.0]);

        $preparation = Preparation::factory()->create(['company_id' => $company->id, 'name' => 'Simple preparation', 'unit' => 'kg']);
        PreparationEntity::create(['preparation_id' => $preparation->id, 'entity_id' => $ing->id, 'entity_type' => Ingredient::class]);

        $payload = [
            'quantity' => 1.0,
            'location_id' => $location2->id,
            'components' => [
                ['entity_id' => $ing->id, 'entity_type' => 'ingredient', 'quantity' => 3.0, 'sources' => [
                    ['location_id' => $location1->id, 'quantity' => 3.0],
                ]],
            ],
        ];

        $this->actingAs($user)
            ->postJson("/api/preparations/{$preparation->id}/prepare", $payload)
            ->assertStatus(400)
            ->assertJsonFragment(['message' => "Stock insuffisant pour 'Farine' à l'emplacement 'Réserve' (disponible: 2, requis: 3)"]);

        $this->assertDatabaseHas('ingredient_location', ['ingredient_id' => $ing->id, 'location_id' => $location1->id, 'quantity' => 2.0]);
    }

    /** Scénario : échec de préparation avec un congélateur. */
    public function test_it_fails_when_using_freezer_location(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);

        $freezerType = LocationType::firstOrCreate(['name' => 'Congélateur']);

        $ing = Ingredient::factory()->create(['company_id' => $company->id, 'name' => 'Viande', 'unit' => 'kg']);
        $freezer = Location::factory()->create(['company_id' => $company->id, 'name' => 'Congélateur principal', 'location_type_id' => $freezerType->id]);
        $kitchen = Location::factory()->create(['company_id' => $company->id, 'name' => 'Cuisine']);

        $ing->locations()->updateExistingPivot($freezer->id, ['quantity' => 5.0]);

        $preparation = Preparation::factory()->create(['company_id' => $company->id, 'name' => 'Préparation de viande', 'unit' => 'kg']);
        PreparationEntity::create(['preparation_id' => $preparation->id, 'entity_id' => $ing->id, 'entity_type' => Ingredient::class]);

        $payload = [
            'quantity' => 1.0,
            'location_id' => $kitchen->id,
            'components' => [
                ['entity_id' => $ing->id, 'entity_type' => 'ingredient', 'quantity' => 2.0, 'sources' => [
                    ['location_id' => $freezer->id, 'quantity' => 2.0],
                ]],
            ],
        ];

        $this->actingAs($user)
            ->postJson("/api/preparations/{$preparation->id}/prepare", $payload)
            ->assertStatus(400)
            ->assertJsonFragment(['message' => "Impossible d'utiliser un emplacement de type congélateur ('Congélateur principal') pour le composant 'Viande'"]);

        $this->assertDatabaseHas('ingredient_location', ['ingredient_id' => $ing->id, 'location_id' => $freezer->id, 'quantity' => 5.0]);
    }

    /** Scénario : échec lorsque la somme des sources ne correspond pas à la quantité requise. */
    public function test_it_fails_when_source_sum_does_not_match_required_quantity(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);

        $ing = Ingredient::factory()->create(['company_id' => $company->id, 'name' => 'Farine', 'unit' => 'kg']);
        $location1 = Location::factory()->create(['company_id' => $company->id, 'name' => 'Réserve 1']);
        $location2 = Location::factory()->create(['company_id' => $company->id, 'name' => 'Réserve 2']);
        $destination = Location::factory()->create(['company_id' => $company->id, 'name' => 'Cuisine']);

        $ing->locations()->updateExistingPivot($location1->id, ['quantity' => 5.0]);
        $ing->locations()->updateExistingPivot($location2->id, ['quantity' => 3.0]);

        $preparation = Preparation::factory()->create(['company_id' => $company->id, 'name' => 'Simple preparation', 'unit' => 'kg']);
        PreparationEntity::create(['preparation_id' => $preparation->id, 'entity_id' => $ing->id, 'entity_type' => Ingredient::class]);

        $payload = [
            'quantity' => 2.0,
            'location_id' => $destination->id,
            'components' => [
                ['entity_id' => $ing->id, 'entity_type' => 'ingredient', 'quantity' => 6.0, 'sources' => [
                    ['location_id' => $location1->id, 'quantity' => 3.0],
                    ['location_id' => $location2->id, 'quantity' => 2.0],
                ]],
            ],
        ];

        $this->actingAs($user)
            ->postJson("/api/preparations/{$preparation->id}/prepare", $payload)
            ->assertStatus(400)
            ->assertJsonFragment(['message' => "La somme des quantités des sources (5) ne correspond pas à la quantité requise (6) pour 'Farine'"]);

        $this->assertDatabaseHas('ingredient_location', ['ingredient_id' => $ing->id, 'location_id' => $location1->id, 'quantity' => 5.0]);
        $this->assertDatabaseHas('ingredient_location', ['ingredient_id' => $ing->id, 'location_id' => $location2->id, 'quantity' => 3.0]);
    }

    /** Scénario : préparation avec cumul de quantité existante. */
    public function test_it_adds_to_existing_quantity(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);

        $ing = Ingredient::factory()->create(['company_id' => $company->id, 'name' => 'Farine', 'unit' => 'kg']);
        $source = Location::factory()->create(['company_id' => $company->id, 'name' => 'Réserve']);
        $destination = Location::factory()->create(['company_id' => $company->id, 'name' => 'Cuisine']);

        $ing->locations()->updateExistingPivot($source->id, ['quantity' => 10.0]);

        $preparation = Preparation::factory()->create(['company_id' => $company->id, 'name' => 'Simple preparation', 'unit' => 'kg']);
        PreparationEntity::create(['preparation_id' => $preparation->id, 'entity_id' => $ing->id, 'entity_type' => Ingredient::class]);

        // quantité initiale
        $preparation->locations()->updateExistingPivot($destination->id, ['quantity' => 1.5]);

        $payload = [
            'quantity' => 2.5,
            'location_id' => $destination->id,
            'components' => [
                ['entity_id' => $ing->id, 'entity_type' => 'ingredient', 'quantity' => 3.0, 'sources' => [
                    ['location_id' => $source->id, 'quantity' => 3.0],
                ]],
            ],
        ];

        $this->actingAs($user)
            ->postJson("/api/preparations/{$preparation->id}/prepare", $payload)
            ->assertStatus(200);

        $this->assertDatabaseHas('location_preparation', ['preparation_id' => $preparation->id, 'location_id' => $destination->id, 'quantity' => 4.0]);
    }
}
