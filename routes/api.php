<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\PublicMenuController;
use App\Http\Controllers\Api\MasterMenuController;
use App\Http\Controllers\Api\BranchMenuController;
use App\Http\Controllers\Api\TableController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\KitchenController;
use App\Http\Controllers\Api\PrintJobController;
use App\Http\Controllers\Api\ReportController;

// Public Endpoints
Route::post('/login', [AuthController::class, 'login']);

Route::prefix('public')->group(function () {
    Route::get('/branches/{branch}/menu', [PublicMenuController::class, 'getBranchMenu']);
    Route::get('/branches/{branch}/tables', [PublicMenuController::class, 'getBranchTables']);
    Route::get('/tables/{token}', [PublicMenuController::class, 'verifyTableToken']);
    Route::get('/takeaway/{token}', [PublicMenuController::class, 'verifyTakeawayToken']);
    Route::post('/orders', [PublicMenuController::class, 'createOrder']);
    Route::post('/orders/{access_token}/payment', [PublicMenuController::class, 'submitPayment']);
    Route::get('/orders/{access_token}/status', [PublicMenuController::class, 'getOrderStatus']);
});

// Authenticated Endpoints (Sanctum + Branch Isolation Middleware)
Route::middleware(['auth:sanctum', \App\Http\Middleware\BranchScopeMiddleware::class])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    // User & Branch Management (Owner/CEO Only)
    Route::get('/users', [\App\Http\Controllers\Api\UserController::class, 'index']);
    Route::post('/users', [\App\Http\Controllers\Api\UserController::class, 'store']);
    Route::put('/users/{id}', [\App\Http\Controllers\Api\UserController::class, 'update']);
    Route::delete('/users/{id}', [\App\Http\Controllers\Api\UserController::class, 'destroy']);

    Route::get('/all-branches', [\App\Http\Controllers\Api\BranchController::class, 'index']);
    Route::post('/branches', [\App\Http\Controllers\Api\BranchController::class, 'store']);
    Route::put('/branches/{id}', [\App\Http\Controllers\Api\BranchController::class, 'update']);

    // Master Menu (Owner Only)
    Route::get('/categories', [MasterMenuController::class, 'getCategories']);
    Route::post('/categories', [MasterMenuController::class, 'storeCategory']);
    Route::get('/menus', [MasterMenuController::class, 'getMasterMenus']);
    Route::post('/menus', [MasterMenuController::class, 'storeMasterMenu']);
    Route::post('/menus/{id}', [MasterMenuController::class, 'updateMasterMenu']);
    Route::post('/variants', [MasterMenuController::class, 'storeVariant']);
    Route::get('/addons', [MasterMenuController::class, 'getAddons']);
    Route::post('/addons', [MasterMenuController::class, 'storeAddon']);

    // Branch Menu & Tables
    Route::get('/branches/{branch}/menus', [BranchMenuController::class, 'getBranchMenus']);
    Route::post('/branches/{branch}/menus/{menu}', [BranchMenuController::class, 'updateBranchMenu']);
    Route::put('/branches/{branch}/menus/{menu}', [BranchMenuController::class, 'updateBranchMenu']);
    Route::patch('/branch-menus/{id}/availability', [BranchMenuController::class, 'setAvailability']);
    Route::delete('/branch-menus/{id}', [BranchMenuController::class, 'destroy']);

    // Promos (Admin Cabang & Owner)
    Route::get('/promos', [\App\Http\Controllers\Api\PromoController::class, 'index']);
    Route::post('/promos', [\App\Http\Controllers\Api\PromoController::class, 'store']);
    Route::put('/promos/{id}', [\App\Http\Controllers\Api\PromoController::class, 'update']);
    Route::delete('/promos/{id}', [\App\Http\Controllers\Api\PromoController::class, 'destroy']);

    Route::get('/branches/{branch}/tables', [TableController::class, 'getBranchTables']);
    Route::post('/branches/{branch}/tables', [TableController::class, 'storeTable']);
    Route::post('/branches/{branch}/tables/{table}/regenerate-qr', [TableController::class, 'regenerateQrToken']);

    // Orders (Kasir / Admin / Owner)
    Route::get('/orders', [OrderController::class, 'getOrders']);
    Route::get('/orders/{id}', [OrderController::class, 'getOrderDetails']);
    Route::post('/orders/takeaway', [OrderController::class, 'createTakeawayOrder']);
    Route::post('/orders/{id}/confirm-paid', [OrderController::class, 'confirmPaid']);

    // Kitchen Dashboard (Dapur-only status mutation)
    Route::get('/kitchen/orders', [KitchenController::class, 'getKitchenOrders']);
    Route::patch('/kitchen/orders/{id}/status', [KitchenController::class, 'updateKitchenStatus']);

    // Printing Engine
    Route::get('/print-jobs', [PrintJobController::class, 'getPrintJobs']);
    Route::post('/print-jobs/{id}/retry', [PrintJobController::class, 'retryPrintJob']);
    Route::post('/orders/{id}/reprint-receipt', [PrintJobController::class, 'reprintReceipt']);
    Route::post('/orders/{id}/reprint-kitchen', [PrintJobController::class, 'reprintKitchen']);

    // Reports & Analytics
    Route::get('/reports/sales', [ReportController::class, 'getSalesReport']);
    Route::get('/reports/inventory', [ReportController::class, 'getInventoryReport']);
    Route::get('/reports/audit', [ReportController::class, 'getAuditReport']);
});
