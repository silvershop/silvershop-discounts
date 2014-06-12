<?php

namespace Shop\Discount;

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
				$disamt = $action->perform();
				//apply cart-level discounts
				if(!$action->isForItems()){
					$total += $disamt;
					if($disamt && $this->linkdiscounts){
						$discountmodifier->Discounts()->add(
							$discount,
							array(
								'DiscountAmount' => $disamt
							)
						);
					}
				}
			}
			if($discount->Terminating){
				break;
			}
		}
		//add up best item-level discounts
		foreach($items as $iteminfo){
			$discountamount = $iteminfo->getBestDiscount();
			//prevent discounting more than original price
			if($discountamount > $iteminfo->getOriginalTotal()){
				$discountamount = $iteminfo->getOriginalTotal();
			}
			$total += $discountamount;
			//link up selected discounts
			if($this->linkdiscounts){
				//remove any existing linked discounts
				$iteminfo->getItem()->Discounts()->removeAll();
				if($bestadjustment = $iteminfo->getBestAdjustment()){
					$iteminfo->getItem()->Discounts()->add(
						$bestadjustment->getAdjuster(),
						array(
							'DiscountAmount' => $bestadjustment->getValue()
						)
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
		if($discount->ForCart){
			$subtotal = $this->order->SubTotal();
			//TODO: this stuff should probably be moved into the action
			$items = $this->order->Items();
			//reduce subtotal to selected products, if necessary
			$products = $discount->Products();
			if($products->exists()){
				$newsubtotal = 0;
				$items = $items
					->leftJoin("Product_OrderItem", "\"Product_OrderItem\".\"ID\" = \"OrderAttribute\".\"ID\"")
					->filter("ProductID", $products->map('ID', 'ID')->toArray());
				$subtotal = $items->SubTotal();
			}
			
			$actions[] = new \SubtotalDiscountAction($subtotal, $discount);
		}
		//get shipping actions
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