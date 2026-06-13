<?php

use App\Models\Community;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\AbstractProvider;
use Laravel\Socialite\Two\User as SocialiteUser;
use Symfony\Component\HttpFoundation\Cookie;

uses(RefreshDatabase::class);

function seedAuthDemoData(): void
{
    test()->seed(DatabaseSeeder::class);
}

function configureDiscordOAuth(): void
{
    config([
        'services.discord.client_id' => 'discord-client-id',
        'services.discord.client_secret' => 'discord-client-secret',
        'services.discord.redirect' => 'http://localhost/api/v1/auth/discord/callback',
    ]);
}

/**
 * @return array{state: string, cookie: string}
 */
function beginDiscordOAuth(): array
{
    configureDiscordOAuth();

    $response = test()->get('/api/v1/auth/discord/redirect');
    $response->assertRedirect();

    $location = $response->headers->get('Location');
    parse_str((string) parse_url((string) $location, PHP_URL_QUERY), $query);

    $cookie = collect($response->headers->getCookies())
        ->first(fn (Cookie $cookie): bool => $cookie->getName() === 'commandsphere_discord_oauth_state');

    expect($query['state'] ?? null)->toBeString()->not->toBe('');
    expect($cookie)->not->toBeNull();

    return [
        'state' => (string) $query['state'],
        'cookie' => $cookie->getValue(),
    ];
}

function fakeDiscordUser(): SocialiteUser
{
    return (new SocialiteUser)
        ->setRaw([
            'id' => 'discord-123',
            'username' => 'discord-user',
        ])
        ->map([
            'id' => 'discord-123',
            'nickname' => 'discord-user',
            'name' => 'Discord User',
            'email' => 'discord-user@example.test',
            'avatar' => 'https://cdn.discordapp.test/avatar.png',
        ]);
}

function mockDiscordProviderUser(SocialiteUser $user): void
{
    $provider = Mockery::mock(AbstractProvider::class);
    $provider->shouldReceive('stateless')->once()->andReturnSelf();
    $provider->shouldReceive('user')->once()->andReturn($user);

    Socialite::shouldReceive('buildProvider')
        ->once()
        ->andReturn($provider);
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

it('sets a signed oauth state cookie on Discord redirect', function (): void {
    $oauth = beginDiscordOAuth();

    expect($oauth['state'])->toHaveLength(40);
    expect($oauth['cookie'])->toContain('.');
});

it('rejects Discord callback without a matching oauth state', function (): void {
    configureDiscordOAuth();

    $this
        ->getJson('/api/v1/auth/discord/callback?state=missing')
        ->assertStatus(419)
        ->assertJson([
            'code' => 'auth.oauth_state_invalid',
        ]);
});

it('issues a sanctum token through Discord OAuth after validating state', function (): void {
    seedAuthDemoData();

    $oauth = beginDiscordOAuth();

    mockDiscordProviderUser(fakeDiscordUser());

    $response = $this
        ->withCredentials()
        ->withUnencryptedCookie('commandsphere_discord_oauth_state', $oauth['cookie'])
        ->getJson('/api/v1/auth/discord/callback?state='.$oauth['state']);

    $response
        ->assertOk()
        ->assertJsonStructure([
            'token',
            'token_type',
            'user',
        ])
        ->assertJsonPath('token_type', 'Bearer')
        ->assertJsonPath('user.discord_id', 'discord-123')
        ->assertCookieExpired('commandsphere_discord_oauth_state');
});
