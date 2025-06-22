<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_register()
    {
        $response = $this->postJson('/register', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertCreated();
        $this->assertArrayHasKey('token', $response->json());
    }

    public function test_login_and_access_protected_route()
    {
        $user = User::factory()->create([
            'password' => 'password',
        ]);

        $response = $this->postJson('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $token = $response->json('token');
        $response->assertOk();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/user')
            ->assertOk();
    }

    public function test_can_logout()
    {
        $user = User::factory()->create([
            'password' => 'password',
        ]);

        $token = $user->createToken('auth')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/logout')
            ->assertNoContent();
    }
}
