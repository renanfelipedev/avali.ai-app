<?php

namespace App\Services;

use App\Models\Plan;
use App\Models\User;
use Exception;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AsaasService
{
    protected string $apiKey;

    protected string $baseUrl;

    public function __construct()
    {
        $this->apiKey = config('asaas.api_key') ?? '';
        $this->baseUrl = rtrim(config('asaas.base_url') ?? 'https://sandbox.asaas.com/api/v3', '/');
    }

    /**
     * Get or create a customer in Asaas.
     */
    public function findOrCreateCustomer(User $user): string
    {
        if (! empty($user->asaas_customer_id)) {
            return $user->asaas_customer_id;
        }

        // Try searching for an existing customer with this email
        $searchResponse = $this->request('get', '/customers', ['email' => $user->email]);

        if ($searchResponse->successful() && ! empty($searchResponse->json('data.0.id'))) {
            $customerId = $searchResponse->json('data.0.id');
            $user->updateQuietly(['asaas_customer_id' => $customerId]);

            return $customerId;
        }

        // Create new customer
        $payload = [
            'name' => $user->name,
            'email' => $user->email,
        ];

        if (! empty($user->cpf)) {
            $payload['cpfCnpj'] = preg_replace('/\D/', '', $user->cpf);
        }

        $createResponse = $this->request('post', '/customers', $payload);

        if (! $createResponse->successful()) {
            Log::error('Asaas customer creation failed', [
                'user_id' => $user->id,
                'response' => $createResponse->json(),
            ]);
            throw new Exception('Erro ao criar cliente no gateway Asaas: '.($createResponse->json('errors.0.description') ?? 'Resposta inválida'));
        }

        $customerId = $createResponse->json('id');
        $user->updateQuietly(['asaas_customer_id' => $customerId]);

        return $customerId;
    }

    /**
     * Update customer in Asaas (e.g. CPF/CNPJ or phone).
     */
    public function updateCustomer(User $user, array $data): bool
    {
        $customerId = $this->findOrCreateCustomer($user);
        $response = $this->request('post', "/customers/{$customerId}", $data);

        if (! $response->successful()) {
            $errorDescription = $response->json('errors.0.description') ?? 'Falha ao atualizar dados do cliente no gateway Asaas';
            Log::error('Asaas customer update failed', [
                'user_id' => $user->id,
                'customer_id' => $customerId,
                'response' => $response->json(),
            ]);
            throw new Exception($errorDescription);
        }

        return true;
    }

    /**
     * Create a recurring subscription in Asaas.
     *
     * @param  string  $billingType  UNDEFINED | PIX | BOLETO | CREDIT_CARD
     * @return array Subscription payload from Asaas
     */
    public function createSubscription(User $user, Plan $plan, string $billingType = 'UNDEFINED'): array
    {
        $customerId = $this->findOrCreateCustomer($user);
        $value = round($plan->price_cents / 100, 2);

        $payload = [
            'customer' => $customerId,
            'billingType' => $billingType,
            'value' => $value,
            'nextDueDate' => now()->addDays(1)->format('Y-m-d'),
            'cycle' => 'MONTHLY',
            'description' => "Assinatura Plano {$plan->name} - Avali.ai",
            'externalReference' => "plan_{$plan->id}_user_{$user->id}",
        ];

        $response = $this->request('post', '/subscriptions', $payload);

        if (! $response->successful()) {
            Log::error('Asaas subscription creation failed', [
                'user_id' => $user->id,
                'plan_id' => $plan->id,
                'response' => $response->json(),
            ]);
            throw new Exception('Erro ao criar assinatura no gateway Asaas: '.($response->json('errors.0.description') ?? 'Erro inesperado'));
        }

        return $response->json();
    }

    /**
     * Get payments for a given subscription.
     */
    public function getSubscriptionPayments(string $subscriptionId): array
    {
        $response = $this->request('get', "/subscriptions/{$subscriptionId}/payments");

        return $response->json('data') ?? [];
    }

    /**
     * Cancel an active subscription in Asaas.
     */
    public function cancelSubscription(string $subscriptionId): bool
    {
        $response = $this->request('delete', "/subscriptions/{$subscriptionId}");

        return $response->successful();
    }

    /**
     * Retrieve payment details (including invoiceUrl, bankSlipUrl, etc.).
     */
    public function getPayment(string $paymentId): array
    {
        $response = $this->request('get', "/payments/{$paymentId}");

        return $response->json() ?? [];
    }

    /**
     * Get Pix QR Code and copy-paste code for a payment.
     */
    public function getPixQrCode(string $paymentId): array
    {
        $response = $this->request('get', "/payments/{$paymentId}/pixQrCode");

        return $response->json() ?? [];
    }

    /**
     * Helper to perform HTTP requests to Asaas API.
     */
    protected function request(string $method, string $endpoint, array $data = []): Response
    {
        $url = $this->baseUrl.'/'.ltrim($endpoint, '/');

        $headers = [
            'access_token' => $this->apiKey,
            'Content-Type' => 'application/json',
        ];

        return Http::withHeaders($headers)
            ->acceptJson()
            ->{$method}($url, $data);
    }
}
