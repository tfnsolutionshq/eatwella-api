<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

use App\Interfaces\PaymentGatewayInterface;
use App\Services\Payment\PaystackGateway;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(PaymentGatewayInterface::class, function ($app) {
            // Logic to switch gateways based on config if needed
            // For now, return PaystackGateway as requested
            return new PaystackGateway();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
