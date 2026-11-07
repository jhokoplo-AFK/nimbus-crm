<?php

use App\Http\Controllers\Api\AccountApiController;
use App\Http\Controllers\Api\ActivityApiController;
use App\Http\Controllers\Api\ApiTokenController;
use App\Http\Controllers\Api\BrandApiController;
use App\Http\Controllers\Api\ContactApiController;
use App\Http\Controllers\Api\ContractApiController;
use App\Http\Controllers\Api\LeadApiController;
use App\Http\Controllers\Api\OpportunityApiController;
use App\Http\Controllers\Api\PriceApprovalLayerApiController;
use App\Http\Controllers\Api\PricebookApiController;
use App\Http\Controllers\Api\PricebookEntryApiController;
use App\Http\Controllers\Api\ProductApiController;
use App\Http\Controllers\Api\QuotationApiController;
use App\Http\Controllers\Api\QuoteLineItemApiController;
use App\Http\Controllers\Api\SalesOrderApiController;
use App\Http\Controllers\Api\SalesOrderItemApiController;
use App\Http\Controllers\Api\SalesTargetApiController;
use App\Http\Controllers\Api\UserApiController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API routes (all prefixed with /api)
|--------------------------------------------------------------------------
|
| Authentication: POST /api/auth/token with email + password returns a
| Sanctum bearer token. Every other route requires `Authorization: Bearer`.
|
*/

Route::post('auth/token', [ApiTokenController::class, 'store'])->middleware('throttle:6,1');

Route::middleware(['auth:sanctum'])->group(function (): void {
    Route::get('auth/user', [ApiTokenController::class, 'show']);
    Route::post('auth/logout', [ApiTokenController::class, 'destroy']);

    // Leads ---------------------------------------------------------------
    Route::get('leads/statuses', [LeadApiController::class, 'statuses']);
    Route::apiResource('leads', LeadApiController::class);
    Route::post('leads/{lead}/convert', [LeadApiController::class, 'convert']);

    // Accounts & contacts -------------------------------------------------
    Route::apiResource('accounts', AccountApiController::class);
    Route::apiResource('contacts', ContactApiController::class);

    // Pipeline ------------------------------------------------------------
    Route::apiResource('opportunities', OpportunityApiController::class);
    Route::patch('opportunities/{opportunity}/stage', [OpportunityApiController::class, 'updateStage']);

    // Catalog -------------------------------------------------------------
    Route::apiResource('brands', BrandApiController::class);
    Route::apiResource('products', ProductApiController::class);
    Route::apiResource('pricebooks', PricebookApiController::class);
    Route::apiResource('pricebook-entries', PricebookEntryApiController::class)
        ->parameters(['pricebook-entries' => 'pricebookEntry']);

    // Price approval matrix -----------------------------------------------
    Route::get('price-approval-layers', [PriceApprovalLayerApiController::class, 'index']);
    Route::get('price-approval-layers/{priceApprovalLayer}', [PriceApprovalLayerApiController::class, 'show']);
    Route::patch('price-approval-layers/{priceApprovalLayer}', [PriceApprovalLayerApiController::class, 'update']);

    // Quotations ----------------------------------------------------------
    Route::apiResource('quotations', QuotationApiController::class);
    Route::post('quotations/{quotation}/approve', [QuotationApiController::class, 'approve']);
    Route::post('quotations/{quotation}/reject', [QuotationApiController::class, 'reject']);
    Route::post('quotations/{quotation}/revise', [QuotationApiController::class, 'revise']);
    Route::post('quotations/{quotation}/send', [QuotationApiController::class, 'send']);
    Route::apiResource('quote-line-items', QuoteLineItemApiController::class)
        ->parameters(['quote-line-items' => 'quoteLineItem']);

    // Contracts & orders --------------------------------------------------
    Route::apiResource('contracts', ContractApiController::class);
    Route::apiResource('sales-orders', SalesOrderApiController::class)
        ->parameters(['sales-orders' => 'salesOrder']);
    Route::patch('sales-orders/{salesOrder}/status', [SalesOrderApiController::class, 'updateStatus']);
    Route::apiResource('sales-order-items', SalesOrderItemApiController::class)
        ->parameters(['sales-order-items' => 'salesOrderItem']);

    // Activities ----------------------------------------------------------
    Route::apiResource('activities', ActivityApiController::class);
    Route::patch('activities/{activity}/complete', [ActivityApiController::class, 'complete']);

    // Targets & users -----------------------------------------------------
    Route::apiResource('sales-targets', SalesTargetApiController::class)
        ->parameters(['sales-targets' => 'salesTarget']);
    Route::get('users', [UserApiController::class, 'index']);
    Route::get('users/{user}', [UserApiController::class, 'show']);
});
