<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Company;
use App\Models\Location;
use App\Models\Ingredient;
use App\Models\LocationType;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;

class LocationControllerTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected $user;
    protected $company;
    protected $locationType;

    protected function setUp(): void
    {
        parent::setUp();

        // Créer une entreprise et un utilisateur pour les tests
        $this->company = Company::factory()->create();
        $this->user = User::factory()->create([
            'company_id' => $this->company->id
        ]);

        // Créer un type de localisation pour les tests
        $defaultName = 'Type par défaut ' . uniqid();
        $this->locationType = LocationType::factory()->create([
            'name' => $defaultName,
            'company_id' => $this->company->id,
            'is_default' => true
        ]);
    }

    /**
     * Ce test vérifie qu'un utilisateur authentifié peut créer un nouvel emplacement
     * pour son entreprise avec un nom et un type valides.
     */
    public function test_store_creates_new_location()
    {
        $this->actingAs($this->user);

        $locationName = 'Frigo principal ' . uniqid();

        $response = $this->postJson('/api/location', [
            'name' => $locationName,
            'location_type_id' => $this->locationType->id
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', $locationName)
            ->assertJsonPath('data.company_id', $this->company->id)
            ->assertJsonPath('data.location_type_id', $this->locationType->id);

        $this->assertDatabaseHas('locations', [
            'name' => $locationName,
            'company_id' => $this->company->id,
            'location_type_id' => $this->locationType->id
        ]);
    }

    /**
     * Ce test vérifie qu'un utilisateur ne peut pas créer un emplacement avec un nom déjà utilisé
     * dans la même entreprise. La validation doit échouer avec une erreur appropriée.
     */
    public function test_cannot_create_duplicate_location()
    {
        $this->actingAs($this->user);

        $locationName = 'Frigo cuisine ' . uniqid();

        // Créer d'abord un emplacement
        Location::factory()->create([
            'name' => $locationName,
            'company_id' => $this->company->id,
            'location_type_id' => $this->locationType->id
        ]);

        // Essayer de créer un emplacement avec le même nom
        $response = $this->postJson('/api/location', [
            'name' => $locationName,
            'location_type_id' => $this->locationType->id
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('name');
    }

    /**
     * Ce test vérifie que le système rejette la création d'un emplacement avec un type
     * appartenant à une autre entreprise, garantissant l'intégrité des données.
     */
    public function test_cannot_create_location_with_other_company_type()
    {
        $this->actingAs($this->user);

        $otherTypeName = 'Type autre entreprise ' . uniqid();
        $locationName = 'Nouvel emplacement ' . uniqid();

        // Créer un type pour une autre entreprise
        $otherCompany = Company::factory()->create();
        $otherType = LocationType::factory()->create([
            'name' => $otherTypeName,
            'company_id' => $otherCompany->id
        ]);

        $response = $this->postJson('/api/location', [
            'name' => $locationName,
            'location_type_id' => $otherType->id
        ]);

        $response->assertStatus(404);
    }

    /**
     * Ce test vérifie qu'un utilisateur peut mettre à jour le nom et le type
     * d'un emplacement qu'il a créé.
     */
    public function test_update_changes_location_name_and_type()
    {
        $this->actingAs($this->user);

        $originalName = 'Réserve initiale ' . uniqid();
        $newName = 'Réserve modifiée ' . uniqid();
        $newTypeName = 'Réserve ' . uniqid();

        $location = Location::factory()->create([
            'name' => $originalName,
            'company_id' => $this->company->id,
            'location_type_id' => $this->locationType->id
        ]);

        // Créer un nouveau type pour la mise à jour
        $newType = LocationType::factory()->create([
            'name' => $newTypeName,
            'company_id' => $this->company->id,
            'is_default' => false
        ]);

        $response = $this->putJson("/api/location/{$location->id}", [
            'name' => $newName,
            'location_type_id' => $newType->id
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.name', $newName)
            ->assertJsonPath('data.location_type_id', $newType->id);

        $this->assertDatabaseHas('locations', [
            'id' => $location->id,
            'name' => $newName,
            'location_type_id' => $newType->id
        ]);
    }

    /**
     * Ce test vérifie qu'un utilisateur peut mettre à jour seulement le nom
     * d'un emplacement sans changer son type.
     */
    public function test_update_name_only()
    {
        $this->actingAs($this->user);

        $originalName = 'Frigo 1 ' . uniqid();
        $newName = 'Frigo principal ' . uniqid();

        $location = Location::factory()->create([
            'name' => $originalName,
            'company_id' => $this->company->id,
            'location_type_id' => $this->locationType->id
        ]);

        $response = $this->putJson("/api/location/{$location->id}", [
            'name' => $newName
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.name', $newName)
            ->assertJsonPath('data.location_type_id', $this->locationType->id);
    }

    /**
     * Ce test vérifie qu'un utilisateur peut supprimer un emplacement non utilisé.
     */
    public function test_destroy_removes_unused_location()
    {
        $this->actingAs($this->user);

        $locationName = 'Emplacement temporaire ' . uniqid();

        $location = Location::factory()->create([
            'name' => $locationName,
            'company_id' => $this->company->id,
            'location_type_id' => $this->locationType->id
        ]);

        $response = $this->deleteJson("/api/location/{$location->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('locations', [
            'id' => $location->id
        ]);
    }

    /**
     * Ce test vérifie qu'un utilisateur ne peut pas supprimer un emplacement
     * qui contient des ingrédients, préservant ainsi l'intégrité des données.
     */
    public function test_cannot_destroy_location_with_ingredients()
    {
        $this->actingAs($this->user);

        $locationName = 'Emplacement avec produits ' . uniqid();
        $ingredientName = 'Tomate ' . uniqid();

        $location = Location::factory()->create([
            'name' => $locationName,
            'company_id' => $this->company->id,
            'location_type_id' => $this->locationType->id
        ]);

        // Créer un ingrédient associé à cet emplacement
        $ingredient = Ingredient::factory()->create([
            'name' => $ingredientName,
            'company_id' => $this->company->id,
            'unit' => 'kg'
        ]);

        $location->ingredients()->attach($ingredient->id);

        $response = $this->deleteJson("/api/location/{$location->id}");

        $response->assertStatus(409);
        $this->assertDatabaseHas('locations', [
            'id' => $location->id
        ]);
    }

    /**
     * Ce test vérifie qu'un utilisateur peut associer un emplacement existant
     * à un type de localisation différent.
     */
    public function test_assign_type_to_location()
    {
        $this->actingAs($this->user);

        $locationName = 'Emplacement à reclasser ' . uniqid();
        $newTypeName = 'Cave ' . uniqid();

        $location = Location::factory()->create([
            'name' => $locationName,
            'company_id' => $this->company->id,
            'location_type_id' => $this->locationType->id
        ]);

        // Créer un nouveau type pour l'assignation
        $newType = LocationType::factory()->create([
            'name' => $newTypeName,
            'company_id' => $this->company->id,
            'is_default' => false
        ]);

        $response = $this->postJson('/api/location/assign-type', [
            'location_id' => $location->id,
            'location_type_id' => $newType->id
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.location_type_id', $newType->id);

        $this->assertDatabaseHas('locations', [
            'id' => $location->id,
            'location_type_id' => $newType->id
        ]);
    }

    /**
     * Ce test vérifie qu'un utilisateur ne peut pas accéder aux emplacements
     * d'une autre entreprise, assurant ainsi la séparation des données.
     */
    public function test_cannot_access_location_from_other_company()
    {
        $this->actingAs($this->user);

        $otherTypeName = 'Type autre entreprise ' . uniqid();
        $otherLocationName = 'Emplacement autre entreprise ' . uniqid();

        // Créer une autre entreprise avec son propre emplacement
        $otherCompany = Company::factory()->create();
        $otherType = LocationType::factory()->create([
            'name' => $otherTypeName,
            'company_id' => $otherCompany->id
        ]);

        $otherLocation = Location::factory()->create([
            'name' => $otherLocationName,
            'company_id' => $otherCompany->id,
            'location_type_id' => $otherType->id
        ]);

        // Essayer d'accéder à l'emplacement de l'autre entreprise
        $response = $this->putJson("/api/location/{$otherLocation->id}", [
            'name' => 'Emplacement modifié'
        ]);

        $response->assertStatus(404);
    }
}
