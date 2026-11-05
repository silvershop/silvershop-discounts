# SilverShop - Discounts

[![Latest Stable Version](https://poser.pugx.org/silvershop/discounts/v/stable.png)](https://packagist.org/packages/silvershop/discounts)
[![Latest Unstable Version](https://poser.pugx.org/silvershop/discounts/v/unstable.png)](https://packagist.org/packages/silvershop/discounts)
[![CI](https://github.com/silvershop/silvershop-discounts/actions/workflows/ci.yml/badge.svg)](https://github.com/silvershop/silvershop-discounts/actions/workflows/ci.yml)
[![Total Downloads](https://poser.pugx.org/silvershop/discounts/downloads.png)](https://packagist.org/packages/silvershop/discounts)

Discounts, coupons and gift vouchers for [SilverShop](https://github.com/silvershop/silvershop-core).

Create price reductions that are worked out **at checkout**, once the whole order
is known, and shown as their own line under the order subtotal. A discount can
apply automatically, or be locked behind a coupon code the customer enters.

* **Flexible actions** - reduce by a percentage or a fixed amount, applied to
  each item, the cart subtotal, the shipping cost, or the whole order.
* **Constraints** - restrict a discount by date range, customer group,
  membership, product, category, product type, order value, address zone, total
  number of uses, and uses per customer.
* **Coupon codes** - one code per promotion, or many unique codes for the same
  promotion (bulk / single-use codes), generated or entered by hand.
* **Stacking** - opt in, per discount, to combine several discounts and coupons
  on one order in a defined priority order.
* **Gift vouchers** - sell a gift voucher product that emails the buyer a coupon
  code worth what they paid.
* **Specific prices** - a separate, simpler feature for per-product sale or
  member pricing that changes the *displayed* price (see below).

> **Discounts vs Specific Prices.** A *Discount* is calculated at checkout and
> never changes the price shown on a product page. A *Specific Price* changes the
> product's displayed selling price. Use a Specific Price for a visible sale or
> member price; use a Discount or Coupon when the reduction depends on the order
> or on a code. See [docs/en/index.md](docs/en/index.md) for the full comparison.

## Requirements

* PHP 8.3+
* [silvershop/core](https://github.com/silvershop/silvershop-core) ^6
* SilverStripe CMS ^6

## Installation

```sh
composer require silvershop/discounts ^4
```

Then run `dev/build?flush=1`.

For earlier platforms use a matching major: `silvershop/discounts:^3` for
SilverShop 3 / SilverStripe 4.

## Setup

### 1. Register the order modifier (required)

Discounts are applied through `OrderDiscountModifier`. Add it to your order
modifiers so the reduction is calculated and shown in the cart and checkout:

```yaml
SilverShop\Model\Order:
  modifiers:
    - SilverShop\Discounts\Model\Modifiers\OrderDiscountModifier
```

The modifier row renders through the standard `$Modifiers` loop your cart and
checkout templates already use. It only shows when a discount actually applies,
and its subtitle lists the codes used.

### 2. Let customers enter a coupon code

Pick whichever fits your checkout.

**Stepped checkout** - add the discount step:

```yaml
SilverShop\Page\CheckoutPage:
  steps:
    'discount': 'SilverShop\Discounts\Checkout\Step\CheckoutStepDiscount'
```

**Single-page checkout / a standalone coupon box** - expose a `CouponForm` on
the checkout controller:

```yaml
SilverShop\Page\CheckoutPageController:
  extensions:
    - SilverShop\Discounts\Extensions\CouponFormCheckoutDecorator
```

`$CouponForm` is then available in the checkout template. It carries a CSRF
token, lets the customer remove applied codes, and (when stacking is on) lists
every applied code.

> Using the [`silvershop-suite/storefront`](https://github.com/silvershop-suite/silvershop-storefront)
> theme? It wires its own coupon box on the cart page, so you do not need the
> decorator above.

## Coupon codes

A coupon is a discount with a code. There are two ways to code one, and they
coexist:

* **A single code** on the coupon itself - the classic one code per promotion.
  Codes are forced to uppercase alphanumerics (the hyphen is allowed, e.g.
  `SUMMER-2026`). If you leave the code blank, one is generated on save.
* **Many child codes** for one coupon (issue
  [#31](https://github.com/silvershop/silvershop-discounts/issues/31)) - one
  promotion (one set of rules, one usage report) handed out as many unique
  codes, each with its own use limit and per-customer limit. Entering any of the
  coupon's codes resolves to the same coupon. Once a coupon has child codes, its
  single legacy code is ignored and hidden.

Generate codes in bulk from the **Codes** tab of a coupon (how many, a prefix,
length, and the per-code limits), or generate a batch of whole coupons from
**Generate Multiple Coupons** in the Discounts admin. Generated codes use a
cryptographically secure random source and are guaranteed unique across every
coupon and code.

Relevant config:

```yaml
SilverShop\Discounts\Model\OrderCoupon:
  generated_code_length: 10   # characters added after any prefix
  minimum_code_length: null   # reject hand-entered codes shorter than this
```

## Constraints

Open a discount's **Constraints** tab to restrict when it applies:

| Constraint | Restricts to |
| --- | --- |
| Date / time | a start and/or end date |
| Membership group | members of chosen groups |
| Specific member | a single member |
| Products | carts containing chosen products |
| Categories | carts containing products from chosen categories |
| Product type | a product class |
| Order value | a minimum (and/or maximum) order value |
| Address zone | a shipping zone |
| Number of uses | a total use limit across all customers |
| Uses per customer | a limit per member (logged in) or per email (guest) |

Global criteria (such as the date) are checked first, then the cart must contain
at least one item that matches any item-level criteria. Use limits are
**pending-aware**: carts that applied a code and are mid-payment count towards
the limit for a short window (`Discount.unpaid_use_timeout`, default 10 minutes),
so a single-use code cannot be redeemed twice by racing two checkouts.

## Stacking

By default only the single best discount applies at each level (items, cart,
shipping), and only one coupon can be applied at a time. Turn on **Allow
stacking** (and set a **Priority**) on each discount you want to combine.
Stacked discounts compound in priority order, and the calculator always uses
whichever of "single best" or "all stackable combined" saves the customer more.

Stacking has real pricing and margin implications (compounding order, combining
automatic discounts with coupons, affiliate-code sharing). Read
[docs/en/index.md](docs/en/index.md#stacking-discounts-and-multiple-coupons)
before enabling it.

```yaml
SilverShop\Discounts\Checkout\CouponCheckoutComponent:
  max_coupons_per_order: 5    # 0 = unlimited
  replace_non_stackable: true # false = reject instead of replacing
```

## Abuse protection

Coupon entry is rate limited: after a number of failed (unknown-code) attempts from
one client, further tries are blocked for a short window, which slows down code
guessing. Tune or disable it, and optionally hide whether a rejected code was
unknown or just invalid (an enumeration oracle) at the cost of less helpful
customer feedback:

```yaml
SilverShop\Discounts\Checkout\CouponCheckoutComponent:
  apply_attempt_limit: 10      # failed attempts per client before a block (0 = off)
  apply_attempt_decay: 5       # minutes the block lasts
  generic_coupon_errors: false # true = one uniform "can't be applied" message

SilverShop\Discounts\Model\OrderCoupon:
  minimum_code_length: 6       # reject hand-entered codes shorter than this
```

## Gift vouchers

Create a **Gift Voucher** product. When an order containing it is paid, the buyer
is emailed a coupon code for each voucher bought, worth exactly what they paid
(single use, and the voucher must be spent in one order). Vouchers can have a
fixed price, or let the customer choose the amount (with an optional minimum).

```yaml
# a gift voucher is a product, so purchasing must be globally enabled
SilverShop\Discounts\Page\GiftVoucherProduct:
  global_allow_purchase: true
```

Override `SilverShop/Discounts/Email/GiftVoucherEmail[Plain]` templates to brand
the voucher email.

## Specific pricing

Extend `Product` and/or `ProductVariation` with `SpecificPricingExtension` to
give each one a pricing table (a price for a date range and/or a member group).
This changes the product's displayed selling price.

```yaml
SilverShop\Page\Product:
  extensions:
    - SilverShop\Discounts\Extensions\SpecificPricingExtension
SilverShop\Model\Variation\Variation:
  extensions:
    - SilverShop\Discounts\Extensions\ProductVariationDiscountExtension
```

In templates:

* Regular price: `$BasePrice` (product), `$Price` (variation)
* Current selling price: `$Price` (product), `$sellingPrice` (variation)
* `$IsReduced` - is a specific price currently lowering it
* `$TotalReduction` - the amount saved

```ss
<% if $IsReduced %>
    <del>$BasePrice.Nice</del> <strong>$Price.Nice</strong>
    <span class="saving"><%t Product.SAVE "Save" %> $TotalReduction.Nice</span>
<% else %>
    $Price.Nice
<% end_if %>
```

## Permissions

Managing discounts, coupons and codes requires the **`MANAGE_DISCOUNTS`**
permission (grant it under *Security &rarr; Groups &rarr; Permissions*), in
addition to access to the Discounts section of the CMS.

## Reporting

* A **Discounts** report (under *Reports*) shows total savings and use counts.
* Each discount's **Usage** tab lists the orders it was used on.
* The `discounts-reconcile-usage` task recomputes the stored usage counters from
  paid orders. Run it once after upgrading to a version with multi-code coupons,
  and any time you want to heal counter drift:

  ```sh
  vendor/bin/sake tasks:discounts-reconcile-usage
  ```

## Development

```sh
composer test      # PHPUnit
composer phpstan    # static analysis (level 5 + silverstan)
composer lint       # phpcs (PSR-12)
composer lint-clean # phpcbf autofix
```

## License

BSD-3-Clause. See [LICENSE](LICENSE).
