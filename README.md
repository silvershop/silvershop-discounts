# Discount - SilverStripe Shop SubModule

Allows creating discounts for products / orders.

 * gift vouchers/cards
 * discount coupons
 * credit notes

Discounts can be applied to all products (ie order subtotal), or to individual products.

## Requirements

 * Shop Module + its own requirements

## Installation Instructions

 * Install code to your SilverStripe root directory.
 * Add $CouponForm to your checkout page template to display the coupon entry form.
 * Add the appropriate modifier to your Order::set_modifiers(array(...)) config

```
	Order::set_modifiers(array(
		"OrderCouponModifier"
	));
```