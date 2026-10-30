<?php

use App\Http\Controllers\Api\AsaasWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/webhooks/asaas', [AsaasWebhookController::class, 'handle'])->name('api.webhooks.asaas');
