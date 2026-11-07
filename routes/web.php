<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\ActivityController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\ContractController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LeadController;
use App\Http\Controllers\OpportunityController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\QuotationController;
use App\Http\Controllers\SalesOrderController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::middleware('guest')->group(function () {
    Route::get('login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('login', [AuthenticatedSessionController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('login.store');
});

Route::middleware(['auth'])->group(function () {
    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::get('dashboard', DashboardController::class)->name('dashboard');

    // Leads ---------------------------------------------------------------
    Route::resource('leads', LeadController::class)->except(['create', 'edit']);
    Route::post('leads/{lead}/convert', [LeadController::class, 'convert'])->name('leads.convert');

    // Accounts & contacts -------------------------------------------------
    Route::get('accounts', [AccountController::class, 'index'])
        ->middleware('permission:accounts.view')->name('accounts.index');
    Route::get('accounts/{account}', [AccountController::class, 'show'])
        ->middleware('permission:accounts.view')->name('accounts.show');

    Route::get('contacts', [ContactController::class, 'index'])
        ->middleware('permission:contacts.view')->name('contacts.index');

    // Opportunities / pipeline --------------------------------------------
    Route::get('opportunities', [OpportunityController::class, 'index'])->name('opportunities.index');
    Route::post('opportunities', [OpportunityController::class, 'store'])->name('opportunities.store');
    Route::get('opportunities/{opportunity}', [OpportunityController::class, 'show'])->name('opportunities.show');
    Route::patch('opportunities/{opportunity}/stage', [OpportunityController::class, 'updateStage'])
        ->name('opportunities.stage');

    // Quotations ----------------------------------------------------------
    Route::get('quotations', [QuotationController::class, 'index'])->name('quotations.index');
    Route::get('quotations/create', [QuotationController::class, 'create'])->name('quotations.create');
    Route::post('quotations', [QuotationController::class, 'store'])->name('quotations.store');
    Route::get('quotations/{quotation}', [QuotationController::class, 'show'])->name('quotations.show');
    Route::patch('quotations/{quotation}', [QuotationController::class, 'update'])->name('quotations.update');
    Route::post('quotations/{quotation}/revise', [QuotationController::class, 'revise'])->name('quotations.revise');
    Route::post('quotations/{quotation}/approve', [QuotationController::class, 'approve'])->name('quotations.approve');
    Route::post('quotations/{quotation}/reject', [QuotationController::class, 'reject'])->name('quotations.reject');
    Route::post('quotations/{quotation}/send', [QuotationController::class, 'send'])->name('quotations.send');

    // Contracts & orders --------------------------------------------------
    Route::get('contracts', [ContractController::class, 'index'])
        ->middleware('permission:contracts.view')->name('contracts.index');
    Route::get('sales-orders', [SalesOrderController::class, 'index'])
        ->middleware('permission:orders.view')->name('sales-orders.index');

    // Catalog -------------------------------------------------------------
    Route::get('products', [ProductController::class, 'index'])
        ->middleware('permission:products.view')->name('products.index');

    // Activities ----------------------------------------------------------
    Route::post('activities', [ActivityController::class, 'store'])->name('activities.store');
    Route::patch('activities/{activity}/complete', [ActivityController::class, 'complete'])
        ->name('activities.complete');
});
