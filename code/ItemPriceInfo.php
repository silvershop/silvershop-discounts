<?php

namespace Shop\Discount;

/**
 * Wrap PriceInfo with order item info
 */
class ItemPriceInfo extends PriceInfo {

	protected $item;
	protected $quantity;

	public function __construct(\OrderItem $item) {
		$this->item = $item;
		$this->quantity = $item->Quantity;

		$originalprice = method_exists($item, "DiscountableAmount") ?
							$item->DiscountableAmount() :
							$item->UnitPrice();

		parent::__construct($originalprice);
	}

	public function getItem(){
		return $this->item;
	}

	public function getQuantity() {
		return $this->quantity;
	}

	public function getOriginalTotal() {
		return $this->originalprice * $this->quantity;
	}

	public function debug() {
		$discount = $this->getBestDiscount();
		$total = $discount * $this->getQuantity();
		$val = "item: ".$this->getItem()->TableTitle();
		$price = $this->getOriginalPrice();
		$val .= " price:$price discount:$discount total:$total.\n";
		
		if($best = $this->getBestAdjustment()) {
			$val .= $this->getBestAdjustment()." ";
			$val .= $this->getBestAdjustment()->getAdjuster()->Title;
		} else {
			$val .= "No adjustments";
		}

		$val .= "\n";
		$val .= implode(",", $this->getAdjustments());
		$val .= "\n\n";

		return $val;
	}

}