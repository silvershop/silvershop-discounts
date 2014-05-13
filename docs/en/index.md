# Discounts

Discounts can be:

 * Temporary price markdowns - restricted by various chosen constraints.
 * Coupon/Voucher codes - unique code entered at checkout, and can also be restriced to the same various constraints.

The discount actions can be either marking down by percent or a fixed amount.
These markdowns can apply to either each item, the items subtotal, or the shipping cost.

## Constraints

 * Products
 * Categories
 * Date / time
 * Membership group
 * Number of uses
 * Order value
 * Address zone

### How it works

When the user enters a coupon at the checkout, the coupon will check that the given order matches
its criteria. First global criteria (like the current date) will be checked, and then it will
check that at least one item in the cart matches the item citeria (eg product is from particular category).
  
### System Restrictions

 * Multiple coupons cannot be used for a single product.
