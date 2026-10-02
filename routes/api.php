<?php

/**
 * Shared / legacy API entry points.
 * Customer app routes  -> routes/customerapi.php
 * Agent app routes     -> routes/agentapi.php
 */

use Illuminate\Support\Facades\Route;

// Public payment webhooks (shared)
Route::post('/razor-pay/webhook', [App\Http\Controllers\Api\RazorpayPaymentControllerApi::class, 'razorpayWebhook']);
Route::post('/agent/payments/callback', [App\Http\Controllers\Agent\EmiPaymentControllerApi::class, 'handle'])
    ->name('agent.payment.callback');

Route::match(['get', 'post'], '/cashfree/return', [App\Http\Controllers\Api\PaymentControllerApi::class, 'cashfreeReturnHandler']);
