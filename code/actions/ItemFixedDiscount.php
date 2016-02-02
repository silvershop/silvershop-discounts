<?php

use Shop\Discount\Adjustment;

/**
 * @package shop_discount
 */
class ItemFixedDiscount extends ItemDiscountAction {

	function perform() {
		foreach($this->infoitems as $info) {
			if(!$this->itemQualifies($info)) {
				continue;
			}

			$amount = $this->discount->getDiscountValue($info->getOriginalPrice());
			$amount *= $info->getQuantity();
			$amount = $this->limit($amount);

            $info->adjustPrice(new Adjustment($amount, $this->discount));

			//break the loop if there is no discountable amount left
			if(!$this->hasRemainingDiscount()) {
				break;
			}
		}
	}
}
