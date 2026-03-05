<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ReviewController extends Controller
{
    /**
     * Store a newly created review in storage.
     */
    public function store(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'order_id' => 'required|uuid|exists:orders,id',
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:1000',
        ]);

        $order = Order::where('id', $validated['order_id'])
            ->where('user_id', $user->id)
            ->first();

        if (!$order) {
            return response()->json(['message' => 'Order not found or does not belong to user.'], 404);
        }

        if ($order->status !== 'completed') {
            return response()->json(['message' => 'You can only review completed orders.'], 400);
        }

        if ($order->review) {
            return response()->json(['message' => 'You have already reviewed this order.'], 400);
        }

        $review = Review::create([
            'user_id' => $user->id,
            'order_id' => $order->id,
            'rating' => $validated['rating'],
            'comment' => $validated['comment'] ?? null,
            'is_published' => true, // Auto-publish by default, can be changed based on policy
        ]);

        return response()->json([
            'message' => 'Review submitted successfully',
            'review' => $review
        ], 201);
    }

    /**
     * Display a listing of reviews (Admin).
     */
    public function index(Request $request)
    {
        if ($response = $this->requireRole($request, ['admin'])) {
            return $response;
        }

        $query = Review::with(['user:id,name,email', 'order:id,order_number,total_amount']);

        if ($request->has('rating')) {
            $query->where('rating', $request->rating);
        }

        if ($request->has('published')) {
            $query->where('is_published', $request->boolean('published'));
        }

        return $query->latest()->paginate($request->get('per_page', 15));
    }

    /**
     * Toggle published status of a review (Admin).
     */
    public function togglePublish(Request $request, Review $review)
    {
        if ($response = $this->requireRole($request, ['admin'])) {
            return $response;
        }

        $review->update([
            'is_published' => !$review->is_published
        ]);

        return response()->json([
            'message' => 'Review publish status updated',
            'review' => $review
        ]);
    }

    /**
     * Remove the specified review from storage (Admin).
     */
    public function destroy(Request $request, Review $review)
    {
        if ($response = $this->requireRole($request, ['admin'])) {
            return $response;
        }

        $review->delete();

        return response()->json(['message' => 'Review deleted successfully']);
    }
}
