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
  
## Stacking discounts and multiple coupons

By default only one discount applies at each level of an order: the single
best discount for each item, the single best discount on the cart subtotal and
the single best discount on shipping. Likewise, a customer can only have one
coupon code applied; entering a new code replaces the old one.

Stacking is opt-in, per discount or coupon, with two fields on the main tab:

 * **Allow stacking** - this discount can be combined with other discounts and
   coupons that also allow stacking.
 * **Priority** - the order stacked discounts are applied in, highest first.
   Ties are broken by creation order (oldest first).

### How stacked discounts are calculated

At each level (each item, the cart subtotal, shipping) the calculator works out
two options and uses whichever saves the customer more:

 1. the single best discount (stackable or not), as before, or
 2. all of the discounts that allow stacking, combined.

Stacked discounts **compound**: each one is calculated on the price remaining
after the higher priority discounts before it. For example, on a $200 cart:

| Stacked discounts (priority order) | Calculation | Saving |
| --- | --- | --- |
| 10% off, then 20% off | $20 + 20% of $180 | $56 |
| $50 off, then 50% off | $50 + 50% of $150 | $125 |
| 50% off, then $50 off | $100 + $50 | $150 |

A discount that does not allow stacking is never combined with another
discount at the same level - but it can still win outright if it is better
than the stack.

### Multiple coupon codes

When every applied coupon allows stacking, each code a customer enters is added
to the cart rather than replacing the previous one. The codes are stored in the
session (`cart.couponcodes`) and passed to the calculator in the
`CouponCodes` context.

If the customer enters a coupon that doesn't allow stacking while other coupons
are applied, or a stackable coupon while a non-stackable one is applied, the new
coupon replaces the applied ones. To show the customer an error instead:

```yaml
SilverShop\Discounts\Checkout\CouponCheckoutComponent:
  replace_non_stackable: false
```

The number of coupons per order is capped (default 5, `0` for unlimited):

```yaml
SilverShop\Discounts\Checkout\CouponCheckoutComponent:
  max_coupons_per_order: 3
```

When more than one coupon is applied, `CouponForm` shows a list of the applied
codes so customers can choose which to remove. `$CouponForm.AppliedCoupons`
is also available to templates (`Code`, `Title` and `Coupon` for each).

### Gotchas

 * **Item, cart and shipping discounts have always combined.** "Allow
   stacking" only controls combining several discounts *at the same level*.
   A non-stackable item discount will still combine with the best cart
   discount and the best shipping discount. Coupons are the exception: a
   non-stackable coupon can't be applied alongside any other coupon, whatever
   level each one applies to.
 * **Automatic discounts stack too.** The flag is on all discounts, not just
   coupons. A stackable automatic (sitewide) discount will combine with any
   stackable coupon the customer enters - only enable it on both if you are
   happy for them to combine.
 * **Priority matters for mixed types.** Applying a fixed amount before a
   percentage gives a smaller total saving than the other way round (see the
   table above). Give fixed-amount discounts the higher priority if you want
   to keep stacked savings conservative.
 * **Savings are capped.** Combined discounts can never take an item below
   zero, or the cart below what's left after item discounts. When the cap is
   hit, the lowest priority discounts in the stack get a smaller (or no)
   amount.
 * **Limits are per discount.** "Maximum amount" applies to each discount on
   its own, not to the stack as a whole.
 * **Product / category constrained cart discounts.** A stacked cart discount
   restricted to some products is calculated on the smaller of its matching
   items' total and the remaining cart total, so the compounding is
   approximate when stacked discounts target different products.
 * **Use limits count every stacked discount.** Each stacked discount or
   coupon is recorded against the order (with its own `DiscountAmount`), so
   each one uses up one of its own uses, and shows in the discount reports.
 * **Margins and coupon sharing.** Stacking makes it easier for customers to
   combine codes from affiliate and coupon sites. Keep high-value coupons
   non-stackable, set use limits and a priority order you're comfortable with,
   and consider `max_coupons_per_order`.
 * **Refunds and returns.** Each discount's amount is recorded per item / per
   order, so a partial refund needs to account for every discount that was
   applied, not just one.

### Upgrading

Existing discounts default to *Allow stacking* off and *Priority* 0, so the
calculated totals are unchanged until you opt in. Run `dev/build` to add the
new fields. The session key `cart.couponcode` is still set to the most recently
applied code, and `CouponCode` is still passed in the calculator context, for
code that relies on them.

When two discounts give exactly the same saving, the one processed first
(higher priority, then oldest) is now the one recorded against the order.
