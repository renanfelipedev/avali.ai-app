<?php

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\AsaasService;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PlanSeeder::class);
    config(['asaas.api_key' => 'test_api_key']);
    config(['asaas.webhook_token' => 'test_webhook_token']);
    config(['asaas.base_url' => 'https://sandbox.asaas.com/api/v3']);
});

test('asaas service creates or finds customer', function () {
    Http::fake([
        'https://sandbox.asaas.com/api/v3/customers*' => Http::response([
            'data' => [
                ['id' => 'cus_mock123', 'name' => 'Prof Teste', 'email' => 'prof@escola.com'],
            ],
        ], 200),
    ]);

    $user = User::factory()->create([
        'name' => 'Prof Teste',
        'email' => 'prof@escola.com',
        'cpf' => '12345678909',
    ]);

    $service = new AsaasService;
    $customerId = $service->findOrCreateCustomer($user);

    expect($customerId)->toBe('cus_mock123')
        ->and($user->fresh()->asaas_customer_id)->toBe('cus_mock123');
});

test('asaas service creates subscription and retrieves payments', function () {
    Http::fake([
        'https://sandbox.asaas.com/api/v3/customers*' => Http::response([
            'id' => 'cus_mock999',
            'name' => 'Prof Assinante',
            'email' => 'assinante@escola.com',
        ], 200),
        'https://sandbox.asaas.com/api/v3/subscriptions' => Http::response([
            'id' => 'sub_mock123',
            'customer' => 'cus_mock999',
            'value' => 39.9,
            'status' => 'ACTIVE',
        ], 200),
        'https://sandbox.asaas.com/api/v3/subscriptions/sub_mock123/payments' => Http::response([
            'data' => [
                [
                    'id' => 'pay_mock123',
                    'invoiceUrl' => 'https://sandbox.asaas.com/i/mock123',
                    'status' => 'PENDING',
                ],
            ],
        ], 200),
    ]);

    $user = User::factory()->create([
        'name' => 'Prof Assinante',
        'email' => 'assinante@escola.com',
        'cpf' => '12345678909',
    ]);

    $plan = Plan::where('slug', 'pro')->first();
    $service = new AsaasService;

    $subscription = $service->createSubscription($user, $plan);
    expect($subscription['id'])->toBe('sub_mock123');

    $payments = $service->getSubscriptionPayments('sub_mock123');
    expect($payments)->toHaveCount(1)
        ->and($payments[0]['invoiceUrl'])->toBe('https://sandbox.asaas.com/i/mock123');
});

test('asaas webhook rejects unauthorized token', function () {
    $response = $this->postJson('/api/webhooks/asaas', [
        'event' => 'PAYMENT_RECEIVED',
    ], [
        'asaas-access-token' => 'invalid_token',
    ]);

    $response->assertStatus(401);
});

test('asaas webhook activates subscription on PAYMENT_CONFIRMED', function () {
    $user = User::factory()->create();
    $plan = Plan::where('slug', 'pro')->first();

    $sub = Subscription::create([
        'user_id' => $user->id,
        'plan_id' => $plan->id,
        'status' => 'pending',
        'gateway' => 'asaas',
        'gateway_subscription_id' => 'sub_live_test_1',
    ]);

    $response = $this->postJson('/api/webhooks/asaas', [
        'event' => 'PAYMENT_CONFIRMED',
        'payment' => [
            'id' => 'pay_123',
            'subscription' => 'sub_live_test_1',
            'externalReference' => "plan_{$plan->id}_user_{$user->id}",
            'value' => 39.9,
        ],
    ], [
        'asaas-access-token' => 'test_webhook_token',
    ]);

    $response->assertOk()
        ->assertJson(['status' => 'success']);

    expect($sub->fresh()->status)->toBe('active')
        ->and($user->fresh()->currentPlan()->slug)->toBe('pro');
});

test('asaas webhook handles PAYMENT_OVERDUE and SUBSCRIPTION_CANCELED', function () {
    $user = User::factory()->create();
    $plan = Plan::where('slug', 'school')->first();

    $sub = Subscription::create([
        'user_id' => $user->id,
        'plan_id' => $plan->id,
        'status' => 'active',
        'gateway' => 'asaas',
        'gateway_subscription_id' => 'sub_cancel_test',
    ]);

    // Overdue
    $this->postJson('/api/webhooks/asaas', [
        'event' => 'PAYMENT_OVERDUE',
        'payment' => [
            'id' => 'pay_overdue_1',
            'subscription' => 'sub_cancel_test',
        ],
    ], [
        'asaas-access-token' => 'test_webhook_token',
    ])->assertOk();

    expect($sub->fresh()->status)->toBe('past_due');

    // Canceled
    $this->postJson('/api/webhooks/asaas', [
        'event' => 'SUBSCRIPTION_CANCELED',
        'subscription' => [
            'id' => 'sub_cancel_test',
        ],
    ], [
        'asaas-access-token' => 'test_webhook_token',
    ])->assertOk();

    expect($sub->fresh()->status)->toBe('canceled')
        ->and($sub->fresh()->canceled_at)->not->toBeNull();
});
