<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Company;
use App\Models\Ingredient;
use App\Models\Location;
use App\Models\LocationType;
use App\Models\Preparation;
use App\Models\PreparationEntity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Class PreparationControllerTest
 *
 * Cette suite de tests couvre les scénarios de création, mise à jour
 * (avec entities_to_add / entities_to_remove), destruction
 * d'une préparation et la préparation elle-même avec prélèvement
 * de composants depuis plusieurs emplacements.
 */
class PreparationControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Scénario : création échoue avec une seule entité.
     */
    public function test_it_fails_to_create_with_single_entity(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $ing = Ingredient::factory()->create(['company_id' => $company->id]);

        $payload = [
            'name' => 'Solo Entity',
            'unit' => 'g',
            'entities' => [['id' => $ing->id, 'type' => 'ingredient']],
            'categories' => ['Dessert'],
        ];

        $this->actingAs($user)
            ->postJson('/api/preparations', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors('entities');
    }

    /**
     * Scénario : création échoue sans catégories.
     */
    public function test_it_fails_to_create_without_categories(): void
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
            ->assertJsonValidationErrors('categories');
    }

    /**
     * Scénario : création avec deux ingrédients.
     */
    public function test_it_creates_with_two_ingredients(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $ing1 = Ingredient::factory()->create(['company_id' => $company->id]);
        $ing2 = Ingredient::factory()->create(['company_id' => $company->id]);

        $payload = [
            'name' => 'Dual Ingredient',
            'unit' => 'kg',
            'entities' => [
                ['id' => $ing1->id, 'type' => 'ingredient'],
                ['id' => $ing2->id, 'type' => 'ingredient'],
            ],
            'categories' => ['Dessert'],
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

    /**
     * Scénario : création avec deux ingrédients et des catégories.
     */
    public function test_it_creates_with_two_ingredients_and_category(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $ing1 = Ingredient::factory()->create(['company_id' => $company->id]);
        $ing2 = Ingredient::factory()->create(['company_id' => $company->id]);

        $payload = [
            'name' => 'Dual Ingredient',
            'unit' => 'kg',
            'entities' => [
                ['id' => $ing1->id, 'type' => 'ingredient'],
                ['id' => $ing2->id, 'type' => 'ingredient'],
            ],
            'categories' => ['Dessert', 'Pâtisserie'],
        ];

        $response = $this->actingAs($user)
            ->postJson('/api/preparations', $payload)
            ->assertStatus(201);

        $preparationId = $response->json('preparation.id');

        $this->assertDatabaseHas('preparation_entities', [
            'entity_id' => $ing1->id,
            'entity_type' => Ingredient::class,
        ]);
        $this->assertDatabaseHas('preparation_entities', [
            'entity_id' => $ing2->id,
            'entity_type' => Ingredient::class,
        ]);

        // Vérifier que les catégories ont été créées et associées
        $dessertCategory = Category::where('name', 'Dessert')->where('company_id', $company->id)->first();
        $patisserieCategory = Category::where('name', 'Pâtisserie')->where('company_id', $company->id)->first();

        $this->assertNotNull($dessertCategory);
        $this->assertNotNull($patisserieCategory);

        $this->assertDatabaseHas('category_preparation', [
            'category_id' => $dessertCategory->id,
            'preparation_id' => $preparationId,
        ]);
        $this->assertDatabaseHas('category_preparation', [
            'category_id' => $patisserieCategory->id,
            'preparation_id' => $preparationId,
        ]);
    }

    /**
     * Scénario : création avec deux préparations.
     */
    public function test_it_creates_with_two_preparations(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $pre1 = Preparation::factory()->create(['company_id' => $company->id]);
        $pre2 = Preparation::factory()->create(['company_id' => $company->id]);

        $payload = [
            'name' => 'Dual Preparation',
            'unit' => 'L',
            'entities' => [
                ['id' => $pre1->id, 'type' => 'preparation'],
                ['id' => $pre2->id, 'type' => 'preparation'],
            ],
            'categories' => ['Boisson'],
        ];

        $response = $this->actingAs($user)
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

        // Vérifier que la catégorie a été créée
        $this->assertDatabaseHas('categories', [
            'name' => 'Boisson',
            'company_id' => $company->id,
        ]);
    }

    /**
     * Scénario : création mixte (1 ingrédient + 1 préparation).
     */
    public function test_it_creates_with_mixed_entities(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $ing = Ingredient::factory()->create(['company_id' => $company->id]);
        $pre = Preparation::factory()->create(['company_id' => $company->id]);

        $payload = [
            'name' => 'Mixed',
            'unit' => 'g',
            'entities' => [
                ['id' => $ing->id, 'type' => 'ingredient'],
                ['id' => $pre->id, 'type' => 'preparation'],
            ],
            'categories' => ['Mixte'],
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

    /**
     * Scénario : mise à jour sans clés d'entités.
     */
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

    /**
     * Scénario : mise à jour des catégories d'une préparation.
     */
    public function test_it_updates_categories(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);

        // Créer une catégorie existante
        $oldCategory = Category::factory()->create([
            'name' => 'OldCategory',
            'company_id' => $company->id,
        ]);

        // Créer une préparation avec la catégorie existante
        $prep = Preparation::factory()->create(['company_id' => $company->id]);
        $prep->categories()->attach($oldCategory->id);

        // Mise à jour avec de nouvelles catégories
        $payload = [
            'categories' => ['NewCategory1', 'NewCategory2'],
        ];

        $this->actingAs($user)
            ->putJson("/api/preparations/{$prep->id}", $payload)
            ->assertStatus(200);

        // Vérifier que les anciennes catégories ont été remplacées
        $this->assertDatabaseMissing('category_preparation', [
            'category_id' => $oldCategory->id,
            'preparation_id' => $prep->id,
        ]);

        // Vérifier que les nouvelles catégories ont été ajoutées
        $newCategory1 = Category::where('name', 'NewCategory1')->where('company_id', $company->id)->first();
        $newCategory2 = Category::where('name', 'NewCategory2')->where('company_id', $company->id)->first();

        $this->assertNotNull($newCategory1);
        $this->assertNotNull($newCategory2);

        $this->assertDatabaseHas('category_preparation', [
            'category_id' => $newCategory1->id,
            'preparation_id' => $prep->id,
        ]);
        $this->assertDatabaseHas('category_preparation', [
            'category_id' => $newCategory2->id,
            'preparation_id' => $prep->id,
        ]);
    }

    /**
     * Scénario : suppression d'une entité via entities_to_remove.
     */
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

    /**
     * Scénario : ajout d'une entité via entities_to_add.
     */
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

    /**
     * Scénario : suppression et ajout simultanés.
     */
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

    /**
     * Scénario : mise à jour des quantités par emplacement.
     */
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
                [
                    'location_id' => $location->id,
                    'quantity' => 10.0,
                ],
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

    /**
     * Scénario : entities_to_remove présent mais vide -> ne fait rien.
     */
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

    /**
     * Scénario : suppression d'une préparation.
     */
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

    /**
     * Scénario : suppression d'une préparation non existante.
     */
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

    /**
     * Scénario : suppression d'une préparation hors société.
     */
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

    /**
     * Scénario : préparation avec vérification des catégories dans la réponse.
     */
    public function test_prepare_response_includes_categories(): void
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
        $preparation->categories()->attach($category->id);

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
                        [
                            'location_id' => $source->id,
                            'quantity' => 3.0,
                        ],
                    ],
                ],
            ],
        ];

        $response = $this->actingAs($user)
            ->postJson("/api/preparations/{$preparation->id}/prepare", $payload)
            ->assertStatus(200);

        // Vérifier que les catégories sont incluses dans la réponse
        $this->assertArrayHasKey('categories', $response->json('preparation'));
        $this->assertCount(1, $response->json('preparation.categories'));
        $this->assertEquals('TestCategory', $response->json('preparation.categories.0.name'));
    }

    /**
     * Scénario : préparation réussie avec un seul emplacement source.
     */
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
        $location1 = Location::factory()->create([
            'company_id' => $company->id,
            'name' => 'Réserve',
        ]);

        $location2 = Location::factory()->create([
            'company_id' => $company->id,
            'name' => 'Cuisine',
        ]);

        // Ajouter du stock aux ingrédients
        $ing1->locations()->updateExistingPivot($location1->id, ['quantity' => 10.0]);
        $ing2->locations()->updateExistingPivot($location1->id, ['quantity' => 8.0]);

        // Créer une préparation
        $preparation = Preparation::factory()->create([
            'company_id' => $company->id,
            'name' => 'Pâte à gâteau',
            'unit' => 'kg',
        ]);

        // Lier les ingrédients à la préparation
        PreparationEntity::create([
            'preparation_id' => $preparation->id,
            'entity_id' => $ing1->id,
            'entity_type' => Ingredient::class,
        ]);

        PreparationEntity::create([
            'preparation_id' => $preparation->id,
            'entity_id' => $ing2->id,
            'entity_type' => Ingredient::class,
        ]);

        // Effectuer la préparation
        $payload = [
            'quantity' => 2.5,
            'location_id' => $location2->id,
            'components' => [
                [
                    'entity_id' => $ing1->id,
                    'entity_type' => 'ingredient',
                    'quantity' => 3.0,
                    'sources' => [
                        [
                            'location_id' => $location1->id,
                            'quantity' => 3.0,
                        ],
                    ],
                ],
                [
                    'entity_id' => $ing2->id,
                    'entity_type' => 'ingredient',
                    'quantity' => 2.0,
                    'sources' => [
                        [
                            'location_id' => $location1->id,
                            'quantity' => 2.0,
                        ],
                    ],
                ],
            ],
        ];

        $this->actingAs($user)
            ->postJson("/api/preparations/{$preparation->id}/prepare", $payload)
            ->assertStatus(200);

        // Vérifier que les stocks ont été réduits
        $this->assertDatabaseHas('ingredient_location', [
            'ingredient_id' => $ing1->id,
            'location_id' => $location1->id,
            'quantity' => 7.0, // 10.0 - 3.0
        ]);

        $this->assertDatabaseHas('ingredient_location', [
            'ingredient_id' => $ing2->id,
            'location_id' => $location1->id,
            'quantity' => 6.0, // 8.0 - 2.0
        ]);

        // Vérifier que la préparation a été ajoutée à l'emplacement destination
        $this->assertDatabaseHas('location_preparation', [
            'preparation_id' => $preparation->id,
            'location_id' => $location2->id,
            'quantity' => 2.5,
        ]);
    }

    /**
     * Scénario : préparation avec plusieurs emplacements sources.
     */
    public function test_it_prepares_with_multiple_sources(): void
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
        $location1 = Location::factory()->create([
            'company_id' => $company->id,
            'name' => 'Réserve 1',
        ]);

        $location2 = Location::factory()->create([
            'company_id' => $company->id,
            'name' => 'Réserve 2',
        ]);

        $destination = Location::factory()->create([
            'company_id' => $company->id,
            'name' => 'Cuisine',
        ]);

        // Ajouter du stock à l'ingrédient dans différents emplacements
        $ing->locations()->updateExistingPivot($location1->id, ['quantity' => 5.0]);
        $ing->locations()->updateExistingPivot($location2->id, ['quantity' => 3.0]);

        // Créer une préparation
        $preparation = Preparation::factory()->create([
            'company_id' => $company->id,
            'name' => 'Simple preparation',
            'unit' => 'kg',
        ]);

        // Lier l'ingrédient à la préparation
        PreparationEntity::create([
            'preparation_id' => $preparation->id,
            'entity_id' => $ing->id,
            'entity_type' => Ingredient::class,
        ]);

        // Effectuer la préparation avec prélèvement depuis plusieurs emplacements
        $payload = [
            'quantity' => 2.0,
            'location_id' => $destination->id,
            'components' => [
                [
                    'entity_id' => $ing->id,
                    'entity_type' => 'ingredient',
                    'quantity' => 6.0,
                    'sources' => [
                        [
                            'location_id' => $location1->id,
                            'quantity' => 4.0,
                        ],
                        [
                            'location_id' => $location2->id,
                            'quantity' => 2.0,
                        ],
                    ],
                ],
            ],
        ];

        $this->actingAs($user)
            ->postJson("/api/preparations/{$preparation->id}/prepare", $payload)
            ->assertStatus(200);

        // Vérifier que les stocks ont été réduits aux bons emplacements
        $this->assertDatabaseHas('ingredient_location', [
            'ingredient_id' => $ing->id,
            'location_id' => $location1->id,
            'quantity' => 1.0, // 5.0 - 4.0
        ]);

        $this->assertDatabaseHas('ingredient_location', [
            'ingredient_id' => $ing->id,
            'location_id' => $location2->id,
            'quantity' => 1.0, // 3.0 - 2.0
        ]);

        // Vérifier que la préparation a été ajoutée
        $this->assertDatabaseHas('location_preparation', [
            'preparation_id' => $preparation->id,
            'location_id' => $destination->id,
            'quantity' => 2.0,
        ]);
    }

    /**
     * Scénario : échec de préparation avec stock insuffisant.
     */
    public function test_it_fails_to_prepare_with_insufficient_stock(): void
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
        $location1 = Location::factory()->create([
            'company_id' => $company->id,
            'name' => 'Réserve',
        ]);

        $location2 = Location::factory()->create([
            'company_id' => $company->id,
            'name' => 'Cuisine',
        ]);

        // Ajouter un stock limité
        $ing->locations()->updateExistingPivot($location1->id, ['quantity' => 2.0]);

        // Créer une préparation
        $preparation = Preparation::factory()->create([
            'company_id' => $company->id,
            'name' => 'Simple preparation',
            'unit' => 'kg',
        ]);

        // Lier l'ingrédient à la préparation
        PreparationEntity::create([
            'preparation_id' => $preparation->id,
            'entity_id' => $ing->id,
            'entity_type' => Ingredient::class,
        ]);

        // Tenter une préparation qui demande plus que le stock disponible
        $payload = [
            'quantity' => 1.0,
            'location_id' => $location2->id,
            'components' => [
                [
                    'entity_id' => $ing->id,
                    'entity_type' => 'ingredient',
                    'quantity' => 3.0, // Plus que les 2.0 disponibles
                    'sources' => [
                        [
                            'location_id' => $location1->id,
                            'quantity' => 3.0,
                        ],
                    ],
                ],
            ],
        ];

        $this->actingAs($user)
            ->postJson("/api/preparations/{$preparation->id}/prepare", $payload)
            ->assertStatus(400)
            ->assertJsonFragment(['message' => "Stock insuffisant pour 'Farine' à l'emplacement 'Réserve' (disponible: 2, requis: 3)"]);

        // Vérifier que le stock n'a pas été modifié (rollback réussi)
        $this->assertDatabaseHas('ingredient_location', [
            'ingredient_id' => $ing->id,
            'location_id' => $location1->id,
            'quantity' => 2.0, // Inchangé
        ]);
    }

    /**
     * Scénario : échec de préparation avec un congélateur.
     */
    public function test_it_fails_when_using_freezer_location(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);

        // Créer un type d'emplacement congélateur
        $freezerType = LocationType::firstOrCreate([
            'name' => 'Congélateur',
        ]);

        // Créer un ingrédient
        $ing = Ingredient::factory()->create([
            'company_id' => $company->id,
            'name' => 'Viande',
            'unit' => 'kg',
        ]);

        // Créer des emplacements dont un congélateur
        $freezer = Location::factory()->create([
            'company_id' => $company->id,
            'name' => 'Congélateur principal',
            'location_type_id' => $freezerType->id,
        ]);

        $kitchen = Location::factory()->create([
            'company_id' => $company->id,
            'name' => 'Cuisine',
        ]);

        // Ajouter du stock dans le congélateur
        $ing->locations()->updateExistingPivot($freezer->id, ['quantity' => 5.0]);

        // Créer une préparation
        $preparation = Preparation::factory()->create([
            'company_id' => $company->id,
            'name' => 'Préparation de viande',
            'unit' => 'kg',
        ]);

        // Lier l'ingrédient à la préparation
        PreparationEntity::create([
            'preparation_id' => $preparation->id,
            'entity_id' => $ing->id,
            'entity_type' => Ingredient::class,
        ]);

        // Tenter une préparation qui utilise un emplacement congélateur
        $payload = [
            'quantity' => 1.0,
            'location_id' => $kitchen->id,
            'components' => [
                [
                    'entity_id' => $ing->id,
                    'entity_type' => 'ingredient',
                    'quantity' => 2.0,
                    'sources' => [
                        [
                            'location_id' => $freezer->id,
                            'quantity' => 2.0,
                        ],
                    ],
                ],
            ],
        ];

        $this->actingAs($user)
            ->postJson("/api/preparations/{$preparation->id}/prepare", $payload)
            ->assertStatus(400)
            ->assertJsonFragment(['message' => "Impossible d'utiliser un emplacement de type congélateur ('Congélateur principal') pour le composant 'Viande'"]);

        // Vérifier que le stock n'a pas été modifié
        $this->assertDatabaseHas('ingredient_location', [
            'ingredient_id' => $ing->id,
            'location_id' => $freezer->id,
            'quantity' => 5.0, // Inchangé
        ]);
    }

    /**
     * Scénario : échec lorsque la somme des sources ne correspond pas à la quantité requise.
     */
    public function test_it_fails_when_source_sum_does_not_match_required_quantity(): void
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
        $location1 = Location::factory()->create([
            'company_id' => $company->id,
            'name' => 'Réserve 1',
        ]);

        $location2 = Location::factory()->create([
            'company_id' => $company->id,
            'name' => 'Réserve 2',
        ]);

        $destination = Location::factory()->create([
            'company_id' => $company->id,
            'name' => 'Cuisine',
        ]);

        // Ajouter du stock
        $ing->locations()->updateExistingPivot($location1->id, ['quantity' => 5.0]);
        $ing->locations()->updateExistingPivot($location2->id, ['quantity' => 3.0]);

        // Créer une préparation
        $preparation = Preparation::factory()->create([
            'company_id' => $company->id,
            'name' => 'Simple preparation',
            'unit' => 'kg',
        ]);

        // Lier l'ingrédient à la préparation
        PreparationEntity::create([
            'preparation_id' => $preparation->id,
            'entity_id' => $ing->id,
            'entity_type' => Ingredient::class,
        ]);

        // Effectuer une préparation avec des sources dont la somme ne correspond pas
        $payload = [
            'quantity' => 2.0,
            'location_id' => $destination->id,
            'components' => [
                [
                    'entity_id' => $ing->id,
                    'entity_type' => 'ingredient',
                    'quantity' => 6.0,
                    'sources' => [
                        [
                            'location_id' => $location1->id,
                            'quantity' => 3.0,
                        ],
                        [
                            'location_id' => $location2->id,
                            'quantity' => 2.0,
                        ],
                        // Total 5.0 alors que quantity est 6.0
                    ],
                ],
            ],
        ];

        $this->actingAs($user)
            ->postJson("/api/preparations/{$preparation->id}/prepare", $payload)
            ->assertStatus(400)
            ->assertJsonFragment(['message' => "La somme des quantités des sources (5) ne correspond pas à la quantité requise (6) pour 'Farine'"]);

        // Vérifier que les stocks n'ont pas été modifiés
        $this->assertDatabaseHas('ingredient_location', [
            'ingredient_id' => $ing->id,
            'location_id' => $location1->id,
            'quantity' => 5.0,
        ]);

        $this->assertDatabaseHas('ingredient_location', [
            'ingredient_id' => $ing->id,
            'location_id' => $location2->id,
            'quantity' => 3.0,
        ]);
    }

    /**
     * Scénario : préparation avec cumul de quantité existante.
     */
    public function test_it_adds_to_existing_quantity(): void
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

        // Créer une préparation
        $preparation = Preparation::factory()->create([
            'company_id' => $company->id,
            'name' => 'Simple preparation',
            'unit' => 'kg',
        ]);

        // Lier l'ingrédient à la préparation
        PreparationEntity::create([
            'preparation_id' => $preparation->id,
            'entity_id' => $ing->id,
            'entity_type' => Ingredient::class,
        ]);

        // Ajouter une quantité initiale de la préparation
        $preparation->locations()->updateExistingPivot($destination->id, ['quantity' => 1.5]);

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
                        [
                            'location_id' => $source->id,
                            'quantity' => 3.0,
                        ],
                    ],
                ],
            ],
        ];

        $this->actingAs($user)
            ->postJson("/api/preparations/{$preparation->id}/prepare", $payload)
            ->assertStatus(200);

        // Vérifier que la quantité a été cumulée
        $this->assertDatabaseHas('location_preparation', [
            'preparation_id' => $preparation->id,
            'location_id' => $destination->id,
            'quantity' => 4.0, // 1.5 + 2.5
        ]);
    }
}
