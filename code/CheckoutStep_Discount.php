<?php

class CheckoutStep_Discount extends CheckoutStep{

	public function CouponForm() {
		return new CouponForm($this->owner, "CouponForm");
	}

}
