<?php

use App\Models\Community;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

function seedAuthDemoData(): void
{
    test()->seed(DatabaseSeeder::class);
}

it('issues a sanctum token through dev login', function (): void {
    seedAuthDemoData();

    $response = $this->postJson('/api/v1/auth/dev-login', [
        'email' => 'admin@demo',
    ]);

    $response
        ->assertOk()
        ->assertJsonStructure([
            'token',
            'token_type',
        ])
        ->assertJson([
            'token_type' => 'Bearer',
        ]);
});

it('returns the authenticated user communities and effective permissions', function (): void {
    seedAuthDemoData();

    $token = User::query()
        ->where('email', 'admin@demo')
        ->firstOrFail()
        ->createToken('test-token')
        ->plainTextToken;

    $response = $this
        ->withToken($token)
        ->getJson('/api/v1/auth/me');

    $response
        ->assertOk()
        ->assertJsonPath('user.email', 'admin@demo')
        ->assertJsonPath('communities.0.role', 'community-admin');

    expect($response->json('communities.0.permissions'))
        ->toMatchArray([
            'plugins.manage' => true,
            'ingestion.run' => true,
            'analytics.view' => true,
        ]);
});

it('rejects protected routes without a bearer token', function (): void {
    $this
        ->getJson('/api/v1/auth/me')
        ->assertUnauthorized()
        ->assertJson([
            'message' => 'Unauthenticated.',
            'code' => 'auth.unauthenticated',
        ]);
});

it('rejects users without the scoped community permission', function (): void {
    seedAuthDemoData();

    Route::middleware(['api', 'auth:sanctum', 'community.permission:plugins.manage'])
        ->get('/api/v1/test/communities/{community}/plugins/manage', fn (Community $community): array => [
            'community' => $community->slug,
        ]);

    $community = Community::query()->orderBy('id')->firstOrFail();
    $token = User::query()
        ->where('email', 'member@demo')
        ->firstOrFail()
        ->createToken('test-token')
        ->plainTextToken;

    $this
        ->withToken($token)
        ->getJson('/api/v1/test/communities/'.$community->id.'/plugins/manage')
        ->assertForbidden()
        ->assertJson([
            'code' => 'authorization.denied',
        ]);
});

it('blocks dev login outside local and testing environments', function (): void {
    seedAuthDemoData();

    $this->app->detectEnvironment(fn (): string => 'production');

    $this
        ->postJson('/api/v1/auth/dev-login', [
            'email' => 'admin@demo',
        ])
        ->assertForbidden()
        ->assertJson([
            'code' => 'auth.dev_login_disabled',
        ]);
});
