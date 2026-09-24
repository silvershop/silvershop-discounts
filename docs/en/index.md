# Discounts

## Discounts vs Specific Prices

This module has two separate ways to reduce what a customer pays. They look similar in the CMS
but behave very differently:

| | Discounts & Coupons | Specific Prices |
|---|---|---|
| Where to set up | **Discounts** section in the CMS | **Pricing** tab of a product or variation |
| When it applies | At checkout, once the order is known | Whenever the product's price is read |
| Changes the displayed product price | No | Yes (`sellingPrice()`) |
| Shows in the cart as | Its own "Discount" line under the subtotal | A lower unit price on the item |
| Can depend on the whole order | Yes: order value, other products in cart, coupon code, use limits… | No: only date range and member group |
| Setup required | Enabled by default | Add `SpecificPricingExtension` (see the module README) |

In short: use a **Specific Price** when you want the product to *look* cheaper on the site (a sale
price, member pricing). Use a **Discount** or **Coupon** when the reduction depends on the order or
on a code the customer enters.

A Discount constrained to certain products does **not** change the price shown on those product
pages. It is calculated at checkout and appears as a line item, just like a coupon without a code.

Discounts can be:

 * Temporary price markdowns - restricted by various chosen constraints.
 * Coupon/Voucher codes - unique code entered at checkout, and can also be restricted to the same various constraints.

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
 * Only one coupon code can be entered per order.

## Templates

### Showing discounts in the cart and checkout

Discounts and coupons are applied through the `OrderDiscountModifier`, so they show up in the
standard modifiers loop that SilverShop's cart and checkout templates already use. The row is
only shown when a discount actually applies (`ShowInTable`), and its subtitle lists any coupon
codes used:

```ss
<% loop $Modifiers %>
    <% if $ShowInTable %>
        <tr class="modifierRow $EvenOdd $FirstLast $Classes">
            <td colspan="3">$TableTitle</td>
            <td>$TableValue.Nice</td>
        </tr>
    <% end_if %>
<% end_loop %>
```

Item-level discounts (discounts that apply to "each individual item") are totalled into that same
row, rather than lowering each item's unit price.

### Showing a "was / now" price

"Was / now" prices come from **Specific Prices**, not Discounts. With `SpecificPricingExtension`
applied, products and variations have:

 * Regular price: `$BasePrice` on products, `$Price` on variations
 * Current selling price, after any specific price: `$Price` on products, `$sellingPrice` on variations
 * `$IsReduced`: whether a specific price is currently lowering the price. For a product with
   variations, this is true when any variation is reduced
 * `$TotalReduction`: the amount saved

On a product page:

```ss
<% if $IsReduced %>
    <del>$BasePrice.Nice</del> <strong>$Price.Nice</strong>
    <span class="saving">Save $TotalReduction.Nice</span>
<% else %>
    $Price.Nice
<% end_if %>
```
