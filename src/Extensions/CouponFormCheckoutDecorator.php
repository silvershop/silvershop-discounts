<?php

namespace SilverShop\Discounts\Extensions;

use SilverShop\Model\Order;
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
        if (($cart = ShoppingCart::curr()) instanceof Order) {
            return CouponForm::create($this->owner, 'CouponForm', $cart);
        }

        return null;
    }
}
