# Outlets/Branches Feature — Implementation Context

## Project
- Laravel API (no frontend, API-only)
- Auth: Laravel Sanctum
- DB: MySQL
- Role-based access via `requireRole()` in base Controller
- Existing roles: `admin, supervisor, attendant, kitchen, store_keeper, delivery_agent, customer`

## Feature Summary
Introduce outlets/branches as a first-class entity. Every non-admin, non-customer user must belong to an outlet. Customers select an outlet before ordering. Stock is tracked per outlet. Availability hours have a two-tier system: global defaults (admin) and outlet-level overrides (supervisor).

---

## Final Schema Plan

| Table | Purpose |
|---|---|
| `outlets` | outlet/branch records, linked to `city_id` |
| `outlet_user` | delivery agent ↔ outlet many-to-many |
| `outlet_availability_hours` | supervisor overrides per outlet per day |
| `outlet_menu_stock` | stock per menu per outlet |
| `delivery_agent_cities` | delivery agent ↔ city many-to-many |
| `users.outlet_id` | nullable FK for attendant, supervisor, kitchen, store_keeper |
| `orders.outlet_id` | nullable FK, null for legacy orders |
| `carts.outlet_id` | customer's chosen outlet session |

---

## Roles & Outlet Rules

| Role | Outlet Assignment | Notes |
|---|---|---|
| `admin` | none | global access |
| `customer` | none | selects outlet per cart session |
| `supervisor` | single outlet | can override availability hours for their outlet only |
| `attendant` | single outlet | sees/manages orders for their outlet only |
| `kitchen` | single outlet | sees/manages orders for their outlet only |
| `store_keeper` | single outlet | can update stock for their outlet only |
| `delivery_agent` | many outlets (city-scoped) | assigned via `outlet_user` pivot, city validated via `delivery_agent_cities` |

### Supervisor Restrictions (cannot do):
- Create/edit menus or pricing
- Manage taxes, discounts, delivery prices
- Manage global settings
- Create outlets or assign supervisors
- Create staff

### Supervisor Permissions (can do):
- Override availability hours for their outlet (open/close, enabled, order_types per day)
- Update menu stock for their outlet
- View their outlet details and staff

---

## Phase-by-Phase Plan

### Phase 1 — Database & Migrations
**Files to create:**
- `create_outlets_table` migration
- `create_outlet_user_table` migration (delivery agent pivot)
- `create_outlet_availability_hours_table` migration
- `create_outlet_menu_stock_table` migration
- `create_delivery_agent_cities_table` migration
- `add_outlet_id_to_users_table` migration
- `add_outlet_id_to_orders_table` migration
- `add_outlet_id_to_carts_table` migration

**Key details:**
- `outlets`: `id (uuid), name, address, phone, city_id (FK), is_active (bool default true), timestamps`
- `outlet_user`: `outlet_id, user_id` (no timestamps needed, simple pivot)
- `outlet_availability_hours`: `id, outlet_id, day (enum), enabled (bool), open (nullable string), close (nullable string), order_types (json nullable), timestamps`
- `outlet_menu_stock`: `id, outlet_id, menu_id, stock_quantity (int default 0), timestamps` — unique on `outlet_id, menu_id`
- `delivery_agent_cities`: `user_id, city_id` (simple pivot)
- `users.outlet_id`: nullable, FK to outlets, after `role` column
- `orders.outlet_id`: nullable, FK to outlets
- `carts.outlet_id`: nullable, FK to outlets
- On migration of `outlet_menu_stock`, copy `menus.stock_quantity` into each outlet's record for all existing outlets

**Status: [ ] Not started**

---

### Phase 2 — Models & Relationships
**Files to create/modify:**
- `app/Models/Outlet.php` (new)
- `app/Models/OutletAvailabilityHour.php` (new)
- `app/Models/OutletMenuStock.php` (new)
- `app/Models/User.php` (modify)
- `app/Models/Menu.php` (modify)
- `app/Models/Cart.php` (modify)
- `app/Models/Order.php` (modify)
- `app/Models/City.php` (modify)

**Key relationships:**
- `Outlet`: `belongsTo(City)`, `hasMany(User)` (single-outlet roles), `belongsToMany(User)` via `outlet_user` (delivery agents), `hasMany(OutletAvailabilityHour)`, `hasMany(OutletMenuStock)`, `supervisors()` scope
- `User`: `belongsTo(Outlet)`, `belongsToMany(Outlet)` via `outlet_user`, `belongsToMany(City)` via `delivery_agent_cities`
- `Menu`: `hasMany(OutletMenuStock)`
- `OutletMenuStock`: `belongsTo(Outlet)`, `belongsTo(Menu)`
- `Cart`: `belongsTo(Outlet)`
- `Order`: `belongsTo(Outlet)`
- `City`: `hasMany(Outlet)`

**Status: [ ] Not started**

---

### Phase 3 — Outlet Management (Admin)
**Files to create/modify:**
- `app/Http/Controllers/Admin/OutletController.php` (new)
- `routes/api.php` (modify — add outlet routes)

**Endpoints:**
- `GET /admin/outlets` — list outlets with city, supervisor count
- `POST /admin/outlets` — create outlet (requires: name, city_id, address, phone)
- `GET /admin/outlets/{outlet}` — show outlet with staff and hours
- `PUT /admin/outlets/{outlet}` — update outlet details
- `PATCH /admin/outlets/{outlet}/toggle` — activate/deactivate
- `POST /admin/outlets/{outlet}/assign-supervisor` — assign one or more supervisors (validates role = supervisor)
- `POST /admin/outlets/{outlet}/assign-delivery-agent` — attach delivery agent (validates city match via delivery_agent_cities)
- `DELETE /admin/outlets/{outlet}/remove-user/{user}` — detach/unassign user from outlet

**Staff creation update (UserController):**
- `storeStaff`: require `outlet_id` for roles: attendant, supervisor, kitchen, store_keeper
- `updateUserProfile`: allow setting/changing `outlet_id`

**Status: [ ] Not started**

---

### Phase 4 — Permission Scoping & Middleware
**Files to create/modify:**
- `app/Http/Controllers/Controller.php` (modify — add `outletContext()` helper)
- `app/Http/Controllers/Admin/OrderController.php` or `OrderController.php` (modify — scope by outlet)
- `app/Http/Controllers/MenuController.php` (modify — scope stock resolution)
- `app/Http/Controllers/KitchenController.php` (modify — scope by outlet)

**Key changes:**
- Add `outletContext(Request $request): ?Outlet` to base Controller — returns the user's outlet based on role
- Order queries for attendant/kitchen/store_keeper/supervisor filter by `outlet_id`
- Supervisor blocked from: menu CRUD, tax, discount, delivery, global settings, outlet management, staff creation
- Stock reads resolve from `outlet_menu_stock` when outlet context is available

**Status: [ ] Not started**

---

### Phase 5 — Availability Hours (Two-Tier)
**Files to create/modify:**
- `app/Http/Controllers/Supervisor/AvailabilityController.php` (new)
- `app/Http/Controllers/Admin/SettingsController.php` (modify — public hours endpoint becomes outlet-aware)
- `routes/api.php` (modify — add supervisor routes)

**Endpoints:**
- `GET /supervisor/outlet` — returns supervisor's outlet: details, staff list, current availability hours (override or global)
- `PUT /supervisor/availability-hours` — write outlet-level override to `outlet_availability_hours`
- `GET /availability-hours?outlet_id=` — public endpoint, resolves: outlet override → global default fallback. `outlet_id` is required if outlets exist.

**Resolution logic:**
```
if outlet_id provided:
    check outlet_availability_hours for that outlet
    if found → return outlet override
    else → return global Setting('availability_hours')
else:
    return global Setting('availability_hours')
```

**Status: [ ] Not started**

---

### Phase 6 — Customer Outlet Selection
**Files to create/modify:**
- `app/Http/Controllers/OutletController.php` (new — public)
- `app/Http/Controllers/CartController.php` (modify — accept outlet_id)
- `app/Http/Controllers/CustomerController.php` (modify — checkout validates outlet)
- `routes/api.php` (modify)

**Endpoints:**
- `GET /outlets?state_id=&city_id=` — public, returns active outlets filtered by location
- `GET /outlets/{outlet}` — public, returns single outlet details

**Cart changes:**
- `POST /cart` and `GET /cart` become outlet-aware via `outlet_id` on cart
- When `outlet_id` changes on an existing cart, carry items over but return `outlet_changed: true` flag in response
- Checkout validates: outlet is active, outlet is within availability hours at time of order

**Status: [ ] Not started**

---

### Phase 7 — Per-Outlet Stock Management
**Files to create/modify:**
- `app/Http/Controllers/Admin/OutletMenuStockController.php` (new)
- `app/Http/Controllers/Supervisor/StockController.php` (new)
- `app/Http/Controllers/MenuController.php` (modify — public menu listing resolves stock from outlet context)
- `routes/api.php` (modify)

**Endpoints:**
- `GET /admin/outlets/{outlet}/stock` — list all menu stocks for an outlet
- `PATCH /admin/outlets/{outlet}/menus/{menu}/stock` — admin updates any outlet's stock
- `PATCH /supervisor/menus/{menu}/stock` — supervisor updates stock for their outlet
- `PATCH /store-keeper/menus/{menu}/stock` — store_keeper updates stock for their outlet
- `GET /admin/menus/{menu}/inventory-logs` — already exists, extend to accept `outlet_id` filter

**Public menu stock resolution:**
- `GET /menus` and `GET /menus/{menu}` — when `outlet_id` present in request, return stock from `outlet_menu_stock` instead of `menus.stock_quantity`

**Status: [ ] Not started**

---

### Phase 8 — Delivery Agent City Scoping
**Files to create/modify:**
- `app/Http/Controllers/Admin/OutletController.php` (modify — city validation on assign-delivery-agent)
- `app/Http/Controllers/Admin/UserController.php` (modify — manage delivery agent cities)
- `routes/api.php` (modify)

**Endpoints:**
- `POST /admin/users/{user}/cities` — assign cities to a delivery agent
- `DELETE /admin/users/{user}/cities/{city}` — remove city from delivery agent
- `GET /admin/users/{user}/cities` — list agent's assigned cities

**Validation rule:**
When attaching a delivery agent to an outlet via `POST /admin/outlets/{outlet}/assign-delivery-agent`:
1. Check that the outlet's `city_id` exists in the agent's `delivery_agent_cities` records
2. If not — return 422 with message: "Delivery agent is not assigned to this outlet's city"

**Status: [ ] Not started**

---

## Important Backward Compatibility Notes
- `orders.outlet_id` is nullable — all existing orders remain valid with null
- `carts.outlet_id` is nullable — existing carts unaffected
- `users.outlet_id` is nullable — existing users unaffected
- `menus.stock_quantity` column stays but is no longer the source of truth once outlets exist
- Public `/availability-hours` without `outlet_id` still works — returns global default
- All existing admin routes remain unchanged in behavior

---

## Key Files Reference
| File | Role |
|---|---|
| `app/Http/Controllers/Controller.php` | Base controller with `requireRole()` |
| `app/Http/Controllers/Admin/SettingsController.php` | Global settings + availability hours |
| `app/Http/Controllers/Admin/UserController.php` | Staff management |
| `app/Models/User.php` | User model, roles, relationships |
| `app/Models/Setting.php` | Key-value settings store |
| `database/migrations/` | All migrations |
| `routes/api.php` | All API routes |
