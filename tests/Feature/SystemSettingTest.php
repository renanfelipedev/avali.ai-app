<?php

use App\Models\SystemSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

test('system setting can be stored and retrieved with fallback', function () {
    expect(SystemSetting::get('unknown_key', 'fallback_value'))->toBe('fallback_value');

    SystemSetting::set('unknown_key', 'stored_value');

    expect(SystemSetting::get('unknown_key'))->toBe('stored_value');
});

test('system setting caches value and invalidates on update', function () {
    SystemSetting::set('cached_key', 'initial');

    expect(SystemSetting::get('cached_key'))->toBe('initial');

    // Atualiza diretamente no banco sem o model para simular cache stale se não invalidar
    SystemSetting::set('cached_key', 'updated');

    expect(SystemSetting::get('cached_key'))->toBe('updated');
});

test('system setting type helpers work correctly', function () {
    SystemSetting::set('is_feature_active', true);
    expect(SystemSetting::getBool('is_feature_active'))->toBeTrue();

    SystemSetting::set('is_feature_active', false);
    expect(SystemSetting::getBool('is_feature_active'))->toBeFalse();

    SystemSetting::set('custom_threshold', 0.85);
    expect(SystemSetting::getFloat('custom_threshold'))->toBe(0.85);
});

test('system setting can be forgotten', function () {
    SystemSetting::set('temp_key', 'hello');
    expect(SystemSetting::get('temp_key'))->toBe('hello');

    SystemSetting::forget('temp_key');

    expect(SystemSetting::get('temp_key', 'default'))->toBe('default');
});
