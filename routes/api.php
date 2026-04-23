<?php

use App\Http\Controllers\AddressController;
use App\Http\Controllers\Admin\AnalyticsController;
use App\Http\Controllers\Admin\CampaignController;
use App\Http\Controllers\Admin\CareerOpeningController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\TaxController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CareersController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CustomerAuthController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DiningTableController;
use App\Http\Controllers\DiscountController;
use App\Http\Controllers\KitchenController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\MenuController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\RecommendationController;
use App\Http\Controllers\ReviewController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

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
Route::get('/takeaway-price', [CustomerController::class, 'takeawayPrice']);
Route::post('/checkout', [CustomerController::class, 'checkout']);
Route::get('/orders/track/{identifier}', [CustomerController::class, 'trackOrder']);
Route::get('/careers/openings', [CareersController::class, 'listOpenings']);
Route::get('/careers/openings/{id}', [CareersController::class, 'showOpening']);
Route::post('/careers/apply', [CareersController::class, 'store']);

// Locations / Zones
Route::get('/states', [LocationController::class, 'getStates']);
Route::get('/cities', [LocationController::class, 'getCities']);
Route::get('/zones', [LocationController::class, 'getZones']);

// Dining Tables & Packaging
Route::get('/tables', [DiningTableController::class, 'index']);
Route::get('/packagings', [\App\Http\Controllers\TakeawayPackagingController::class, 'index']);

// Cart Routes (Public/Guest + Authenticated) - supports both
Route::get('/cart', [CartController::class, 'index']);
Route::post('/cart', [CartController::class, 'store']);
Route::post('/cart/apply-discount', [CartController::class, 'applyDiscount']);
Route::delete('/cart/remove-discount', [CartController::class, 'removeDiscount']);
Route::put('/cart/{itemId}', [CartController::class, 'update']);
Route::delete('/cart/{itemId}', [CartController::class, 'destroy']);

// Recommendations
Route::post('/recommendations/cart', [RecommendationController::class, 'getCartRecommendations']);
Route::post('/recommendations/track', [RecommendationController::class, 'trackInteraction']);

// Discount Validation (Public)
Route::post('/discounts/validate', [DiscountController::class, 'validateCode']);

// Taxes
Route::get('/taxes', [TaxController::class, 'list']);

// Category
Route::get('/categories', [CategoryController::class, 'index']);

// Payment Routes (Public/Guest)
Route::post('/payment/initialize', [PaymentController::class, 'initializePayment']);
Route::get('/payment/verify', [PaymentController::class, 'verifyPayment']);
Route::get('/payment/status', [PaymentController::class, 'orderStatus']);
Route::get('/payment/callback', [PaymentController::class, 'paymentCallback']);
Route::post('/payment/webhook', [PaymentController::class, 'webhook']);

Route::get('/availability-hours', [SettingsController::class, 'getAvailabilityHours']);

// Public Campaign Routes
Route::get('/campaigns', [CampaignController::class, 'publicIndex']);
Route::get('/campaigns/{campaign}', [CampaignController::class, 'publicShow']);

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

        Route::post('/reviews', [ReviewController::class, 'store']);

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
    Route::patch('/admin/menus/{menu}/stock', [MenuController::class, 'updateStock']);
    Route::get('/admin/menus/{menu}/inventory-logs', [MenuController::class, 'inventoryLogs']);
    Route::apiResource('admin/discounts', DiscountController::class);
    Route::apiResource('admin/taxes', TaxController::class);
    Route::patch('/admin/taxes/{tax}/toggle', [TaxController::class, 'toggleStatus']);

    // Dining Tables & Packaging Management
    Route::apiResource('admin/tables', DiningTableController::class);
    Route::patch('/admin/tables/{table}/toggle', [DiningTableController::class, 'toggle']);

    Route::apiResource('admin/packagings', \App\Http\Controllers\TakeawayPackagingController::class)->parameters([
        'packagings' => 'takeawayPackaging'
    ]);
    Route::patch('/admin/packagings/{takeawayPackaging}/toggle', [\App\Http\Controllers\TakeawayPackagingController::class, 'toggle']);

    Route::get('/admin/payments', [PaymentController::class, 'index']);
    Route::get('/admin/careers/applications', [CareersController::class, 'index']);
    Route::get('/admin/careers/openings', [CareerOpeningController::class, 'index']);
    Route::post('/admin/careers/openings', [CareerOpeningController::class, 'store']);
    Route::put('/admin/careers/openings/{opening}', [CareerOpeningController::class, 'update']);
    Route::post('/admin/careers/openings/{opening}', [CareerOpeningController::class, 'update']); // Fallback for multipart/form-data
    Route::delete('/admin/careers/openings/{opening}', [CareerOpeningController::class, 'destroy']);
    Route::get('/admin/settings', [SettingsController::class, 'index']);
    Route::put('/admin/settings', [SettingsController::class, 'update']);
    Route::put('/admin/availability-hours', [SettingsController::class, 'setAvailabilityHours']);

    // Review Management
    Route::get('/admin/reviews', [ReviewController::class, 'index']);
    Route::patch('/admin/reviews/{review}/toggle', [ReviewController::class, 'togglePublish']);
    Route::delete('/admin/reviews/{review}', [ReviewController::class, 'destroy']);

    // Registered Users Management
    Route::get('/admin/users', [UserController::class, 'index']);
    Route::get('/admin/users/{user}', [UserController::class, 'show']);
    Route::post('/admin/attendants', [UserController::class, 'storeAttendant']);
    Route::post('/admin/staff', [UserController::class, 'storeStaff']);

    // Order Management
    Route::get('/admin/orders', [OrderController::class, 'index']);
    Route::get('/admin/orders/attendant', [OrderController::class, 'attendantCreatedOrders']);
    Route::get('/admin/orders/{order}', [OrderController::class, 'show']);
    Route::put('/admin/orders/{order}', [OrderController::class, 'update']);
    Route::get('/attendant/orders', [OrderController::class, 'attendantOrders']);
    Route::get('/attendant/orders/{order}', [OrderController::class, 'show']);

    // Supervisor endpoints
    Route::get('/supervisor/delivery-agents', [OrderController::class, 'getDeliveryAgents']);
    Route::get('/supervisor/orders', [OrderController::class, 'supervisorIndex']);
    Route::get('/supervisor/orders/{order}', [OrderController::class, 'supervisorShow']);
    Route::patch('/supervisor/orders/{order}/assign-delivery-agent', [OrderController::class, 'assignDeliveryAgent']);
    Route::post('/supervisor/orders/{order}/complete', [OrderController::class, 'supervisorCompleteDelivery']);
    Route::put('/supervisor/orders/{order}', [OrderController::class, 'update']);
    Route::get('/delivery-agent/orders', [OrderController::class, 'deliveryAgentOrders']);
    Route::get('/delivery-agent/orders/{order}', [OrderController::class, 'deliveryAgentShow']);
    Route::post('/delivery-agent/orders/{order}/complete', [OrderController::class, 'completeDelivery']);

    // Kitchen Routes
    Route::get('/kitchen/orders/confirmed', [KitchenController::class, 'getConfirmedOrders']);
    Route::get('/kitchen/orders/{order}', [OrderController::class, 'show']);
    Route::post('/kitchen/orders/preparing', [KitchenController::class, 'markAsPreparing']);
    Route::post('/kitchen/orders/ready', [KitchenController::class, 'markAsReady']);

    // Analytics
    Route::prefix('admin/analytics')->group(function () {
        Route::get('/summary', [AnalyticsController::class, 'summary']);
        Route::get('/top-menus', [AnalyticsController::class, 'topMenus']);
        Route::get('/daily-sales', [AnalyticsController::class, 'dailySales']);
    });

    // Campaign Management
    Route::apiResource('admin/campaigns', CampaignController::class);
    Route::post('/admin/campaigns/{campaign}', [CampaignController::class, 'update']); // multipart/form-data fallback
    Route::patch('/admin/campaigns/{campaign}/publish', [CampaignController::class, 'publish']);
    Route::patch('/admin/campaigns/{campaign}/draft', [CampaignController::class, 'draft']);

    // Locations / Zones Admin Management
    Route::apiResource('admin/zones', \App\Http\Controllers\Admin\ZoneController::class);
    Route::patch('/admin/zones/{zone}/toggle', [\App\Http\Controllers\Admin\ZoneController::class, 'toggle']);
});
