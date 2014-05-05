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
			foreach($this->getActionsForDiscount($items, $discount) as $action){
				if($result = $action->perform()){
					$total += $result;
				}
			}
			if($discount->Terminating){
				break;
			}
		}
		//add up discounts
		foreach($items as $item){
			$discount = $item->getBestDiscount();
			//prevent discounting more than original price
			if($discount > $item->getOriginalPrice()){
				$discount = $item->getOriginalPrice();
			}
			$total += $discount * $item->getQuantity();
		}

		//TODO: cart-level discounts

		return $total;
	}

	protected function getActionsForDiscount($items, $discount) {
		$actions = array();
		//perform actions
		if($discount->ForItems){
			if($discount->Type == "Percent"){
				$actions[] = new \ItemPercentDiscount($items, $discount);
			}else{
				$actions[] = new \ItemFixedDiscount($items, $discount);
			}
		}

		//TODO: cart-level discounts

		if($discount->ForShipping && class_exists('ShippingFrameworkModifier') &&
			$shipping = $this->order->getModifier("ShippingFrameworkModifier")
		){
			$actions[] = new \SubtotalDiscountAction($shipping->Amount, $discount);
		}

		return $actions;
	}

	protected function createPriceInfoList(\DataList $list) {
		$output = array();
		foreach($list as $item){
			$output[] = new ItemPriceInfo($item);
		}
		return $output;
	}

}