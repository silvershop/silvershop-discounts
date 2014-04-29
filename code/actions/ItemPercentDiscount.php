<?php

use SS\Shop\Discount\Adjustment;

class ItemPercentDiscount extends ItemDiscountAction{
	
	function perform(){
		foreach($this->infoitems as $info){
			if($this->itemQualifies($info)){
				$amount = $this->discount->getDiscountValue($info->getOriginalPrice());
				$info->adjustPrice(new Adjustment($amount, $this->discount));
			}
		}
	}
	
}