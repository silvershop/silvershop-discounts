<?php

class SubtotalDiscountAction extends Action{
	
	protected $subtotal;
	protected $discount;

	function __construct($subtotal, Discount $discount) {
		$this->subtotal = $subtotal;
		$this->discount = $discount;
	}

	function perform(){
		$amount =  $this->discount->getDiscountValue($this->subtotal);
		if($amount > $this->subtotal){
			$amount = $this->subtotal;
		}
		return $amount;
	}

	function isForItems(){
		return false;
	}

}