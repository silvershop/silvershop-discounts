<?php

class CheckoutStep_Discount extends CheckoutStep{
	
	function CouponForm(){
		return new CouponForm($this->owner,"CouponForm");
	}
	
}