<?php

namespace SilverShop\Discounts\Checkout\Extensions;

use SilverStripe\Core\Extension;
use SilverShop\Cart\ShoppingCart;
use SilverShop\Discounts\Form\CouponForm;

class CouponFormCheckoutDecorator extends Extension
{
    private static $allowed_actions = [
        'CouponForm'
    ];

    public function CouponForm()
    {
        if ($cart = ShoppingCart::curr()) {
            return new CouponForm($this->owner, CouponForm::class, $cart);
        }
    }
}
