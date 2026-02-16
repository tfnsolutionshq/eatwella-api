<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\MenuController;
use App\Http\Controllers\DiscountController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\CustomerAuthController;
use App\Http\Controllers\AddressController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\Admin\AnalyticsController;
use App\Http\Controllers\Admin\UserController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Public Routes
Route::post('/login', [AuthController::class, 'login']);

// Customer Auth Routes
Route::post('/customer/register', [CustomerAuthController::class, 'register']);
Route::post('/customer/login', [CustomerAuthController::class, 'login']);

// Customer / Guest Routes
Route::get('/menus', [CustomerController::class, 'listMenus']);
Route::get('/menus/{menu}', [CustomerController::class, 'showMenu']);
Route::post('/checkout', [CustomerController::class, 'checkout']);
Route::get('/orders/track/{order_number}', [CustomerController::class, 'trackOrder']);

// Cart Routes (Public/Guest + Authenticated) - supports both
Route::get('/cart', [CartController::class, 'index']);
Route::post('/cart', [CartController::class, 'store']);
Route::put('/cart/{itemId}', [CartController::class, 'update']);
Route::delete('/cart/{itemId}', [CartController::class, 'destroy']);
Route::post('/cart/apply-discount', [CartController::class, 'applyDiscount']);
Route::delete('/cart/remove-discount', [CartController::class, 'removeDiscount']);

// Discount Validation (Public)
Route::post('/discounts/validate', [DiscountController::class, 'validateCode']);

// Category
Route::get('/categories', [CategoryController::class, 'index']);

// Payment Routes (Public/Guest)
Route::post('/payment/initialize', [PaymentController::class, 'initializePayment']);
Route::get('/payment/verify', [PaymentController::class, 'verifyPayment']);
Route::get('/payment/status', [PaymentController::class, 'orderStatus']);
Route::get('/payment/callback', [PaymentController::class, 'paymentCallback']);
Route::post('/payment/webhook', [PaymentController::class, 'webhook']);

// Admin Protected Routes
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    // Customer Dashboard Routes
    Route::prefix('customer')->group(function () {
        Route::get('/profile', [CustomerAuthController::class, 'profile']);
        Route::put('/profile', [CustomerAuthController::class, 'updateProfile']);
        Route::get('/overview', [CustomerAuthController::class, 'overview']);
        Route::get('/orders', [CustomerAuthController::class, 'recentOrders']);
        
        Route::get('/addresses', [AddressController::class, 'index']);
        Route::post('/addresses', [AddressController::class, 'store']);
        Route::put('/addresses/{address}', [AddressController::class, 'update']);
        Route::delete('/addresses/{address}', [AddressController::class, 'destroy']);
        
        Route::put('/change-password', [CustomerAuthController::class, 'changePassword']);
        Route::delete('/delete-account', [CustomerAuthController::class, 'deleteAccount']);
        
        Route::post('/logout', [CustomerAuthController::class, 'logout']);
    });

    // Admin Resources
    Route::apiResource('admin/categories', CategoryController::class);
    Route::apiResource('admin/menus', MenuController::class);
    Route::apiResource('admin/discounts', DiscountController::class);

    // Registered Users Management
    Route::get('/admin/users', [UserController::class, 'index']);
    Route::get('/admin/users/{user}', [UserController::class, 'show']);

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
