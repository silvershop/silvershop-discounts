<?php

use Shop\Discount\ItemPriceInfo;

abstract class ItemDiscountAction extends DiscountAction{
	
	//allow defining items to discount
	//otherwise apply to rule-matching items
	protected $infoitems;

	function __construct(array $infoitems, Discount $discount){
		parent::__construct($discount);
		$this->infoitems = $infoitems;
	}

	function isForItems(){
		return true;
	}

	/**
	 * Checks if the given item qualifies for a discount.
	 * @return boolean
	 */
	protected function itemQualifies(ItemPriceInfo $info){
		return ItemDiscountConstraint::match($info->getItem(), $this->discount);
	}

}