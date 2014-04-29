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
		$items = $this->createPriceInfoList($this->order->Items());
		//loop through discounts to apply
		foreach($this->discounts as $discount){
			//perform actions
			$action = $discount->Type == "Percent" ?
				new \ItemPercentDiscount($items, $discount) :
				new \ItemFixedDiscount($items, $discount);
			$action->perform();
			if($discount->Terminating){
				break;
			}
		}
		//work out discount
		foreach($items as $item){
			$discount = $item->getBestDiscount();
			//prevent discounting more than original price
			if($discount > $item->getOriginalPrice()){
				$discount = $item->getOriginalPrice();
			}
			$total += $discount * $item->getQuantity();
		}

		return $total;
	}

	protected function createPriceInfoList(\DataList $list) {
		$output = array();
		foreach($list as $item){
			$output[] = new ItemPriceInfo($item);
		}
		return $output;
	}

}