<?php

class OrderDiscountModifier extends OrderModifier{
	
	private static $defaults = array(
		"Type" => "Deductable"
	);

	private static $many_many = array(
		"Discounts" => "Discount"
	);

	private static $many_many_extraFields = array(
		'Discounts' => array(
			'DiscountAmount' => 'Currency'
		)
	);

	private static $singular_name = "Discount";
	private static $plural_name = "Discounts";

	/**
	 * @see OrderModifier::required()
	 */
	public function required() {
		return false;
	}

	public function value($incoming) {
		$this->Amount = $this->getDiscount();
		return $this->Amount;
	}

	public function getDiscount() {
		$context = array();
		if($code = Session::get("cart.couponcode")){
			$context['CouponCode'] = $code;
		}
		$calculator = new Shop\Discount\Calculator($this->Order(), $context);
		return $calculator->calculate();
	}

	public function getCode(){
		return Session::get("cart.couponcode");
	}

}