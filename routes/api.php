<?php

use App\Http\Controllers\DeliveryAgentController;
use App\Http\Controllers\AddressController;
use App\Http\Controllers\Admin\DeleteController;
use App\Http\Controllers\Admin\AnalyticsController;
use App\Http\Controllers\Admin\CampaignController;
use App\Http\Controllers\Admin\CareerOpeningController;
use App\Http\Controllers\Admin\OutletMenuStockController;
use App\Http\Controllers\Admin\OutletController;
use App\Http\Controllers\Admin\PaymentAccountController;
use App\Http\Controllers\Admin\TaxController;
use App\Http\Controllers\Supervisor\AvailabilityController;
use App\Http\Controllers\Supervisor\StockController as SupervisorStockController;
use App\Http\Controllers\Admin\SettingsController;
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
use App\Http\Controllers\OutletController as PublicOutletController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\RecommendationController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\RiderApplicationController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

use App\Http\Controllers\ReviewController;
use App\Http\Controllers\PasswordResetController;

Route::post('/rider/apply', [RiderApplicationController::class, 'apply']);
Route::post('/rider/activate', [RiderApplicationController::class, 'activate']);

// Public Routes
Route::post('/login', [AuthController::class, 'login']);

// Customer Auth Routes
Route::post('/customer/register', [CustomerAuthController::class, 'register']);
Route::post('/customer/login', [CustomerAuthController::class, 'login']);
Route::delete('/customer/delete-account', [CustomerAuthController::class, 'deleteAccount']);

// Password Reset (OTP) — all users
Route::post('/forgot-password', [PasswordResetController::class, 'sendOtp']);
Route::post('/verify-otp', [PasswordResetController::class, 'verifyOtp']);
Route::post('/reset-password', [PasswordResetController::class, 'resetPassword']);

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
Route::delete('/cart', [CartController::class, 'clear']);
Route::put('/cart/{itemId}', [CartController::class, 'update']);
Route::delete('/cart/{itemId}', [CartController::class, 'destroy']);

// Recommendations
Route::post('/recommendations/cart', [RecommendationController::class, 'getCartRecommendations']);
Route::post('/recommendations/track', [RecommendationController::class, 'trackInteraction']);

// Discount Validation (Public)
Route::post('/discounts/validate', [DiscountController::class, 'validateCode']);

// Taxes
Route::get('/taxes', [TaxController::class, 'list']);
Route::get('/tax-mode', [SettingsController::class, 'getTaxMode']);

// Category
Route::get('/categories', [CategoryController::class, 'index']);

// Payment Routes (Public/Guest)
Route::post('/payment/initialize', [PaymentController::class, 'initializePayment']);
Route::get('/payment/verify', [PaymentController::class, 'verifyPayment']);
Route::get('/payment/status', [PaymentController::class, 'orderStatus']);
Route::get('/payment/callback', [PaymentController::class, 'paymentCallback']);
Route::post('/payment/webhook', [PaymentController::class, 'webhook']);

Route::get('/availability-hours', [SettingsController::class, 'getAvailabilityHours']);

// Public Outlets
Route::get('/outlets', [PublicOutletController::class, 'index']);
Route::get('/outlets/{outlet}', [PublicOutletController::class, 'show']);
Route::post('/outlets/resolve', [\App\Http\Controllers\OutletResolverController::class, 'resolve']);
Route::post('/outlets/nearest', [\App\Http\Controllers\OutletResolverController::class, 'nearest']);

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
        Route::get('/orders/active', [CustomerAuthController::class, 'activeOrders']);

        Route::post('/reviews', [ReviewController::class, 'store']);

        Route::get('/addresses', [AddressController::class, 'index']);
        Route::post('/addresses', [AddressController::class, 'store']);
        Route::put('/addresses/{address}', [AddressController::class, 'update']);
        Route::patch('/addresses/{address}/default', [AddressController::class, 'setDefault']);
        Route::delete('/addresses/{address}', [AddressController::class, 'destroy']);

        Route::put('/change-password', [CustomerAuthController::class, 'changePassword']);

        Route::get('/loyalty', [CustomerAuthController::class, 'loyaltyInfo']);
        Route::post('/logout', [CustomerAuthController::class, 'logout']);
    });

    // Rider Applications
    Route::get('/admin/rider-applications', [RiderApplicationController::class, 'index']);
    Route::post('/admin/rider-applications/{application}/approve', [RiderApplicationController::class, 'approve']);
    Route::post('/admin/rider-applications/{application}/reject', [RiderApplicationController::class, 'reject']);

    // Admin Resources
    Route::apiResource('admin/categories', CategoryController::class);
    Route::apiResource('admin/menus', MenuController::class);
    Route::post('/admin/menus/{menu}', [MenuController::class, 'update']); // multipart/form-data fallback
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
    Route::get('/admin/tax-mode', [SettingsController::class, 'getTaxMode']);
    Route::put('/admin/tax-mode', [SettingsController::class, 'setTaxMode']);
    Route::put('/admin/availability-hours', [SettingsController::class, 'setAvailabilityHours']);
    Route::get('/admin/loyalty', [SettingsController::class, 'getLoyaltySettings']);
    Route::put('/admin/loyalty', [SettingsController::class, 'updateLoyaltySettings']);

    // Payment Accounts
    Route::get('/admin/payment-accounts', [PaymentAccountController::class, 'index']);
    Route::get('/staff/payment-accounts', [PaymentAccountController::class, 'staffIndex']);
    Route::post('/admin/payment-accounts', [PaymentAccountController::class, 'store']);
    Route::put('/admin/payment-accounts/{paymentAccount}', [PaymentAccountController::class, 'update']);
    Route::delete('/admin/payment-accounts/{paymentAccount}', [PaymentAccountController::class, 'destroy']);
    Route::patch('/admin/payment-accounts/{paymentAccount}/toggle', [PaymentAccountController::class, 'toggle']);

    // Review Management
    Route::get('/admin/reviews', [ReviewController::class, 'index']);
    Route::patch('/admin/reviews/{review}/toggle', [ReviewController::class, 'togglePublish']);
    Route::delete('/admin/reviews/{review}', [ReviewController::class, 'destroy']);

    // Registered Users Management
    Route::get('/admin/users', [UserController::class, 'index']);
    Route::get('/admin/users/{user}', [UserController::class, 'show']);
    Route::put('/admin/users/{user}', [UserController::class, 'updateUserProfile']);
    Route::patch('/admin/users/{user}/suspend', [UserController::class, 'suspend']);
    Route::patch('/admin/users/{user}/unsuspend', [UserController::class, 'unsuspend']);
    Route::post('/admin/attendants', [UserController::class, 'storeAttendant']);
    Route::post('/admin/staff', [UserController::class, 'storeStaff']);
    Route::put('/admin/profile', [UserController::class, 'updateProfile']);

    // Order Management
    Route::post('/admin/orders/{order}/send-to-kitchen', [OrderController::class, 'sendToKitchen']);
    Route::patch('/admin/orders/{order}/settle', [OrderController::class, 'settlePayment']);
    Route::get('/admin/orders', [OrderController::class, 'index']);
    Route::get('/admin/orders/attendant', [OrderController::class, 'attendantCreatedOrders']);
    Route::get('/admin/orders/{order}', [OrderController::class, 'show']);
    Route::put('/admin/orders/{order}', [OrderController::class, 'update']);
    Route::post('/admin/orders/{order}/complete-delivery', [OrderController::class, 'adminCompleteDelivery']);
    Route::get('/attendant/orders', [OrderController::class, 'attendantOrders']);
    Route::get('/attendant/orders/{order}', [OrderController::class, 'show']);

    // Supervisor — Outlet Dashboard & Availability Hours
    Route::get('/supervisor/outlet', [AvailabilityController::class, 'outlet']);
    Route::put('/supervisor/availability-hours', [AvailabilityController::class, 'update']);

    // Supervisor endpoints
    Route::get('/supervisor/delivery-agents', [OrderController::class, 'getDeliveryAgents']);
    Route::get('/supervisor/orders', [OrderController::class, 'supervisorIndex']);
    Route::get('/supervisor/orders/{order}', [OrderController::class, 'supervisorShow']);
    Route::patch('/supervisor/orders/{order}/assign-delivery-agent', [OrderController::class, 'assignDeliveryAgent']);
    Route::post('/supervisor/orders/{order}/complete', [OrderController::class, 'supervisorCompleteDelivery']);
    Route::put('/supervisor/orders/{order}', [OrderController::class, 'update']);
    Route::get('/delivery-agent/overview', [DeliveryAgentController::class, 'overview']);
    Route::get('/delivery-agent/orders', [DeliveryAgentController::class, 'getOrders']);
    Route::post('/delivery-agent/orders/{order}/accept', [DeliveryAgentController::class, 'acceptOrder']);
    Route::post('/delivery-agent/orders/{order}/ignore', [DeliveryAgentController::class, 'ignoreOrder']);
    Route::get('/delivery-agent/orders/{order}', [OrderController::class, 'deliveryAgentShow']);
    Route::post('/delivery-agent/orders/{order}/complete', [OrderController::class, 'completeDelivery']);

    // Delivery Agent Bank Details
    Route::get('/delivery-agent/bank-details', [DeliveryAgentController::class, 'getBankDetails']);
    Route::post('/delivery-agent/bank-details', [DeliveryAgentController::class, 'storeBankDetail']);
    Route::put('/delivery-agent/bank-details/{bankDetail}', [DeliveryAgentController::class, 'updateBankDetail']);
    Route::delete('/delivery-agent/bank-details/{bankDetail}', [DeliveryAgentController::class, 'deleteBankDetail']);

    // Admin: Orders delivered by a rider
    Route::get('/admin/riders/{user}/orders', [DeliveryAgentController::class, 'riderOrders']);

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

    // Bulk Delete
    Route::prefix('admin')->group(function () {
        Route::delete('/users',                [DeleteController::class, 'users']);
        Route::delete('/orders',               [DeleteController::class, 'orders']);
        Route::delete('/carts',                [DeleteController::class, 'carts']);
        Route::delete('/reviews',              [DeleteController::class, 'reviews']);
        Route::delete('/menus',                [DeleteController::class, 'menus']);
        Route::delete('/categories',           [DeleteController::class, 'categories']);
        Route::delete('/discounts',            [DeleteController::class, 'discounts']);
        Route::delete('/taxes',                [DeleteController::class, 'taxes']);
        Route::delete('/zones',                [DeleteController::class, 'zones']);
        Route::delete('/tables',               [DeleteController::class, 'tables']);
        Route::delete('/packagings',           [DeleteController::class, 'packagings']);
        Route::delete('/campaigns',            [DeleteController::class, 'campaigns']);
        Route::delete('/careers/applications', [DeleteController::class, 'careerApplications']);
        Route::delete('/careers/openings',     [DeleteController::class, 'careerOpenings']);
        Route::delete('/inventory-logs',        [DeleteController::class, 'inventoryLogs']);
        Route::delete('/settings',              [DeleteController::class, 'settings']);
    });

    // Campaign Management
    Route::apiResource('admin/campaigns', CampaignController::class);
    Route::post('/admin/campaigns/{campaign}', [CampaignController::class, 'update']); // multipart/form-data fallback
    Route::patch('/admin/campaigns/{campaign}/publish', [CampaignController::class, 'publish']);
    Route::patch('/admin/campaigns/{campaign}/draft', [CampaignController::class, 'draft']);

    // Locations / Zones Admin Management
    Route::apiResource('admin/zones', \App\Http\Controllers\Admin\ZoneController::class);
    Route::patch('/admin/zones/{zone}/toggle', [\App\Http\Controllers\Admin\ZoneController::class, 'toggle']);

    // States & Cities Admin Management
    Route::post('/admin/states', [LocationController::class, 'storeState']);
    Route::put('/admin/states/{state}', [LocationController::class, 'updateState']);
    Route::delete('/admin/states/{state}', [LocationController::class, 'destroyState']);
    Route::post('/admin/cities', [LocationController::class, 'storeCity']);
    Route::put('/admin/cities/{city}', [LocationController::class, 'updateCity']);
    Route::delete('/admin/cities/{city}', [LocationController::class, 'destroyCity']);

    // Delivery Agent City Management
    Route::get('/admin/users/{user}/cities', [UserController::class, 'listCities']);
    Route::post('/admin/users/{user}/cities', [UserController::class, 'assignCities']);
    Route::delete('/admin/users/{user}/cities/{city}', [UserController::class, 'removeCity']);

    // Outlet Management
    Route::get('/admin/outlets', [OutletController::class, 'index']);
    Route::post('/admin/outlets', [OutletController::class, 'store']);
    Route::get('/admin/outlets/{outlet}', [OutletController::class, 'show']);
    Route::put('/admin/outlets/{outlet}', [OutletController::class, 'update']);
    Route::patch('/admin/outlets/{outlet}/toggle', [OutletController::class, 'toggle']);
    Route::post('/admin/outlets/{outlet}/assign-supervisor', [OutletController::class, 'assignSupervisor']);
    Route::post('/admin/outlets/{outlet}/assign-delivery-agent', [OutletController::class, 'assignDeliveryAgent']);
    Route::delete('/admin/outlets/{outlet}/remove-user/{user}', [OutletController::class, 'removeUser']);

    // Outlet Stock Management (Admin)
    Route::get('/admin/outlets/{outlet}/stock', [OutletMenuStockController::class, 'index']);
    Route::patch('/admin/outlets/{outlet}/menus/{menu}/stock', [OutletMenuStockController::class, 'update']);
    Route::get('/admin/outlets/{outlet}/users', [OutletController::class, 'users']);

    // Outlet Stock Management (Supervisor & Store Keeper)
    Route::patch('/supervisor/menus/{menu}/stock', [SupervisorStockController::class, 'update']);
    Route::patch('/store-keeper/menus/{menu}/stock', [SupervisorStockController::class, 'update']);

    // Notification Preferences (all authenticated users)
    Route::get('/notification-preferences', [NotificationController::class, 'getPreferences']);
    Route::patch('/notification-preferences', [NotificationController::class, 'updatePreferences']);

    // Notifications
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);
    Route::patch('/notifications/push-settings', [NotificationController::class, 'pushSettings']);
    Route::patch('/notifications/read-all', [NotificationController::class, 'markAllRead']);
    Route::patch('/notifications/{notification}/read', [NotificationController::class, 'markRead']);
    Route::delete('/notifications', [NotificationController::class, 'bulkDestroy']);
    Route::delete('/notifications/{notification}', [NotificationController::class, 'destroy']);

    // FCM Tokens
    Route::post('/fcm-tokens', [NotificationController::class, 'registerToken']);
    Route::delete('/fcm-tokens', [NotificationController::class, 'removeToken']);
});
