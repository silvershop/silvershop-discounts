<?php

abstract class DiscountAction extends Action{
	
	protected $discount;

	//used for keeping dotal discount within MaxAmount
	protected $remaining;
	protected $limited;

	function __construct(Discount $discount){
		$this->discount = $discount;
		$this->remaining = (float)$this->discount->MaxAmount;
		$this->limited = (bool)$this->remaining;
	}

	/**
	 * Limit an amount to be within maximum allowable discount,
	 * and update the total remaining discountable amount;
	 * 
	 * @return float new amount
	 */
	protected function limit($amount){
		if($this->limited){
			if($amount > $this->remaining){
				$amount = $this->remaining;
			}
			$this->remaining -= $amount > $this->remaining ? $this->remaining : $amount;
		}
		return $amount;
	}

	/**
	 * Check if there is any further allowable amount to be discounted
	 * @return boolean
	 */
	protected function hasRemainingDiscount(){
		return !$this->limited || $this->remaining > 0;
	}


	public function reduceRemaining($amount){
		if($this->remaining){
			$this->remaining -= $amount;
		}
	}

}