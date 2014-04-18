<?php

class OrderDiscountModifier extends OrderModifier{
	
	private static $defaults = array(
		"Type" => "Deductable"
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
		$discounts = OrderDiscount::get_matching($this->Order());

		//apply best discount to each order line?
	}

}