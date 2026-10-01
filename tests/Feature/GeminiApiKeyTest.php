<?php

use App\Models\GeminiApiKey;
use App\Models\Role;
use App\Models\User;
use App\Services\AiService;
use App\Services\GeminiApiKeyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function createAdminUser(): User
{
    $adminRole = Role::firstOrCreate(['slug' => 'admin'], ['name' => 'Admin', 'description' => 'Admin']);
    $user = User::factory()->create();
    $user->roles()->attach($adminRole);

    return $user;
}

test('gemini api key model encrypts key in database and masks key for display', function () {
    $apiKey = GeminiApiKey::factory()->create([
        'key' => 'AIzaSySecretTestKey123456789012345',
    ]);

    // O valor no banco de dados deve estar criptografado (não em texto plano)
    $rawDbValue = DB::table('gemini_api_keys')->where('id', $apiKey->id)->value('key');
    expect($rawDbValue)->not->toBe('AIzaSySecretTestKey123456789012345');

    // Ao acessar pelo model, é descriptografado
    expect($apiKey->key)->toBe('AIzaSySecretTestKey123456789012345');

    // A chave mascarada deve esconder a maior parte dos caracteres
    expect($apiKey->maskedKey())->toBe('AIzaSy...2345');
});

test('only one key can be default at a time', function () {
    $key1 = GeminiApiKey::factory()->default()->create(['name' => 'Key 1']);
    expect($key1->is_default)->toBeTrue();

    $key2 = GeminiApiKey::factory()->default()->create(['name' => 'Key 2']);

    $key1->refresh();
    $key2->refresh();

    expect($key1->is_default)->toBeFalse();
    expect($key2->is_default)->toBeTrue();
});

test('service returns best active key and rotates on quota limits', function () {
    $service = app(GeminiApiKeyService::class);

    // Sem chaves cadastradas e sem .env
    config(['gemini.api_key' => null]);
    putenv('GEMINI_API_KEY=');

    expect($service->getActiveKey())->toBeNull();

    // Cria chave normal e chave padrão
    $key1 = GeminiApiKey::factory()->create(['name' => 'Secundária', 'priority' => 10]);
    $key2 = GeminiApiKey::factory()->default()->create(['name' => 'Principal', 'priority' => 1]);

    // Deve retornar a chave padrão
    expect($service->getActiveKey()->id)->toBe($key2->id);

    // Simula rotação de chave por erro de cota
    $rotated = $service->rotateKey($key2, new Exception('Resource exhausted: quota exceeded (429)'));

    expect($rotated)->not->toBeNull();
    expect($rotated->id)->toBe($key1->id);

    $key2->refresh();
    expect($key2->status)->toBe('rate_limited');
    expect($key2->rate_limited_until)->not->toBeNull();
});

test('service falls back to env if no database keys exist', function () {
    $service = app(GeminiApiKeyService::class);
    config(['gemini.api_key' => 'AIzaSyFallbackFromEnv12345']);

    expect($service->getActiveKey())->toBeNull();
    expect($service->hasValidKey())->toBeTrue();
    expect($service->getActiveKeyString())->toBe('AIzaSyFallbackFromEnv12345');
});

test('guest cannot access gemini keys page', function () {
    $this->get(route('gemini-keys.index'))
        ->assertRedirect(route('login'));
});

test('non-admin user cannot access gemini keys page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('gemini-keys.index'))
        ->assertForbidden();
});

test('admin can view gemini keys page', function () {
    $admin = createAdminUser();

    $key = GeminiApiKey::factory()->create(['name' => 'Chave de Producao']);

    $this->actingAs($admin)
        ->get(route('gemini-keys.index'))
        ->assertOk()
        ->assertSee('Gerenciamento de APIs Gemini')
        ->assertSee('Chave de Producao');
});

test('admin can create, edit, toggle and delete key via livewire', function () {
    $admin = createAdminUser();

    // Criar chave
    Livewire::actingAs($admin)
        ->test('pages::gemini-keys.index')
        ->call('createKey')
        ->set('name', 'Nova Chave de Teste')
        ->set('key', 'AIzaSyTestValidLengthKey1234567890')
        ->set('priority', 5)
        ->set('is_default', true)
        ->call('save')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('gemini_api_keys', [
        'name' => 'Nova Chave de Teste',
        'is_default' => true,
        'priority' => 5,
    ]);

    $created = GeminiApiKey::where('name', 'Nova Chave de Teste')->first();

    // Editar chave
    Livewire::actingAs($admin)
        ->test('pages::gemini-keys.index')
        ->call('editKey', $created->id)
        ->set('name', 'Nome Alterado')
        ->call('save')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('gemini_api_keys', [
        'id' => $created->id,
        'name' => 'Nome Alterado',
    ]);

    // Alternar ativação
    Livewire::actingAs($admin)
        ->test('pages::gemini-keys.index')
        ->call('toggleActive', $created->id);

    expect($created->fresh()->is_active)->toBeFalse();

    // Deletar chave
    Livewire::actingAs($admin)
        ->test('pages::gemini-keys.index')
        ->call('deleteKey', $created->id);

    $this->assertDatabaseMissing('gemini_api_keys', [
        'id' => $created->id,
    ]);
});

test('admin can set a key as default', function () {
    $admin = createAdminUser();

    $key1 = GeminiApiKey::factory()->default()->create(['name' => 'Key 1']);
    $key2 = GeminiApiKey::factory()->create(['name' => 'Key 2', 'is_default' => false]);

    Livewire::actingAs($admin)
        ->test('pages::gemini-keys.index')
        ->call('setDefault', $key2->id);

    expect($key1->fresh()->is_default)->toBeFalse();
    expect($key2->fresh()->is_default)->toBeTrue();
});

test('admin can import key from env', function () {
    $admin = createAdminUser();
    config(['gemini.api_key' => 'AIzaSyEnvImportedKey9876543210']);
    putenv('GEMINI_API_KEY=AIzaSyEnvImportedKey9876543210');

    Livewire::actingAs($admin)
        ->test('pages::gemini-keys.index')
        ->call('importFromEnv');

    $this->assertDatabaseHas('gemini_api_keys', [
        'name' => 'Chave Importada (.env)',
    ]);
});

test('ai service throws exception when no gemini key is configured anywhere', function () {
    config(['gemini.api_key' => null]);
    putenv('GEMINI_API_KEY=');

    $aiService = app(AiService::class);

    $this->expectException(Exception::class);
    $this->expectExceptionMessage('Nenhuma chave de API do Gemini configurada no sistema.');

    $aiService->generateContent(['ping']);
});

test('available scope excludes inactive, invalid, and rate limited keys', function () {
    GeminiApiKey::factory()->create(['name' => 'Active Key']);
    GeminiApiKey::factory()->inactive()->create(['name' => 'Inactive Key']);
    GeminiApiKey::factory()->invalid()->create(['name' => 'Invalid Key']);
    GeminiApiKey::factory()->rateLimited()->create(['name' => 'Rate Limited Key']);

    $available = GeminiApiKey::available()->get();

    expect($available)->toHaveCount(1);
    expect($available->first()->name)->toBe('Active Key');
});

test('admin can update key name without re-entering the secret key', function () {
    $admin = createAdminUser();

    $key = GeminiApiKey::factory()->create([
        'name' => 'Original Name',
        'key' => 'AIzaSySecretOriginalKey1234567890',
    ]);

    Livewire::actingAs($admin)
        ->test('pages::gemini-keys.index')
        ->call('editKey', $key->id)
        ->set('name', 'Updated Name')
        ->set('key', '') // vazio intencionalmente
        ->call('save')
        ->assertHasNoErrors();

    $key->refresh();
    expect($key->name)->toBe('Updated Name');
    expect($key->key)->toBe('AIzaSySecretOriginalKey1234567890');
});
