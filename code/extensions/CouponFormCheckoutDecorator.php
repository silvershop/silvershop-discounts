<?php

/**
 * Decorates checkout with CouponForm
 * @package shop_discount
 */
class CouponFormCheckoutDecorator extends Extension{

	public static $allowed_actions = array(
		'CouponForm'
	);

	public function CouponForm() {
		if($cart = ShoppingCart::curr()){
			return new CouponForm($this->owner, "CouponForm", $cart);
		}
	}

}