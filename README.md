# SilverShop - Discounts

[![Latest Stable Version](https://poser.pugx.org/silvershop/discounts/v/stable.png)](https://packagist.org/packages/silvershop/discounts)
[![Latest Unstable Version](https://poser.pugx.org/silvershop/discounts/v/unstable.png)](https://packagist.org/packages/silvershop/discounts)
[![Build Status](https://secure.travis-ci.org/silvershop/silvershop-shipping.png)](http://travis-ci.org/silvershop/silvershop-shipping)
[![Code Coverage](https://scrutinizer-ci.com/g/silvershop/silvershop-shipping/badges/coverage.png?s=cae0140f6d9a99c35b20c23b8bbe88711d526246)](https://scrutinizer-ci.com/g/silvershop/silvershop-shipping/)
[![Scrutinizer Quality Score](https://scrutinizer-ci.com/g/silvershop/silvershop-shipping/badges/quality-score.png?s=802731e23565b5a7051b5622a56fccb7b764662a)](https://scrutinizer-ci.com/g/silvershop/silvershop-shipping/)
[![Total Downloads](https://poser.pugx.org/silvershop/shipping/downloads.png)](https://packagist.org/packages/silvershop/shipping)

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
	composer require silvershop/discounts dev-master
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

If you would like to display the coupon form seperately to the checkout form,
apply the following extension. This will make `CouponForm` available in the checkout template:

```yaml
CheckoutPage_Controller:
  extensions:
    - CouponFormCheckoutDecorator
```

Add the `OrderDiscountModifier` modifier to your order modifiers yaml config:

```yaml
Order:
  modifiers:
    - Blah
    - OrderDiscountModifier #here!
    - Blah
```

## Specific Pricing

Extend `Product` and/or `ProductVariation` with the `SpecificPricingExtension` to introduce a pricing table for each product. This allows admins to set prices according to things like, date, and membership group.

Configure as follows:
```yaml
Product:
  extensions:
    - SpecificPricingExtension
ProductVariation:
  extensions:
    - SpecificPricingExtension
```

## Upgrading

`OrderCoupon` has become a subclass of `Discount`, so if your existing database contains `OrderCoupon`, it might be best to rename it to `Discount` before running `dev/build?flush=1`.
