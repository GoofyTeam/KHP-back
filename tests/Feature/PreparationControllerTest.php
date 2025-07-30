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
 * Cette suite de tests couvre les scénarios de création et de mise à jour
 * d'une préparation avec ses entités liées (ingrédients et/ou autres préparations).
 *
 * Use cases testés :
 * - Validation minimale à la création (au moins 2 entités requises)
 * - Création avec 2 ingrédients
 * - Création avec 2 préparations
 * - Création mixte (1 ingrédient + 1 préparation)
 * - Création avec plus de 2 entités
 * - Mise à jour sans fournir d'entités (ne doit pas effacer les liaisons existantes)
 * - Mise à jour des entités lorsque fournies (remplacement complet des liens)
 */
class PreparationControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Scénario : création échoue avec une seule entité.
     *
     * Attendu : validation 422 (au moins 2 entités requises en création).
     */
    public function test_it_fails_to_create_with_single_entity(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $ingredient = Ingredient::factory()->create(['company_id' => $company->id]);

        $payload = [
            'name' => 'Too Few Entities',
            'unit' => 'g',
            'entities' => [[
                'id' => $ingredient->id,
                'type' => 'ingredient',
            ]],
        ];

        $response = $this->actingAs($user)->postJson('/api/preparations', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('entities');
    }

    /**
     * Scénario : création réussie avec deux ingrédients.
     *
     * Attendu : status 201 et deux liens ingredient/preparation créés.
     */
    public function test_it_creates_with_two_ingredients(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $ing1 = Ingredient::factory()->create(['company_id' => $company->id]);
        $ing2 = Ingredient::factory()->create(['company_id' => $company->id]);

        $payload = [
            'name' => 'Dual Ingredient Prep',
            'unit' => 'kg',
            'entities' => [
                ['id' => $ing1->id, 'type' => 'ingredient'],
                ['id' => $ing2->id, 'type' => 'ingredient'],
            ],
        ];

        $response = $this->actingAs($user)->postJson('/api/preparations', $payload);

        $response->assertStatus(201);
        $entities = $response->json('preparation.entities');
        $this->assertCount(2, $entities);
        $this->assertDatabaseHas('preparation_entities', ['entity_id' => $ing1->id, 'entity_type' => Ingredient::class]);
        $this->assertDatabaseHas('preparation_entities', ['entity_id' => $ing2->id, 'entity_type' => Ingredient::class]);
    }

    /**
     * Scénario : création réussie avec deux préparations.
     *
     * Attendu : status 201 et deux liens preparation/preparation créés.
     */
    public function test_it_creates_with_two_preparations(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $pre1 = Preparation::factory()->create(['company_id' => $company->id]);
        $pre2 = Preparation::factory()->create(['company_id' => $company->id]);

        $payload = [
            'name' => 'Dual Prep Prep',
            'unit' => 'L',
            'entities' => [
                ['id' => $pre1->id, 'type' => 'preparation'],
                ['id' => $pre2->id, 'type' => 'preparation'],
            ],
        ];

        $response = $this->actingAs($user)->postJson('/api/preparations', $payload);

        $response->assertStatus(201);
        $entities = $response->json('preparation.entities');
        $this->assertCount(2, $entities);
        $this->assertDatabaseHas('preparation_entities', ['entity_id' => $pre1->id, 'entity_type' => Preparation::class]);
        $this->assertDatabaseHas('preparation_entities', ['entity_id' => $pre2->id, 'entity_type' => Preparation::class]);
    }

    /**
     * Scénario : création mixte avec un ingrédient et une préparation.
     *
     * Attendu : status 201 et liens créés pour chaque type.
     */
    public function test_it_creates_with_mixed_entities(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $ing = Ingredient::factory()->create(['company_id' => $company->id]);
        $pre = Preparation::factory()->create(['company_id' => $company->id]);

        $payload = [
            'name' => 'Mixed Entities Prep',
            'unit' => 'kg',
            'entities' => [
                ['id' => $ing->id, 'type' => 'ingredient'],
                ['id' => $pre->id, 'type' => 'preparation'],
            ],
        ];

        $response = $this->actingAs($user)->postJson('/api/preparations', $payload);

        $response->assertStatus(201);
        $entities = $response->json('preparation.entities');
        $this->assertTrue(collect($entities)->contains(fn ($e) => $e['entity_type'] === Ingredient::class && $e['entity_id'] === $ing->id));
        $this->assertTrue(collect($entities)->contains(fn ($e) => $e['entity_type'] === Preparation::class && $e['entity_id'] === $pre->id));
    }

    /**
     * Scénario : création avec plus de deux entités.
     *
     * Attendu : status 201 et nombre total d'entités retourné égal au nombre fourni.
     */
    public function test_it_creates_with_multiple_entities(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $ing1 = Ingredient::factory()->create(['company_id' => $company->id]);
        $ing2 = Ingredient::factory()->create(['company_id' => $company->id]);
        $pre = Preparation::factory()->create(['company_id' => $company->id]);

        $payload = [
            'name' => 'Multi Entities Prep',
            'unit' => 'g',
            'entities' => [
                ['id' => $ing1->id, 'type' => 'ingredient'],
                ['id' => $ing2->id, 'type' => 'ingredient'],
                ['id' => $pre->id,  'type' => 'preparation'],
            ],
        ];

        $response = $this->actingAs($user)->postJson('/api/preparations', $payload);

        $response->assertStatus(201);
        $entities = $response->json('preparation.entities');
        $this->assertCount(3, $entities);
    }

    /**
     * Scénario : mise à jour sans fournir d'entités.
     *
     * Attendu : status 200, modification des champs autorisés
     * et les entités précédemment liées restent intactes.
     */
    public function test_it_updates_without_entities(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $prep = Preparation::factory()->create(['company_id' => $company->id]);
        // Liaisons initiales
        $ing = Ingredient::factory()->create(['company_id' => $company->id]);
        PreparationEntity::create(['preparation_id' => $prep->id, 'entity_id' => $ing->id, 'entity_type' => Ingredient::class]);

        $payload = ['name' => 'Updated Name Only'];
        $response = $this->actingAs($user)->putJson("/api/preparations/{$prep->id}", $payload);

        $response->assertStatus(200);
        $this->assertDatabaseHas('preparations', ['id' => $prep->id, 'name' => 'Updated Name Only']);
        $this->assertDatabaseHas('preparation_entities', ['preparation_id' => $prep->id, 'entity_id' => $ing->id]);
    }

    /**
     * Scénario : mise à jour des entités lorsqu'elles sont fournies.
     *
     * Attendu : status 200, les anciennes liaisons sont supprimées
     * et remplacées par celles envoyées dans la requête.
     */
    public function test_it_updates_entities_when_provided(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $prep = Preparation::factory()->create(['company_id' => $company->id]);
        // Anciennes liaisons
        $oldIng = Ingredient::factory()->create(['company_id' => $company->id]);
        $oldPrep = Preparation::factory()->create(['company_id' => $company->id]);
        PreparationEntity::create(['preparation_id' => $prep->id, 'entity_id' => $oldIng->id, 'entity_type' => Ingredient::class]);
        PreparationEntity::create(['preparation_id' => $prep->id, 'entity_id' => $oldPrep->id, 'entity_type' => Preparation::class]);

        // Nouvelles liaisons
        $newIng = Ingredient::factory()->create(['company_id' => $company->id]);
        $newPrep = Preparation::factory()->create(['company_id' => $company->id]);
        $payload = [
            'entities' => [
                ['id' => $newIng->id, 'type' => 'ingredient'],
                ['id' => $newPrep->id, 'type' => 'preparation'],
            ],
        ];

        $response = $this->actingAs($user)->putJson("/api/preparations/{$prep->id}", $payload);

        $response->assertStatus(200);
        $this->assertDatabaseMissing('preparation_entities', ['entity_id' => $oldIng->id]);
        $this->assertDatabaseHas('preparation_entities', ['entity_id' => $newIng->id, 'entity_type' => Ingredient::class]);
        $this->assertDatabaseHas('preparation_entities', ['entity_id' => $newPrep->id, 'entity_type' => Preparation::class]);
    }
}
