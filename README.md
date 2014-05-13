# Discount - SilverStripe Shop SubModule

Allows creating discounts for products / orders.

 * Discount by various criteria, including:
  * Time
  * User group
  * Product/Category
  * Number of uses
  * Order value
  * Shipping zone
 * Coupon codes
 * Gift voucher products
 * Shipping discount

Discounts can be applied to individual products, cart subtotal, or shipping.
Discounts can be globally enabled/disabled.

## Requirements

 * Shop Module + its requirements

## Installation Instructions

 * Install code to your SilverStripe root directory.

via composer:

```sh
	composer require burnbright/silverstripe-shop-discount dev-master
```

If you are using the stepped checkout, add the `CheckoutStep_Discount` checkout step:

```yaml
CheckoutPage:
	steps:
		'contactdetails' : 'CheckoutStep_ContactDetails'
		'shippingaddress' : 'CheckoutStep_Address'
		'billingaddress' : 'CheckoutStep_Address'
		'shippingmethod' : 'CheckoutStep_ShippingMethod'
		'discount' : 'CheckoutStep_Discount' #here!
		'paymentmethod' : 'CheckoutStep_PaymentMethod'
		'summary' : 'CheckoutStep_Summary'
```
 
 Add the `OrderDiscountModifier` modifier to your order modifiers yaml config:

```yaml
Order:
	modifiers:
		- Blah
		- OrderDiscountModifier #here!
		- Blah
```
