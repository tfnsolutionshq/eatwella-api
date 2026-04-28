<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\CareerApplication;
use App\Models\CareerOpening;
use App\Models\Cart;
use App\Models\Category;
use App\Models\Discount;
use App\Models\DiningTable;
use App\Models\Menu;
use App\Models\Order;
use App\Models\Review;
use App\Models\TakeawayPackaging;
use App\Models\Tax;
use App\Models\User;
use App\Models\InventoryLog;
use App\Models\Setting;
use App\Models\Zone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class DeleteController extends Controller
{
    private function adminOnly(Request $request)
    {
        return $this->requireRole($request, ['admin']);
    }

    public function users(Request $request)
    {
        if ($response = $this->adminOnly($request)) return $response;

        $request->validate([
            'ids'     => 'nullable|array',
            'ids.*'   => 'string',
            'roles'   => 'nullable|array',
            'roles.*' => 'in:customer,attendant,supervisor,delivery_agent,kitchen',
        ]);

        if (empty($request->ids) && empty($request->roles)) {
            return response()->json(['status' => false, 'message' => 'Provide ids or roles.'], 422);
        }

        $query = User::query();

        if (! empty($request->ids)) {
            $query->whereIn('id', $request->ids);
        } elseif (! empty($request->roles)) {
            $query->whereIn('role', $request->roles);
        }

        $users = $query->get();

        if ($users->isEmpty()) {
            return response()->json(['status' => false, 'message' => 'No users found.'], 404);
        }

        DB::transaction(function () use ($users) {
            foreach ($users as $user) {
                $user->tokens()->delete();

                if ($user->role === 'customer') {
                    $orderIds = $user->orders()->pluck('id');

                    if ($orderIds->isNotEmpty()) {
                        \App\Models\Invoice::whereIn('order_id', $orderIds)->delete();
                        \App\Models\OrderItem::whereIn('order_id', $orderIds)->delete();
                        \App\Models\Review::whereIn('order_id', $orderIds)->delete();
                        Order::whereIn('id', $orderIds)->delete();
                    }

                    $user->carts()->each(fn($cart) => $cart->items()->delete());
                    $user->carts()->delete();
                    $user->addresses()->delete();
                } else {
                    Order::where('attendant_id', $user->id)->update(['attendant_id' => null]);
                    Order::where('delivery_agent_id', $user->id)->update(['delivery_agent_id' => null]);
                    Order::where('assigned_by_supervisor_id', $user->id)->update(['assigned_by_supervisor_id' => null]);
                    Order::where('sent_to_kitchen_by_id', $user->id)->update(['sent_to_kitchen_by_id' => null]);
                    Order::where('completed_by_id', $user->id)->update(['completed_by_id' => null]);
                }

                $user->delete();
            }
        });

        return response()->json(['status' => true, 'message' => "{$users->count()} user(s) deleted successfully."]);
    }

    public function orders(Request $request)
    {
        if ($response = $this->adminOnly($request)) return $response;

        $request->validate([
            'ids'   => 'nullable|array',
            'ids.*' => 'string',
            'all'   => 'nullable|boolean',
        ]);

        if (empty($request->ids) && ! $request->boolean('all')) {
            return response()->json(['status' => false, 'message' => 'Provide ids or set all to true.'], 422);
        }

        DB::transaction(function () use ($request) {
            $ids = $request->boolean('all')
                ? Order::pluck('id')
                : Order::whereIn('id', $request->ids)->pluck('id');

            if ($ids->isEmpty()) return;

            \App\Models\Invoice::whereIn('order_id', $ids)->delete();
            \App\Models\OrderItem::whereIn('order_id', $ids)->delete();
            \App\Models\Review::whereIn('order_id', $ids)->delete();
            Order::whereIn('id', $ids)->delete();
        });

        return response()->json(['status' => true, 'message' => 'Order(s) deleted successfully.']);
    }

    public function carts(Request $request)
    {
        if ($response = $this->adminOnly($request)) return $response;

        $request->validate([
            'ids'   => 'nullable|array',
            'ids.*' => 'string',
            'all'   => 'nullable|boolean',
        ]);

        if (empty($request->ids) && ! $request->boolean('all')) {
            return response()->json(['status' => false, 'message' => 'Provide ids or set all to true.'], 422);
        }

        $carts = $request->boolean('all') ? Cart::all() : Cart::whereIn('id', $request->ids)->get();

        if ($carts->isEmpty()) {
            return response()->json(['status' => false, 'message' => 'No carts found.'], 404);
        }

        DB::transaction(function () use ($carts) {
            $carts->each(fn($cart) => $cart->items()->delete());
            Cart::whereIn('id', $carts->pluck('id'))->delete();
        });

        return response()->json(['status' => true, 'message' => "{$carts->count()} cart(s) deleted successfully."]);
    }

    public function reviews(Request $request)
    {
        if ($response = $this->adminOnly($request)) return $response;

        $request->validate([
            'ids'   => 'nullable|array',
            'ids.*' => 'string',
            'all'   => 'nullable|boolean',
        ]);

        if (empty($request->ids) && ! $request->boolean('all')) {
            return response()->json(['status' => false, 'message' => 'Provide ids or set all to true.'], 422);
        }

        $deleted = $request->boolean('all')
            ? Review::query()->delete()
            : Review::whereIn('id', $request->ids)->delete();

        if (! $deleted) {
            return response()->json(['status' => false, 'message' => 'No reviews found.'], 404);
        }

        return response()->json(['status' => true, 'message' => "{$deleted} review(s) deleted successfully."]);
    }

    public function menus(Request $request)
    {
        if ($response = $this->adminOnly($request)) return $response;

        $request->validate([
            'ids'   => 'nullable|array',
            'ids.*' => 'string',
            'all'   => 'nullable|boolean',
        ]);

        if (empty($request->ids) && ! $request->boolean('all')) {
            return response()->json(['status' => false, 'message' => 'Provide ids or set all to true.'], 422);
        }

        $menus = $request->boolean('all') ? Menu::all() : Menu::whereIn('id', $request->ids)->get();

        if ($menus->isEmpty()) {
            return response()->json(['status' => false, 'message' => 'No menus found.'], 404);
        }

        DB::transaction(function () use ($menus) {
            foreach ($menus as $menu) {
                $images = json_decode($menu->getRawOriginal('images'), true) ?? [];
                foreach ($images as $image) {
                    Storage::disk('public')->delete($image);
                }
                $menu->inventoryLogs()->delete();
                $menu->delete();
            }
        });

        return response()->json(['status' => true, 'message' => "{$menus->count()} menu(s) deleted successfully."]);
    }

    public function categories(Request $request)
    {
        if ($response = $this->adminOnly($request)) return $response;

        $request->validate([
            'ids'   => 'nullable|array',
            'ids.*' => 'string',
            'all'   => 'nullable|boolean',
        ]);

        if (empty($request->ids) && ! $request->boolean('all')) {
            return response()->json(['status' => false, 'message' => 'Provide ids or set all to true.'], 422);
        }

        $deleted = $request->boolean('all')
            ? Category::query()->delete()
            : Category::whereIn('id', $request->ids)->delete();

        if (! $deleted) {
            return response()->json(['status' => false, 'message' => 'No categories found.'], 404);
        }

        return response()->json(['status' => true, 'message' => "{$deleted} categorie(s) deleted successfully."]);
    }

    public function discounts(Request $request)
    {
        if ($response = $this->adminOnly($request)) return $response;

        $request->validate([
            'ids'   => 'nullable|array',
            'ids.*' => 'string',
            'all'   => 'nullable|boolean',
        ]);

        if (empty($request->ids) && ! $request->boolean('all')) {
            return response()->json(['status' => false, 'message' => 'Provide ids or set all to true.'], 422);
        }

        $deleted = $request->boolean('all')
            ? Discount::query()->delete()
            : Discount::whereIn('id', $request->ids)->delete();

        if (! $deleted) {
            return response()->json(['status' => false, 'message' => 'No discounts found.'], 404);
        }

        return response()->json(['status' => true, 'message' => "{$deleted} discount(s) deleted successfully."]);
    }

    public function taxes(Request $request)
    {
        if ($response = $this->adminOnly($request)) return $response;

        $request->validate([
            'ids'   => 'nullable|array',
            'ids.*' => 'string',
            'all'   => 'nullable|boolean',
        ]);

        if (empty($request->ids) && ! $request->boolean('all')) {
            return response()->json(['status' => false, 'message' => 'Provide ids or set all to true.'], 422);
        }

        $deleted = $request->boolean('all')
            ? Tax::query()->delete()
            : Tax::whereIn('id', $request->ids)->delete();

        if (! $deleted) {
            return response()->json(['status' => false, 'message' => 'No taxes found.'], 404);
        }

        return response()->json(['status' => true, 'message' => "{$deleted} tax(es) deleted successfully."]);
    }

    public function zones(Request $request)
    {
        if ($response = $this->adminOnly($request)) return $response;

        $request->validate([
            'ids'   => 'nullable|array',
            'ids.*' => 'integer',
            'all'   => 'nullable|boolean',
        ]);

        if (empty($request->ids) && ! $request->boolean('all')) {
            return response()->json(['status' => false, 'message' => 'Provide ids or set all to true.'], 422);
        }

        $deleted = $request->boolean('all')
            ? Zone::query()->delete()
            : Zone::whereIn('id', $request->ids)->delete();

        if (! $deleted) {
            return response()->json(['status' => false, 'message' => 'No zones found.'], 404);
        }

        return response()->json(['status' => true, 'message' => "{$deleted} zone(s) deleted successfully."]);
    }

    public function tables(Request $request)
    {
        if ($response = $this->adminOnly($request)) return $response;

        $request->validate([
            'ids'   => 'nullable|array',
            'ids.*' => 'string',
            'all'   => 'nullable|boolean',
        ]);

        if (empty($request->ids) && ! $request->boolean('all')) {
            return response()->json(['status' => false, 'message' => 'Provide ids or set all to true.'], 422);
        }

        $deleted = $request->boolean('all')
            ? DiningTable::query()->delete()
            : DiningTable::whereIn('id', $request->ids)->delete();

        if (! $deleted) {
            return response()->json(['status' => false, 'message' => 'No tables found.'], 404);
        }

        return response()->json(['status' => true, 'message' => "{$deleted} table(s) deleted successfully."]);
    }

    public function packagings(Request $request)
    {
        if ($response = $this->adminOnly($request)) return $response;

        $request->validate([
            'ids'   => 'nullable|array',
            'ids.*' => 'string',
            'all'   => 'nullable|boolean',
        ]);

        if (empty($request->ids) && ! $request->boolean('all')) {
            return response()->json(['status' => false, 'message' => 'Provide ids or set all to true.'], 422);
        }

        $deleted = $request->boolean('all')
            ? TakeawayPackaging::query()->delete()
            : TakeawayPackaging::whereIn('id', $request->ids)->delete();

        if (! $deleted) {
            return response()->json(['status' => false, 'message' => 'No packagings found.'], 404);
        }

        return response()->json(['status' => true, 'message' => "{$deleted} packaging(s) deleted successfully."]);
    }

    public function campaigns(Request $request)
    {
        if ($response = $this->adminOnly($request)) return $response;

        $request->validate([
            'ids'   => 'nullable|array',
            'ids.*' => 'string',
            'all'   => 'nullable|boolean',
        ]);

        if (empty($request->ids) && ! $request->boolean('all')) {
            return response()->json(['status' => false, 'message' => 'Provide ids or set all to true.'], 422);
        }

        $campaigns = $request->boolean('all') ? Campaign::all() : Campaign::whereIn('id', $request->ids)->get();

        if ($campaigns->isEmpty()) {
            return response()->json(['status' => false, 'message' => 'No campaigns found.'], 404);
        }

        foreach ($campaigns as $campaign) {
            if ($campaign->image_path) {
                Storage::disk('public')->delete($campaign->image_path);
            }
            $campaign->delete();
        }

        return response()->json(['status' => true, 'message' => "{$campaigns->count()} campaign(s) deleted successfully."]);
    }

    public function careerApplications(Request $request)
    {
        if ($response = $this->adminOnly($request)) return $response;

        $request->validate([
            'ids'   => 'nullable|array',
            'ids.*' => 'string',
            'all'   => 'nullable|boolean',
        ]);

        if (empty($request->ids) && ! $request->boolean('all')) {
            return response()->json(['status' => false, 'message' => 'Provide ids or set all to true.'], 422);
        }

        $applications = $request->boolean('all') ? CareerApplication::all() : CareerApplication::whereIn('id', $request->ids)->get();

        if ($applications->isEmpty()) {
            return response()->json(['status' => false, 'message' => 'No applications found.'], 404);
        }

        foreach ($applications as $application) {
            if ($application->cv_path) {
                Storage::disk('public')->delete($application->cv_path);
            }
            if ($application->cover_letter_path) {
                Storage::disk('public')->delete($application->cover_letter_path);
            }
            $application->delete();
        }

        return response()->json(['status' => true, 'message' => "{$applications->count()} application(s) deleted successfully."]);
    }

    public function careerOpenings(Request $request)
    {
        if ($response = $this->adminOnly($request)) return $response;

        $request->validate([
            'ids'   => 'nullable|array',
            'ids.*' => 'string',
            'all'   => 'nullable|boolean',
        ]);

        if (empty($request->ids) && ! $request->boolean('all')) {
            return response()->json(['status' => false, 'message' => 'Provide ids or set all to true.'], 422);
        }

        $openings = $request->boolean('all') ? CareerOpening::all() : CareerOpening::whereIn('id', $request->ids)->get();

        if ($openings->isEmpty()) {
            return response()->json(['status' => false, 'message' => 'No openings found.'], 404);
        }

        foreach ($openings as $opening) {
            if ($opening->image_path) {
                Storage::disk('public')->delete($opening->image_path);
            }
            $opening->delete();
        }

        return response()->json(['status' => true, 'message' => "{$openings->count()} opening(s) deleted successfully."]);
    }

    public function inventoryLogs(Request $request)
    {
        if ($response = $this->adminOnly($request)) return $response;

        $request->validate([
            'ids'   => 'nullable|array',
            'ids.*' => 'string',
            'all'   => 'nullable|boolean',
        ]);

        if (empty($request->ids) && ! $request->boolean('all')) {
            return response()->json(['status' => false, 'message' => 'Provide ids or set all to true.'], 422);
        }

        $deleted = $request->boolean('all')
            ? InventoryLog::query()->delete()
            : InventoryLog::whereIn('id', $request->ids)->delete();

        if (! $deleted) {
            return response()->json(['status' => false, 'message' => 'No inventory logs found.'], 404);
        }

        return response()->json(['status' => true, 'message' => "{$deleted} inventory log(s) deleted successfully."]);
    }

    public function settings(Request $request)
    {
        if ($response = $this->adminOnly($request)) return $response;

        $request->validate([
            'ids'   => 'nullable|array',
            'ids.*' => 'integer',
            'all'   => 'nullable|boolean',
        ]);

        if (empty($request->ids) && ! $request->boolean('all')) {
            return response()->json(['status' => false, 'message' => 'Provide ids or set all to true.'], 422);
        }

        $deleted = $request->boolean('all')
            ? Setting::query()->delete()
            : Setting::whereIn('id', $request->ids)->delete();

        if (! $deleted) {
            return response()->json(['status' => false, 'message' => 'No settings found.'], 404);
        }

        return response()->json(['status' => true, 'message' => "{$deleted} setting(s) deleted successfully."]);
    }
}
