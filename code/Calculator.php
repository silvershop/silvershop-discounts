<?php

namespace Shop\Discount;

class Calculator{
	
	protected $order;
	protected $discounts;

	protected $log = array();

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
		//info items wrap OrderItems, see ItemPriceInfo
		$infoitems = $this->createPriceInfoList($this->order->Items());
		$discountmodifier = $this->order->getModifier("OrderDiscountModifier", true);
		
		//clear any existing linked discounts
		$discountmodifier->Discounts()->removeAll();
		
		//loop through discounts to apply
		foreach($this->discounts as $discount){
			foreach($this->getActionsForDiscount($infoitems, $discount) as $action){
				$amount = $action->perform();
				//apply cart-level discounts
				if(!$action->isForItems()){
					$total += $amount;
					if($amount){
						$discountmodifier->Discounts()->add(
							$discount,
							array(
								'DiscountAmount' => $amount
							)
						);
						$this->logDiscountAmount("Cart", $amount, $discount);
					}
				}
			}
		}
		//add up best item-level discounts
		foreach($infoitems as $infoitem){
			$bestadjustment = $infoitem->getBestAdjustment();
			if(!$bestadjustment){
				continue;
			}
			$amount = $bestadjustment->getValue();
			//prevent discounting more than original price
			if($amount > $infoitem->getOriginalTotal()){
				$amount = $infoitem->getOriginalTotal();
			}
			$total += $amount;
			//remove any existing linked discounts
			$infoitem->getItem()->Discounts()->removeAll();
			$infoitem->getItem()->Discounts()->add(
				$bestadjustment->getAdjuster(),
				array(
					'DiscountAmount' => $amount
				)
			);
			$this->logDiscountAmount("Item", $amount, $bestadjustment->getAdjuster());
		}

		return $total;
	}

	/**
	 * Get the actions from a given discount
	 */
	protected function getActionsForDiscount($infoitems, $discount) {
		$actions = array();
		//get item-level actions
		if($discount->ForItems){
			if($discount->Type == "Percent"){
				$actions[] = new \ItemPercentDiscount($infoitems, $discount);
			}else{
				$actions[] = new \ItemFixedDiscount($infoitems, $discount);
			}
		}
		//get cart-level actions
		if($discount->ForCart){
			$actions[] = new \SubtotalDiscountAction(
				$this->getDiscountableAmount($discount),
				$discount
			);
		}
		//get shipping actions
		if($discount->ForShipping && class_exists('ShippingFrameworkModifier') &&
			$shipping = $this->order->getModifier("ShippingFrameworkModifier")
		){
			$actions[] = new \SubtotalDiscountAction($shipping->Amount, $discount);
		}

		return $actions;
	}

	/**
	 * Work out the total discountable amount for a given discount
	 */
	protected function getDiscountableAmount($discount){
		$amount = 0;
		foreach($this->order->Items() as $item){
			if(
				$discount->itemMatchesCategoryCriteria($item, $discount) &&
				$discount->itemMatchesProductCriteria($item, $discount)
			){
				$amount += method_exists($item, "DiscountableAmount") ?
							$item->DiscountableAmount() :
							$item->Total();
			}
		}

		return $amount;
	}

	protected function createPriceInfoList(\DataList $list) {
		$output = array();
		foreach($list as $item){
			$output[] = new ItemPriceInfo($item);
		}
		return $output;
	}

	/**
	 * Store details about discounts for loggging / debubgging
	 * @param  [type]   $level    [description]
	 * @param  [type]   $amount   [description]
	 * @param  Discount $discount [description]
	 * @return [type]             [description]
	 */
	public function logDiscountAmount($level, $amount, \Discount $discount) {
		$this->log[] = array(
			"Level" => $level,
			"Amount" => $amount,
			"Discount" => $discount->Title
		);
	}

	public function getLog(){
		return $this->log;
	}

}