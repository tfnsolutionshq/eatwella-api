<?php

namespace App\Console\Commands;

use App\Mail\BirthdayEmail;
use App\Models\Discount;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class SendBirthdayEmails extends Command
{
    protected $signature = 'birthday:send';
    protected $description = 'Send birthday discount emails to users whose birthday is today';

    public function handle(): void
    {
        $discountType    = Setting::where('key', 'birthday_discount_type')->value('value') ?? 'menu';
        $discountPct     = (float) Setting::where('key', 'birthday_discount_percentage')->value('value');

        $hasMenu         = in_array($discountType, ['menu', 'both']);
        $hasFreeDelivery = in_array($discountType, ['free_delivery', 'both']);

        if ($hasMenu && !$discountPct) {
            $this->warn('Birthday discount percentage not set. Menu discount will be skipped.');
            $hasMenu = false;
        }

        if (!$hasMenu && !$hasFreeDelivery) {
            $this->info('No birthday discount configured. Skipping.');
            return;
        }

        $today = now();

        $users = User::where('role', 'customer')
            ->where('birth_month', $today->month)
            ->whereNotNull('birthday')
            ->whereRaw('DAY(birthday) = ?', [$today->day])
            ->whereNotNull('email')
            ->get();

        if ($users->isEmpty()) {
            $this->info('No birthdays today.');
            return;
        }

        foreach ($users as $user) {
            $menuDiscount = null;
            $freeDeliveryDiscount = null;

            if ($hasMenu) {
                $menuDiscount = Discount::create([
                    'name'          => 'Birthday Discount - ' . $user->name,
                    'code'          => 'BDAY-' . strtoupper(Str::random(6)),
                    'discount_type' => 'menu',
                    'type'          => 'percentage',
                    'value'         => $discountPct,
                    'start_date'    => $today->toDateString(),
                    'end_date'      => $today->toDateString(),
                    'is_indefinite' => false,
                    'is_active'     => true,
                    'usage_limit'   => 1,
                    'used_count'    => 0,
                ]);
                $menuDiscount->users()->sync([$user->id]);
            }

            if ($hasFreeDelivery) {
                $freeDeliveryDiscount = Discount::create([
                    'name'          => 'Birthday Free Delivery - ' . $user->name,
                    'code'          => 'BDAYFD-' . strtoupper(Str::random(6)),
                    'discount_type' => 'free_delivery',
                    'type'          => 'fixed',
                    'value'         => 0,
                    'start_date'    => $today->toDateString(),
                    'end_date'      => $today->toDateString(),
                    'is_indefinite' => false,
                    'is_active'     => true,
                    'usage_limit'   => null,
                    'used_count'    => 0,
                ]);
                $freeDeliveryDiscount->users()->sync([$user->id]);
            }

            Mail::to($user->email)->queue(new BirthdayEmail($user, $menuDiscount, $freeDeliveryDiscount));

            $this->info("Birthday email queued for {$user->email}");
        }
    }
}
