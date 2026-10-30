<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AsaasWebhookController extends Controller
{
    /**
     * Handle incoming webhooks from Asaas.
     */
    public function handle(Request $request): JsonResponse
    {
        $expectedToken = config('asaas.webhook_token');
        $receivedToken = $request->header('asaas-access-token');

        if (! empty($expectedToken) && $receivedToken !== $expectedToken) {
            Log::warning('Asaas webhook unauthorized attempt', [
                'ip' => $request->ip(),
                'received_token' => $receivedToken,
            ]);

            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $event = $request->input('event');
        $payment = $request->input('payment');

        Log::info("Asaas webhook event received: {$event}", [
            'event' => $event,
            'payment_id' => $payment['id'] ?? null,
            'subscription_id' => $payment['subscription'] ?? null,
        ]);

        return match ($event) {
            'PAYMENT_CONFIRMED', 'PAYMENT_RECEIVED' => $this->handlePaymentReceived($payment),
            'PAYMENT_OVERDUE' => $this->handlePaymentOverdue($payment),
            'SUBSCRIPTION_CANCELED', 'SUBSCRIPTION_DELETED' => $this->handleSubscriptionCanceled($request->all()),
            default => response()->json(['status' => 'ignored']),
        };
    }

    /**
     * Handle successful payment (activates or extends subscription).
     */
    protected function handlePaymentReceived(?array $payment): JsonResponse
    {
        if (! $payment) {
            return response()->json(['error' => 'Missing payment data'], 400);
        }

        $subscriptionId = $payment['subscription'] ?? null;
        $externalReference = $payment['externalReference'] ?? null;
        $customerId = $payment['customer'] ?? null;

        // Try finding existing subscription by gateway_subscription_id
        $subscription = null;
        if ($subscriptionId) {
            $subscription = Subscription::where('gateway_subscription_id', $subscriptionId)->first();
        }

        // Fallback: try finding via externalReference: plan_{planId}_user_{userId}
        if (! $subscription && $externalReference && preg_match('/plan_(\d+)_user_(\d+)/', $externalReference, $matches)) {
            $planId = (int) $matches[1];
            $userId = (int) $matches[2];

            $subscription = Subscription::firstOrNew(['user_id' => $userId]);
            $subscription->plan_id = $planId;
            $subscription->gateway = 'asaas';
            $subscription->gateway_subscription_id = $subscriptionId;
        }

        // Fallback by customerId
        if (! $subscription && $customerId) {
            $user = User::where('asaas_customer_id', $customerId)->first();
            if ($user) {
                $subscription = Subscription::firstOrNew(['user_id' => $user->id]);
                $subscription->gateway = 'asaas';
                $subscription->gateway_subscription_id = $subscriptionId;
            }
        }

        if (! $subscription) {
            Log::warning('Asaas webhook: Subscription or User not found for payment', ['payment' => $payment]);

            return response()->json(['error' => 'Subscription not found'], 404);
        }

        $subscription->status = 'active';
        $subscription->current_period_start = now();
        $subscription->current_period_end = now()->addMonth();
        $subscription->canceled_at = null;
        $subscription->save();

        Log::info('Subscription activated/renewed via Asaas webhook', [
            'user_id' => $subscription->user_id,
            'plan_id' => $subscription->plan_id,
            'subscription_id' => $subscriptionId,
        ]);

        return response()->json(['status' => 'success', 'message' => 'Subscription activated']);
    }

    /**
     * Handle overdue payment.
     */
    protected function handlePaymentOverdue(?array $payment): JsonResponse
    {
        if (! $payment) {
            return response()->json(['error' => 'Missing payment data'], 400);
        }

        $subscriptionId = $payment['subscription'] ?? null;

        if ($subscriptionId) {
            $subscription = Subscription::where('gateway_subscription_id', $subscriptionId)->first();
            if ($subscription) {
                $subscription->update(['status' => 'past_due']);
                Log::info('Subscription marked past_due via Asaas webhook', ['id' => $subscription->id]);
            }
        }

        return response()->json(['status' => 'success', 'message' => 'Subscription marked past due']);
    }

    /**
     * Handle subscription cancellation in Asaas.
     */
    protected function handleSubscriptionCanceled(array $payload): JsonResponse
    {
        $subscriptionId = $payload['subscription']['id'] ?? ($payload['id'] ?? null);

        if ($subscriptionId) {
            $subscription = Subscription::where('gateway_subscription_id', $subscriptionId)->first();
            if ($subscription) {
                $subscription->update([
                    'status' => 'canceled',
                    'canceled_at' => now(),
                ]);
                Log::info('Subscription canceled via Asaas webhook', ['id' => $subscription->id]);
            }
        }

        return response()->json(['status' => 'success', 'message' => 'Subscription canceled']);
    }
}
