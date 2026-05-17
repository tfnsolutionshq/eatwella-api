<?php

namespace App\Services;

use App\Models\Discount;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Str;

class NewUserDiscountService
{
    public function createForUser(User $user): array
    {
        $discountType = Setting::where('key', 'new_user_discount_type')->value('value') ?? 'none';
        $discountPct  = (float) Setting::where('key', 'new_user_discount_percentage')->value('value');

        $hasMenu         = in_array($discountType, ['menu', 'both']);
        $hasFreeDelivery = in_array($discountType, ['free_delivery', 'both']);

        if ($hasMenu && !$discountPct) {
            $hasMenu = false;
        }

        $menuDiscount = null;
        $freeDeliveryDiscount = null;

        if (!$hasMenu && !$hasFreeDelivery) {
            return [$menuDiscount, $freeDeliveryDiscount];
        }

        if ($hasMenu) {
            $menuDiscount = Discount::create([
                'name'          => 'Welcome Discount - ' . $user->name,
                'code'          => 'WELCOME-' . strtoupper(Str::random(6)),
                'discount_type' => 'menu',
                'type'          => 'percentage',
                'value'         => $discountPct,
                'start_date'    => now()->toDateString(),
                'end_date'      => null,
                'is_indefinite' => true,
                'is_active'     => true,
                'usage_limit'   => 1,
                'used_count'    => 0,
            ]);
            $menuDiscount->users()->sync([$user->id]);
        }

        if ($hasFreeDelivery) {
            $freeDeliveryDiscount = Discount::create([
                'name'          => 'Welcome Free Delivery - ' . $user->name,
                'code'          => 'WELCOMEFD-' . strtoupper(Str::random(5)),
                'discount_type' => 'free_delivery',
                'type'          => 'fixed',
                'value'         => 0,
                'start_date'    => now()->toDateString(),
                'end_date'      => null,
                'is_indefinite' => true,
                'is_active'     => true,
                'usage_limit'   => 1,
                'used_count'    => 0,
            ]);
            $freeDeliveryDiscount->users()->sync([$user->id]);
        }

        return [$menuDiscount, $freeDeliveryDiscount];
    }
}
