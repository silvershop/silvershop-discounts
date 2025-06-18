<?php

namespace SilverShop\Discounts\Extensions;

use SilverStripe\Core\Extension;
use SilverShop\Cart\ShoppingCart;
use SilverShop\Discounts\Form\CouponForm;

class CouponFormCheckoutDecorator extends Extension
{
    private static array $allowed_actions = [
        'CouponForm'
    ];

    public function CouponForm(): ?CouponForm
    {
        if ($cart = ShoppingCart::curr()) {
            return new CouponForm($this->owner, 'CouponForm', $cart);
        }
        return null;
    }
}
