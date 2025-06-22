<?php

namespace SilverShop\Discounts\Extensions;

use SilverStripe\Core\Extension;
use SilverShop\Cart\ShoppingCart;
use SilverShop\Discounts\Form\CouponForm;

/**
 * @extends Extension<static>
 */
class CouponFormCheckoutDecorator extends Extension
{
    private static array $allowed_actions = [
        'CouponForm'
    ];

    public function CouponForm(): ?CouponForm
    {
        if ($cart = ShoppingCart::curr()) {
            return CouponForm::create($this->owner, 'CouponForm', $cart);
        }
        return null;
    }
}
