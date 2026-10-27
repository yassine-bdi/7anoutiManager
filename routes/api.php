<?php

use App\Http\Controllers\Api\V1\Credits\CreditEntryController;
use App\Http\Controllers\Api\V1\Credits\CreditPaymentController;
use App\Http\Controllers\Api\V1\Credits\CustomerController;
use App\Http\Controllers\Api\V1\CreditsOverviewController;
use App\Http\Controllers\Api\V1\DashboardSummaryController;
use App\Http\Controllers\Api\V1\Finance\FinanceSaleController;
use App\Http\Controllers\Api\V1\Finance\FinancialEntryController;
use App\Http\Controllers\Api\V1\FinanceOverviewController;
use App\Http\Controllers\Api\V1\Stock\ProductController;
use App\Http\Controllers\Api\V1\Stock\StockMovementController;
use App\Http\Controllers\Api\V1\StockOverviewController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::get('/dashboard-summary', DashboardSummaryController::class)
        ->name('api.v1.dashboard-summary');

    Route::get('/stock/overview', StockOverviewController::class)
        ->name('api.v1.stock.overview');

    Route::get('/stock/products', [ProductController::class, 'index'])
        ->name('api.v1.stock.products.index');

    Route::post('/stock/products', [ProductController::class, 'store'])
        ->name('api.v1.stock.products.store');

    Route::patch('/stock/products/{product}', [ProductController::class, 'update'])
        ->name('api.v1.stock.products.update');

    Route::delete('/stock/products/{product}', [ProductController::class, 'destroy'])
        ->name('api.v1.stock.products.destroy');

    Route::post('/stock/movements', [StockMovementController::class, 'store'])
        ->name('api.v1.stock.movements.store');

    Route::get('/finance/overview', FinanceOverviewController::class)
        ->name('api.v1.finance.overview');

    Route::get('/finance/entries', [FinancialEntryController::class, 'index'])
        ->name('api.v1.finance.entries.index');

    Route::post('/finance/entries', [FinancialEntryController::class, 'store'])
        ->name('api.v1.finance.entries.store');

    Route::post('/finance/sales', [FinanceSaleController::class, 'store'])
        ->name('api.v1.finance.sales.store');

    Route::patch('/finance/entries/{financialEntry}', [FinancialEntryController::class, 'update'])
        ->name('api.v1.finance.entries.update');

    Route::delete('/finance/entries/{financialEntry}', [FinancialEntryController::class, 'destroy'])
        ->name('api.v1.finance.entries.destroy');

    Route::get('/credits/overview', CreditsOverviewController::class)
        ->name('api.v1.credits.overview');

    Route::get('/credits/customers', [CustomerController::class, 'index'])
        ->name('api.v1.credits.customers.index');

    Route::post('/credits/customers', [CustomerController::class, 'store'])
        ->name('api.v1.credits.customers.store');

    Route::get('/credits/entries', [CreditEntryController::class, 'index'])
        ->name('api.v1.credits.entries.index');

    Route::post('/credits/entries', [CreditEntryController::class, 'store'])
        ->name('api.v1.credits.entries.store');

    Route::patch('/credits/entries/{customerCredit}', [CreditEntryController::class, 'update'])
        ->name('api.v1.credits.entries.update');

    Route::delete('/credits/entries/{customerCredit}', [CreditEntryController::class, 'destroy'])
        ->name('api.v1.credits.entries.destroy');

    Route::post('/credits/entries/{customerCredit}/payments', [CreditPaymentController::class, 'store'])
        ->name('api.v1.credits.entries.payments.store');
});
