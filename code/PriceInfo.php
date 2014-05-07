<?php

namespace SS\Shop\Discount;

/**
 * Represent a price, along with adjustments made to it.
 */
class PriceInfo{

	protected $originalprice;
	protected $currentprice; //for compounding discounts

	protected $adjustments = array();
	protected $bestadjustment;

	public function __construct($price) {
		$this->currentprice = $this->originalprice = $price;
	}

	public function getOriginalPrice() {
		return $this->originalprice;
	}

	public function getPrice() {
		return $this->currentprice;
	}

	public function adjustPrice(Adjustment $a) {
		$this->currentprice -= $a->getValue();
		$this->setBestAdjustment($a);
		$this->adjustments[] = $a;
	}

	public function getCompoundedDiscount() {
		return $this->originalprice - $this->currentprice;
	}

	public function getBestDiscount() {
		if($this->bestadjustment){
			return $this->bestadjustment->getValue();
		}
		return 0;
	}

	public function getBestAdjustment() {
		return $this->bestadjustment;
	}

	public function getAdjustments() {
		return $this->adjustments;
	}

	/**
	 * Sets the best adjustment, if the passed adjustment
	 * is indeed better.
	 * @param Adjustment $candidate for better adjustment
	 */
	protected function setBestAdjustment(Adjustment $candidate){
		$this->bestadjustment = $this->bestadjustment ?
			Adjustment::better_of($this->bestadjustment, $candidate) : $candidate;
	}

}