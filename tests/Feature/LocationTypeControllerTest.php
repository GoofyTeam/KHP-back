<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Location;
use App\Models\LocationType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class LocationTypeControllerTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected $user;

    protected $company;

    protected function setUp(): void
    {
        parent::setUp();

        // Créer une entreprise et un utilisateur pour les tests
        $this->company = Company::factory()->create();
        $this->user = User::factory()->create([
            'company_id' => $this->company->id,
        ]);
    }

    /**
     * Ce test vérifie qu'un utilisateur authentifié peut créer un nouveau type de localisation
     * pour son entreprise avec un nom valide et unique.
     */
    public function test_store_creates_new_location_type()
    {
        $this->actingAs($this->user);

        $typeName = 'Réserve sèche '.uniqid();

        $response = $this->postJson('/api/location-types', [
            'name' => $typeName,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', $typeName)
            ->assertJsonPath('data.company_id', $this->company->id)
            ->assertJsonPath('data.is_default', false);

        $this->assertDatabaseHas('location_types', [
            'name' => $typeName,
            'company_id' => $this->company->id,
        ]);
    }

    /**
     * Ce test vérifie qu'un utilisateur ne peut pas créer un type avec un nom déjà utilisé
     * dans la même entreprise. La validation doit échouer avec une erreur appropriée.
     */
    public function test_cannot_create_duplicate_location_type()
    {
        $this->actingAs($this->user);

        $typeName = 'Cave à vin '.uniqid();

        // Créer d'abord un type
        LocationType::factory()->create([
            'name' => $typeName,
            'company_id' => $this->company->id,
        ]);

        // Essayer de créer un type avec le même nom
        $response = $this->postJson('/api/location-types', [
            'name' => $typeName,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('name');
    }

    /**
     * Ce test vérifie qu'un utilisateur peut mettre à jour le nom d'un type de localisation
     * qu'il a créé, tant que ce n'est pas un type par défaut.
     */
    public function test_update_changes_location_type_name()
    {
        $this->actingAs($this->user);

        $originalName = 'Réserve '.uniqid();
        $newName = 'Réserve principale '.uniqid();

        $locationType = LocationType::factory()->create([
            'name' => $originalName,
            'company_id' => $this->company->id,
            'is_default' => false,
        ]);

        $response = $this->putJson("/api/location-types/{$locationType->id}", [
            'name' => $newName,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.name', $newName);

        $this->assertDatabaseHas('location_types', [
            'id' => $locationType->id,
            'name' => $newName,
        ]);
    }

    /**
     * Ce test vérifie qu'un utilisateur ne peut pas modifier un type par défaut.
     * Le système doit rejeter cette demande avec une erreur explicite.
     */
    public function test_cannot_update_default_location_type()
    {
        $this->actingAs($this->user);

        $defaultName = 'Congélateur '.uniqid();
        $newName = 'Super Congélateur '.uniqid();

        $defaultType = LocationType::factory()->create([
            'name' => $defaultName,
            'company_id' => $this->company->id,
            'is_default' => true,
        ]);

        $response = $this->putJson("/api/location-types/{$defaultType->id}", [
            'name' => $newName,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('name');
    }

    /**
     * Ce test vérifie qu'un utilisateur peut supprimer un type de localisation non utilisé
     * qu'il a créé, à condition qu'il ne soit pas un type par défaut.
     */
    public function test_destroy_removes_unused_location_type()
    {
        $this->actingAs($this->user);

        $typeName = 'Type temporaire '.uniqid();

        $locationType = LocationType::factory()->create([
            'name' => $typeName,
            'company_id' => $this->company->id,
            'is_default' => false,
        ]);

        $response = $this->deleteJson("/api/location-types/{$locationType->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('location_types', [
            'id' => $locationType->id,
        ]);
    }

    /**
     * Ce test vérifie qu'un utilisateur ne peut pas supprimer un type de localisation
     * par défaut. Le système doit rejeter cette demande avec une erreur explicite.
     */
    public function test_cannot_destroy_default_location_type()
    {
        $this->actingAs($this->user);

        $defaultName = 'Réfrigérateur '.uniqid();

        $defaultType = LocationType::factory()->create([
            'name' => $defaultName,
            'company_id' => $this->company->id,
            'is_default' => true,
        ]);

        $response = $this->deleteJson("/api/location-types/{$defaultType->id}");

        $response->assertStatus(403);
        $this->assertDatabaseHas('location_types', [
            'id' => $defaultType->id,
        ]);
    }

    /**
     * Ce test vérifie qu'un utilisateur ne peut pas supprimer un type de localisation
     * actuellement utilisé par des emplacements. Cela protège l'intégrité des données.
     */
    public function test_cannot_destroy_location_type_in_use()
    {
        $this->actingAs($this->user);

        $typeName = 'Réserve utilisée '.uniqid();
        $locationName = 'Emplacement test '.uniqid();

        $locationType = LocationType::factory()->create([
            'name' => $typeName,
            'company_id' => $this->company->id,
            'is_default' => false,
        ]);

        // Créer un emplacement qui utilise ce type
        Location::factory()->create([
            'name' => $locationName,
            'company_id' => $this->company->id,
            'location_type_id' => $locationType->id,
        ]);

        $response = $this->deleteJson("/api/location-types/{$locationType->id}");

        $response->assertStatus(409);
        $this->assertDatabaseHas('location_types', [
            'id' => $locationType->id,
        ]);
    }

    /**
     * Ce test vérifie qu'un utilisateur ne peut pas accéder aux types de localisation
     * d'une autre entreprise, assurant ainsi la séparation des données.
     */
    public function test_cannot_access_location_type_from_other_company()
    {
        $this->actingAs($this->user);

        $otherTypeName = 'Type autre entreprise '.uniqid();

        // Créer une autre entreprise avec son propre type
        $otherCompany = Company::factory()->create();
        $otherType = LocationType::factory()->create([
            'name' => $otherTypeName,
            'company_id' => $otherCompany->id,
        ]);

        // Essayer d'accéder au type de l'autre entreprise
        $response = $this->putJson("/api/location-types/{$otherType->id}", [
            'name' => 'Type modifié',
        ]);

        $response->assertStatus(404);
    }
}
