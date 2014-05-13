<?php

class SubtotalDiscountAction extends DiscountAction{
	
	protected $subtotal;

	function __construct($subtotal, Discount $discount) {
		parent::__construct($discount);
		$this->subtotal = $subtotal;
		$this->discount = $discount;
	}

	function perform(){
		$amount =  $this->discount->getDiscountValue($this->subtotal);
		if($amount > $this->subtotal){
			$amount = $this->subtotal;
		}
		$amount = $this->limit($amount);

		return $amount;
	}

	function isForItems(){
		return false;
	}

}