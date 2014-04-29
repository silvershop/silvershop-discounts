<?php

use SS\Shop\Discount\ItemPriceInfo;

abstract class ItemDiscountAction extends Action{
	
	//allow defining items to discount
	//otherwise apply to rule-matching items
	protected $infoitems;
	protected $discount;

	function __construct(array $infoitems, Discount $discount){
		$this->infoitems = $infoitems;
		$this->discount = $discount;
	}

	/**
	 * Checks if the given item qualifies for a discount.
	 * @return boolean
	 */
	protected function itemQualifies(ItemPriceInfo $info){
		return $this->discount->itemMatchesProductCriteria($info->getItem(), $this->discount) &&
			$this->discount->itemMatchesCategoryCriteria($info->getItem(), $this->discount);
	}

}