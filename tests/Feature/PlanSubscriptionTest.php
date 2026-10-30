<?php

use App\Models\Plan;
use App\Models\PlanUsage;
use App\Models\Subscription;
use App\Models\User;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PlanSeeder::class);
});

test('user defaults to free plan without active subscription', function () {
    $user = User::factory()->create();

    expect($user->currentPlan()->slug)->toBe('free')
        ->and($user->canUseFeature('allow_question_images'))->toBeFalse()
        ->and($user->canUseFeature('allow_google_classroom'))->toBeFalse()
        ->and($user->getQueuePriority())->toBe('default');
});

test('user respects monthly exam limits on free plan', function () {
    $user = User::factory()->create();

    // Free plan allows 5 exams
    expect($user->canGenerateExam())->toBeTrue();

    // Consume 5 exams
    $usage = PlanUsage::currentForUser($user);
    $usage->update(['exams_generated_count' => 5]);

    expect($user->canGenerateExam())->toBeFalse();

    // Record usage method increments correctly
    $user->recordExamUsage();
    expect($usage->fresh()->exams_generated_count)->toBe(6);
});

test('user respects submission limits on free plan', function () {
    $user = User::factory()->create();

    // Free plan allows 25 submissions
    expect($user->canEvaluateSubmissions(10))->toBeTrue();
    expect($user->canEvaluateSubmissions(26))->toBeFalse();

    $usage = PlanUsage::currentForUser($user);
    $usage->update(['submissions_evaluated_count' => 24]);

    expect($user->canEvaluateSubmissions(1))->toBeTrue();
    expect($user->canEvaluateSubmissions(2))->toBeFalse();
});

test('pro plan unlocks image features and higher queue priority', function () {
    $user = User::factory()->create();
    $proPlan = Plan::where('slug', 'pro')->first();

    Subscription::create([
        'user_id' => $user->id,
        'plan_id' => $proPlan->id,
        'status' => 'active',
        'starts_at' => now(),
    ]);

    expect($user->currentPlan()->slug)->toBe('pro')
        ->and($user->canUseFeature('allow_question_images'))->toBeTrue()
        ->and($user->getQueuePriority())->toBe('high');
});

test('school plan has vip priority and allows classroom', function () {
    $user = User::factory()->create();
    $schoolPlan = Plan::where('slug', 'school')->first();

    Subscription::create([
        'user_id' => $user->id,
        'plan_id' => $schoolPlan->id,
        'status' => 'active',
        'starts_at' => now(),
    ]);

    expect($user->currentPlan()->slug)->toBe('school')
        ->and($user->canUseFeature('allow_google_classroom'))->toBeTrue()
        ->and($user->getQueuePriority())->toBe('vip')
        ->and($user->canGenerateExam())->toBeTrue(); // unlimited
});

test('plans page displays plan options and opens checkout modal for paid plan', function () {
    $user = User::factory()->create();
    $proPlan = Plan::where('slug', 'pro')->first();

    Livewire::actingAs($user)
        ->test('pages::plans.index')
        ->assertOk()
        ->assertSee('Gratuito (Starter)')
        ->assertSee('Professor Pro')
        ->assertSee('Institucional (Escolas)')
        ->call('selectPlan', $proPlan->id)
        ->assertSet('showCheckoutModal', true)
        ->assertSet('selectedPlan.id', $proPlan->id);
});

test('plans page allows user to directly switch to free plan', function () {
    $user = User::factory()->create();
    $proPlan = Plan::where('slug', 'pro')->first();
    $freePlan = Plan::where('slug', 'free')->first();

    // Start on Pro
    Subscription::create([
        'user_id' => $user->id,
        'plan_id' => $proPlan->id,
        'status' => 'active',
    ]);

    Livewire::actingAs($user)
        ->test('pages::plans.index')
        ->call('selectPlan', $freePlan->id)
        ->assertSet('showCheckoutModal', false);

    expect($user->fresh()->currentPlan()->slug)->toBe('free');
});

test('exams create page warns when exam limit is exceeded', function () {
    $user = User::factory()->create();
    $usage = PlanUsage::currentForUser($user);
    $usage->update(['exams_generated_count' => 5]);

    Livewire::actingAs($user)
        ->test('pages::exams.create')
        ->assertOk()
        ->assertSee('Limite mensal do seu plano atingido');
});
