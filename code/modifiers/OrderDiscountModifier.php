<?php

class OrderDiscountModifier extends OrderModifier{
	
	private static $defaults = array(
		"Type" => "Deductable"
	);

	private static $many_many = array(
		"Discounts" => "Discount"
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

	function getDiscount() {
		$calculator = new SS\Shop\Discount\Calculator($this->Order());
		return $calculator->calculate();
	}

}