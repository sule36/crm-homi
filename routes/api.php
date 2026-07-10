<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\LeadCaptureController;
use App\Http\Controllers\Api\WhatsAppWebhookController;
use App\Http\Controllers\Api\GoogleAdsWebhookController;
use App\Http\Controllers\Api\MetaLeadAdsWebhookController;
use App\Http\Controllers\Api\MetaMessagingWebhookController;

// Public Webhooks
Route::get('/webhooks/whatsapp', [WhatsAppWebhookController::class, 'verify']);
Route::post('/webhooks/whatsapp', [WhatsAppWebhookController::class, 'handle']);

Route::get('/webhooks/meta-messaging', [MetaMessagingWebhookController::class, 'verify']);
Route::post('/webhooks/meta-messaging', [MetaMessagingWebhookController::class, 'handle']);

Route::post('/webhooks/google-ads', [GoogleAdsWebhookController::class, 'handle']);
Route::get('/webhooks/meta-leads', [MetaLeadAdsWebhookController::class, 'verify']);
Route::post('/webhooks/meta-leads', [MetaLeadAdsWebhookController::class, 'handle']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/leads', [LeadCaptureController::class, 'store']);
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
});
