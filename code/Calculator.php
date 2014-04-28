<?php

namespace SS\Shop\Discount;

class Calculator{
	
	protected $order;
	protected $discounts;

	public function __construct(\Order $order, $context = array()) {
		$this->order = $order;
		//get qualifying discounts for this order
		$this->discounts = \Discount::get_matching($this->order, $context);
	}

	/**
	 * Work out the discount for a given order.
	 * @param Order $order
	 * @return double - discount amount
	 */
	public function calculate() {
		$total = 0;

		//TODO: possibly loop over discounts instead

		//items
		$items = $this->createPriceInfoList($this->order->Items());
		foreach($items as $item){
			$this->setItemDiscount($item);
			$discount = $item->getBestDiscount();
			//prevent discounting more than original price
			if($discount > $item->getOriginalPrice()){
				$discount = $item->getOriginalPrice();
			}
			$total += $discount * $item->getQuantity();
		}
		//TODO
			//order-level discounts
			//shipping discounts

		//TODO Other discounting strategies?
			//compounding (all) discounts
			//apply first discount
			//apply last discount

		return $total;
	}

	/**
	 * Update item priceinfo to include discount(s) 
	 * @param PriceInfo $iteminfo [description]
	 */
	protected function setItemDiscount(ItemPriceInfo $iteminfo) {
		foreach($this->discounts as $discount){

			//TODO: check if discount can apply to this item
			
			$amount = $discount->getDiscountValue($iteminfo->getOriginalPrice());
			$adjustment = new Adjustment($amount, $discount);
			$iteminfo->adjustPrice($adjustment);
		}
	}

	protected function createPriceInfoList(\DataList $list) {
		$output = array();
		foreach($list as $item){
			$output[] = new ItemPriceInfo($item);
		}
		return $output;
	}

}