# Eatwella Recommendation System Architecture

## 1. System Overview
The Eatwella Recommendation System is designed to provide real-time, personalized product suggestions to users based on their current cart contents. It utilizes a **Hybrid Filtering** approach, combining Collaborative Filtering (frequently bought together) and Content-Based Filtering (category and attribute similarity).

## 2. Architecture Components

### A. Data Ingestion & Pre-computation Pipeline
Instead of calculating complex similarities on-the-fly, the system uses an asynchronous data ingestion pipeline:
- **Source Data:** `orders`, `order_items`, and `menus`.
- **Processing Job:** A scheduled Artisan command (`recommendations:generate`) processes historical data nightly.
- **Collaborative Filtering:** Uses item-to-item co-occurrence matrices (Apriori algorithm approach). It calculates the confidence of purchasing Item B given Item A is in the cart.
- **Content-Based Filtering:** Analyzes menu attributes (e.g., `category_id`) to suggest complementary items.
- **Storage:** Pre-computed recommendation scores are stored in the `menu_recommendations` table for rapid retrieval.

### B. Recommendation Engine (Hybrid Filtering)
The engine calculates a final weighted score:
- **Score = (W1 * Collaborative Score) + (W2 * Content Score)**
- It guarantees a fallback: if a new product has no sales history (Cold Start problem), it falls back to the Content-Based score.

### C. Real-time API & Caching (<200ms)
- **Endpoint:** `GET /api/recommendations/cart`
- **Mechanism:** When a user queries the API with their current cart item IDs, the system fetches the top N pre-computed recommendations from the database.
- **Caching Layer:** Queries are cached using Laravel's Cache facade (Redis/Memcached) keyed by the sorted array of cart `menu_id`s. This ensures responses well under the 200ms threshold.

### D. A/B Testing Framework
- Users/Requests are dynamically bucketed into testing groups (e.g., Group A: Collaborative only, Group B: Hybrid).
- The assigned `ab_test_group` is returned in the API payload.
- **Metrics Tracking:** A dedicated endpoint `POST /api/recommendations/track` logs impressions, clicks, and add-to-cart events into the `recommendation_logs` table.
- **KPIs Evaluated:** Click-Through Rate (CTR), Conversion Rate (CVR), and Average Order Value (AOV) impact.

### E. Integration
- The API accepts either a `cart_id` (integrating with the existing `Cart` model) or an array of `menu_ids` (for guest/stateless frontends).
- Filters out items that are already in the cart and items where `is_available = false`.

## 3. Database Schema
- **`menu_recommendations`**: `id`, `menu_id`, `recommended_menu_id`, `algorithm`, `score`, `created_at`, `updated_at`.
- **`recommendation_logs`**: `id`, `session_id`, `user_id`, `recommended_menu_id`, `ab_test_group`, `action` (view, click, add_to_cart), `created_at`.

## 4. API Specifications

### Fetch Recommendations
`POST /api/recommendations/cart`
**Payload:**
```json
{
    "menu_ids": ["uuid-1", "uuid-2"]
}
```
**Response:**
```json
{
    "data": [
        {
            "id": "uuid-3",
            "name": "Coca Cola",
            "price": "2.50",
            "images": [...],
            "recommendation_score": 0.85
        }
    ],
    "ab_test_group": "B",
    "response_time_ms": 45
}
```

### Track Interaction
`POST /api/recommendations/track`
**Payload:**
```json
{
    "recommended_menu_id": "uuid-3",
    "ab_test_group": "B",
    "action": "add_to_cart"
}
```

## 5. Deployment Procedures
1. Run Migrations: `php artisan migrate`
2. Initial Data Ingestion: `php artisan recommendations:generate`
3. Schedule the Cron Job: Ensure Laravel scheduler (`php artisan schedule:run`) is active to rebuild recommendations nightly.
4. Ensure Redis/Memcached is configured as the `CACHE_DRIVER` in `.env` for optimal sub-200ms performance.
