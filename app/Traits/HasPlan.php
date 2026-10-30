<?php

namespace App\Traits;

use App\Models\Plan;
use App\Models\PlanUsage;
use App\Models\Subscription;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

trait HasPlan
{
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function activeSubscription(): HasOne
    {
        return $this->hasOne(Subscription::class)
            ->whereIn('status', ['active', 'trialing'])
            ->where(function ($q) {
                $q->whereNull('current_period_end')
                    ->orWhere('current_period_end', '>', now());
            })
            ->latestOfMany();
    }

    public function currentPlan(): Plan
    {
        return $this->activeSubscription?->plan ?? Plan::free();
    }

    public function canGenerateExam(): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        $limit = (int) $this->currentPlan()->getFeature('monthly_exams_limit', 5);
        if ($limit === -1) {
            return true; // Ilimitado
        }

        return $this->getCurrentMonthExamsCount() < $limit;
    }

    public function canEvaluateSubmissions(int $count = 1): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        $limit = (int) $this->currentPlan()->getFeature('monthly_submissions_limit', 25);
        if ($limit === -1) {
            return true; // Ilimitado
        }

        return ($this->getCurrentMonthSubmissionsCount() + $count) <= $limit;
    }

    public function canUseFeature(string $featureKey): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        return $this->currentPlan()->allows($featureKey);
    }

    public function getQueuePriority(): string
    {
        return (string) $this->currentPlan()->getFeature('queue_priority', 'default');
    }

    public function getCurrentMonthExamsCount(): int
    {
        return PlanUsage::currentForUser($this)->exams_generated_count;
    }

    public function getCurrentMonthSubmissionsCount(): int
    {
        return PlanUsage::currentForUser($this)->submissions_evaluated_count;
    }

    public function recordExamUsage(int $count = 1): void
    {
        PlanUsage::currentForUser($this)->increment('exams_generated_count', $count);
    }

    public function recordSubmissionUsage(int $count = 1): void
    {
        PlanUsage::currentForUser($this)->increment('submissions_evaluated_count', $count);
    }
}
