<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\MenuController;
use App\Http\Controllers\DiscountController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\Admin\AnalyticsController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Public Routes
Route::post('/login', [AuthController::class, 'login']);

// Customer / Guest Routes
Route::get('/menus', [CustomerController::class, 'listMenus']);
Route::get('/menus/{menu}', [CustomerController::class, 'showMenu']);
Route::post('/checkout', [CustomerController::class, 'checkout']);
Route::get('/orders/track/{order_number}', [CustomerController::class, 'trackOrder']);

// Cart Routes (Public/Guest)
Route::get('/cart', [CartController::class, 'index']);
Route::post('/cart', [CartController::class, 'store']);
Route::put('/cart/{itemId}', [CartController::class, 'update']);
Route::delete('/cart/{itemId}', [CartController::class, 'destroy']);

// Payment Routes (Public/Guest)
Route::post('/payment/initialize', [PaymentController::class, 'initializePayment']);
Route::get('/payment/verify', [PaymentController::class, 'verifyPayment']);
Route::get('/payment/status', [PaymentController::class, 'orderStatus']);
Route::post('/payment/webhook', [PaymentController::class, 'webhook']);

// Admin Protected Routes
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    // Admin Resources
    Route::apiResource('admin/categories', CategoryController::class);
    Route::apiResource('admin/menus', MenuController::class);
    Route::apiResource('admin/discounts', DiscountController::class);

    // Order Management
    Route::get('/admin/orders', [OrderController::class, 'index']);
    Route::get('/admin/orders/{order}', [OrderController::class, 'show']);
    Route::put('/admin/orders/{order}', [OrderController::class, 'update']);

    // Analytics
    Route::prefix('admin/analytics')->group(function () {
        Route::get('/summary', [AnalyticsController::class, 'summary']);
        Route::get('/top-menus', [AnalyticsController::class, 'topMenus']);
        Route::get('/daily-sales', [AnalyticsController::class, 'dailySales']);
    });
});
