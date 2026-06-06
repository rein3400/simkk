<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_endpoint(): void
    {
        $this->get('/api/health')->assertOk()->assertJson(['ok' => true]);
    }

    public function test_login_returns_token(): void
    {
        User::create([
            'username'     => 'kasir',
            'password'     => bcrypt('simkk-2026'),
            'nama_lengkap' => 'Nadia Putri',
            'level'        => 'Kasir',
            'shift'        => 'Pagi',
        ]);

        $this->postJson('/api/login', [
            'username' => 'kasir',
            'password' => 'simkk-2026',
            'level'    => 'Kasir',
        ])->assertOk()
          ->assertJsonStructure(['token', 'user']);
    }

    public function test_login_fails_with_wrong_password(): void
    {
        User::create([
            'username'     => 'kasir',
            'password'     => bcrypt('simkk-2026'),
            'nama_lengkap' => 'Nadia Putri',
            'level'        => 'Kasir',
            'shift'        => 'Pagi',
        ]);

        $this->postJson('/api/login', [
            'username' => 'kasir',
            'password' => 'wrong',
            'level'    => 'Kasir',
        ])->assertStatus(401);
    }

    public function test_bootstrap_requires_auth(): void
    {
        $this->get('/api/bootstrap')->assertStatus(401);
    }

    public function test_role_gate_blocks_wrong_role(): void
    {
        $user = User::create([
            'username'     => 'terapis',
            'password'     => bcrypt('simkk-2026'),
            'nama_lengkap' => 'dr. Melati',
            'level'        => 'Terapis',
            'shift'        => 'Treatment A',
        ]);

        $this->actingAs($user)
            ->postJson('/api/transactions/pay', [])
            ->assertStatus(403);
    }
}
