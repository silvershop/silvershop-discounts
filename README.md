# Discount - SilverStripe Shop SubModule

Allows creating discounts for products / orders.

 * gift vouchers/cards
 * discount coupons
 * credit notes

Discounts can be applied to all products (ie order subtotal), or to individual products.

## Requirements

 * Shop Module + its requirements

## Installation Instructions

 * Install code to your SilverStripe root directory.

via composer:

```sh
	composer require burnbright/silverstripe-shop-discount dev-master
```

 * Add $CouponForm to your checkout page template to display the coupon entry form.
 * Add the appropriate modifier to your order modifiers yaml config

```yaml
Order:
	modifiers:
		- OrderCouponModifier
```