# Discounts

Discounts come in two forms:

 * Temporary price markdown on one or many products - displayed on front-end, and may vary, depending on logged in member
 * Coupon/Voucher codes - unique code entered at checkout

## Vouchers

### Setup Options

 * Code (always required). By default a coupon code is 10 characters long.
 * Amount/Percent - how much to take off.
 
 * Usage limit - unlimited, or a certian number of uses
 * Use period - specify a start / end period that the voucher can be used within.
 * Product range - restrict to specific products.

### How it works

When the user enters a cupon at the checkout, the coupon will check that the given order matches
its criteria. First global criteria (like the current date) will be checked, and then it will
check that at least one item in the cart matches the item citeria (eg product is from particular category).
 
### Common use cases
 
 * Unique coupon codes per customer, for a one-time sale.
 * Coupon code, with an expiry date, to be used by many people.
 * Coupon to use on a single product.
  
### System Restrictions

 * Multiple coupons cannot be used for a single product.
