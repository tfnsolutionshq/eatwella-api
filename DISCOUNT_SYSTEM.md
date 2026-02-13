# Discount Code System Implementation

## Overview
Implemented a complete discount code system where users must enter a valid discount code to receive discounts on their orders.

## Database Changes

### Discounts Table
Added columns:
- `code` (string, unique) - The discount code users enter
- `usage_limit` (integer, nullable) - Maximum number of times the code can be used
- `used_count` (integer, default 0) - Tracks how many times the code has been used

### Orders Table
Added column:
- `discount_code` (string, nullable) - Stores which discount code was used

### Carts Table
Added column:
- `discount_code` (string, nullable) - Stores discount code applied to cart

## API Endpoints

### Public Endpoints

1. **Validate Discount Code**
   - POST `/api/discounts/validate`
   - Body: `{ "code": "SAVE20" }`
   - Returns discount details if valid

2. **Apply Discount to Cart**
   - POST `/api/cart/apply-discount`
   - Body: `{ "code": "SAVE20" }`
   - Applies discount code to current cart session

3. **Remove Discount from Cart**
   - DELETE `/api/cart/remove-discount`
   - Removes discount code from cart

### Admin Endpoints (Protected)

1. **Create Discount**
   - POST `/api/admin/discounts`
   - Body:
   ```json
   {
     "name": "Summer Sale",
     "code": "SUMMER20",
     "type": "percentage",
     "value": 20,
     "start_date": "2024-06-01",
     "end_date": "2024-08-31",
     "is_indefinite": false,
     "is_active": true,
     "usage_limit": 100
   }
   ```

2. **Update Discount**
   - PUT `/api/admin/discounts/{id}`

3. **List/View/Delete Discounts**
   - GET `/api/admin/discounts`
   - GET `/api/admin/discounts/{id}`
   - DELETE `/api/admin/discounts/{id}`

## Discount Types

1. **Percentage** - Reduces order by a percentage (e.g., 20% off)
2. **Fixed** - Reduces order by a fixed amount (e.g., ₦500 off)

## Discount Validation

A discount code is valid when:
- `is_active` is true
- Current date is >= `start_date`
- Current date is <= `end_date` (if not indefinite)
- `used_count` < `usage_limit` (if limit is set)

## Usage Flow

### Customer Flow:
1. Add items to cart
2. Enter discount code
3. System validates code and applies discount
4. Proceed to checkout
5. Discount is applied to final amount
6. Order is created with discount code tracked
7. Discount usage count is incremented

### Cart Calculations:
- `subtotal` - Sum of all items
- `discount_amount` - Calculated based on discount type
- `total` - Subtotal minus discount amount

## Email Templates

Both order emails now display:
- Discount code used (if applicable)
- Discount amount
- Original subtotal
- Final amount paid

## Model Methods

### Discount Model
- `isValid()` - Checks if discount is currently valid
- `calculateDiscount($amount)` - Calculates discount for given amount

### Cart Model
- `discount()` - Relationship to Discount model
- `getSubtotalAttribute()` - Calculates cart subtotal
- `getDiscountAmountAttribute()` - Calculates discount amount
- `getTotalAttribute()` - Calculates final total

## Security Features

- Discount codes are stored in uppercase
- Codes are unique in database
- Usage limits prevent abuse
- Expiration dates control validity period
- Admin-only creation/management

## Testing

To test the system:

1. Create a discount code via admin panel
2. Add items to cart
3. Apply discount code
4. Verify discount is calculated correctly
5. Complete checkout
6. Check order shows discount code
7. Verify usage count incremented
