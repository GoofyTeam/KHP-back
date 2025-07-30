<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Ingredient;
use App\Models\Preparation;
use App\Models\PreparationEntity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Class PreparationControllerTest
 *
 * Cette suite de tests couvre les scénarios de création, mise à jour
 * (avec entities_to_add / entities_to_remove) et destruction
 * d'une préparation et de ses liaisons (ingrédients ou préparations).
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
        ];

        $this->actingAs($user)
            ->postJson('/api/preparations', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors('entities');
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
     * Scénario : suppression d’une entité via entities_to_remove.
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
     * Scénario : ajout d’une entité via entities_to_add.
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
}
