<?php

namespace SS\Shop\Discount;

class Calculator{
	
	protected $order;
	protected $discounts;
	protected $linkdiscounts = true;

	public function __construct(\Order $order, $context = array()) {
		$this->order = $order;
		//get qualifying discounts for this order
		$this->discounts = \Discount::get_matching($this->order, $context);
	}

	/**
	 * Specify whether the calculator should link discounts to order/items
	 * during calculation.
	 * @param boolean $dolink
	 */
	public function setLinkDiscounts($dolink = true) {
		$this->linkdiscounts = $dolink;
	}

	/**
	 * Work out the discount for a given order.
	 * @param Order $order
	 * @return double - discount amount
	 */
	public function calculate() {
		$total = 0;
		$items = $this->createPriceInfoList($this->order->Items());
		$discountmodifier = $this->order->getModifier("OrderDiscountModifier", true);
		if($this->linkdiscounts){
			//clear any existing linked discounts
			$discountmodifier->Discounts()->removeAll();
		}
		//loop through discounts to apply
		foreach($this->discounts as $discount){
			foreach($this->getActionsForDiscount($items, $discount) as $action){
				//perform item-specific discount actions
				if($action->isForItems()){
					$action->perform(); //iteminfo objects will be updated
				}
				//perform cart-level discount actions
				else{
					//add result to discount total
					$total += $action->perform();
					if($this->linkdiscounts){
						$discountmodifier->Discounts()->add($discount);
					}
				}
			}
			if($discount->Terminating){
				break;
			}
		}
		//add up item discounts
		foreach($items as $iteminfo){
			$discountamount = $iteminfo->getBestDiscount();
			//prevent discounting more than original price
			if($discountamount > $iteminfo->getOriginalPrice()){
				$discountamount = $iteminfo->getOriginalPrice();
			}
			$total += $discountamount * $iteminfo->getQuantity();
			//link up selected discounts
			if($this->linkdiscounts){
				//remove any existing linked discounts
				$iteminfo->getItem()->Discounts()->removeAll();
				if($bestadjustment = $iteminfo->getBestAdjustment()){
					$iteminfo->getItem()->Discounts()->add(
						$bestadjustment->getAdjuster()
					);
				}
			}
		}

		return $total;
	}

	protected function getActionsForDiscount($items, $discount) {
		$actions = array();
		//get item-level actions
		if($discount->ForItems){
			if($discount->Type == "Percent"){
				$actions[] = new \ItemPercentDiscount($items, $discount);
			}else{
				$actions[] = new \ItemFixedDiscount($items, $discount);
			}
		}
		//get cart-level actions
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