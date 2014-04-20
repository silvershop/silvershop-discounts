<?php

/**
 * Order discounts.
 * 
 * This class is needed to clearly distinguish
 * between coupons and generic discounts.
 *
 * @package shop-discounts
 */
class OrderDiscount extends Discount{

	/**
	 * Get the smallest possible list of discounts that can apply
	 * to a given order.
	 * @param  Order  $order order to check against
	 * @return DataList matching discounts
	 */
	public static function get_matching(Order $order) {

		//get as many matching discounts as possible in a single query
		$discounts = self::get()
			->filter("Active", true)
			//amount or percent > 0
			->filterAny(array(
				"Amount:GreaterThan" => 0,
				"Percent:GreaterThan" => 0
			));

		$constraints = Config::inst()->forClass("Discount")->constraints;
		foreach($constraints as $constraint){
			$discounts = singleton($constraint)
							->setOrder($order)
							->filter($discounts);
		}

		//cull remaining invalid discounts programatically
		$validdiscounts = new ArrayList();
		foreach ($discounts as $discount) {
			if($discount->valid($order)){
				$validdiscounts->push($discount);
			}
		}

		return $validdiscounts;
	}
	

}