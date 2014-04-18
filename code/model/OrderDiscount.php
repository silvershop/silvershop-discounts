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

		$now = date('Y-m-d H:i:s');
		

		$productids = $order->Items()
							->map('ProductID', 'ProductID')
							->toArray();
		
		//$categoryids
		
		$member = Member::currentUser();
		$groupids = $member->Groups()
							->map('ID', 'ID')
							->toArray();

		//get as many matching discounts as possible in a single query
		$discounts = self::get()
			->filter("Active", true)
			//amount or percent > 0
			->filterAny(array(
				"Amount:GreaterThan" => 0,
				"Percent:GreaterThan" => 0
			))
			//order value
			->filterAny(array(
				"MinOrderValue" => 0,
				"MinOrderValue:LessThan" => $order->SubTotal()
			))
			//start date is null or < today
			->where(
				//to bad ORM filtering for NULL doesn't work :(
				"(\"Discount\".\"StartDate\" IS NULL) OR (\"Discount\".\"StartDate\" < '$now')"
			)
			//end date is null or > today
			->where(
				//to bad ROM filtering for NULL doesn't work :(
				"(\"Discount\".\"EndDate\" IS NULL) OR (\"Discount\".\"EndDate\" > '$now')"
			)
			//member is in group (or group doesn't apply)
			->filterAny(array(
				"GroupID" => $groupids,
				"GroupID" => 0
			));
			//->leftJoin("")
		
			//products/categories match
			//zone matches

		Debug::show($discounts->sql());

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