# SilverShop - Discounts

[![Latest Stable Version](https://poser.pugx.org/silvershop/discounts/v/stable.png)](https://packagist.org/packages/silvershop/discounts)
[![Latest Unstable Version](https://poser.pugx.org/silvershop/discounts/v/unstable.png)](https://packagist.org/packages/silvershop/discounts)
[![Build Status](https://secure.travis-ci.org/silvershop/silvershop-discounts.png)](http://travis-ci.org/silvershop/silvershop-discounts)
[![Code Coverage](https://scrutinizer-ci.com/g/silvershop/silvershop-discounts/badges/coverage.png?s=cae0140f6d9a99c35b20c23b8bbe88711d526246)](https://scrutinizer-ci.com/g/silvershop/silvershop-discounts/)
[![Scrutinizer Quality Score](https://scrutinizer-ci.com/g/silvershop/silvershop-discounts/badges/quality-score.png?s=802731e23565b5a7051b5622a56fccb7b764662a)](https://scrutinizer-ci.com/g/silvershop/silvershop-discounts/)
[![Total Downloads](https://poser.pugx.org/silvershop/discounts/downloads.png)](https://packagist.org/packages/silvershop/discounts)

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

 * SilverShop Module

## Installation

```sh
	composer require silvershop/discounts dev-master
```

If you are using the stepped checkout, add the `CheckoutStep_Discount` checkout 
step:

```yaml
SilverShop\Page\CheckoutPage:
  steps:
    'discount' : 'SilverShop\Discounts\Checkout\Step\CheckoutStepDiscount'
```

If you would like to display the coupon form seperately to the checkout form,
apply the following extension. This will make `CouponForm` available in the 
checkout template:

```yaml
SilverShop\Page\CheckoutPageController:
  extensions:
    - SilverShop\Discounts\Extensions\CouponFormCheckoutDecorator
```

Add the `OrderDiscountModifier` modifier to your order modifiers yaml config:

```yaml
SilverShop\Model\Order:
  modifiers:
    - SilverShop\Discounts\Model\Modifiers\OrderDiscountModifier
```

## Specific Pricing

Extend `Product` and/or `ProductVariation` with the `SpecificPricingExtension` 
to introduce a pricing table for each product. This allows admins to set prices 
according to things like, date, and membership group.

```yaml
SilverShop\Page\Product:
  extensions:
    - SilverShop\Discounts\Extensions\SpecificPricingExtension
```
