<?php

/**
 * Discount constraint that restricts to specific items.
 */
abstract class ItemDiscountConstraint extends Discountconstraint{

	/**
	 * Checks that an item can be discounted for configured
	 * constraints.
	 *
	 * If any constraint check fails, the entire function returns false;
	 */
	public static function match(OrderItem $item, Discount $discount){
		$singletons = array();
		$itemconstraints = ClassInfo::subclassesFor("ItemDiscountConstraint");
		array_shift($itemconstraints); //exclude abstract base class
		$configuredconstraints = Config::inst()->forClass("Discount")->constraints;
		//get only the configured item constraints
		$classes = array_intersect($itemconstraints, $configuredconstraints);
		foreach($classes as $constraint){
			if(!singleton($constraint)->itemMatchesCriteria($item, $discount)){
				return false;
			}
		}

		return true;
	}

	/**
	 * Returns true if the given item sits within this constraint.
	 *
	 * If there is no constraint set, then it should return true.
	 * 
	 * @param  OrderItem $item
	 * @param  Discount  $discount 
	 * @return boolean
	 */
	abstract public function itemMatchesCriteria(OrderItem $item, Discount $discount);

	/**
	 * Check if at least one item in cart matches this criteria.
	 */
	public function itemsInCart(Discount $discount){
		$items = $this->order->Items();
		foreach($items as $item){
			if($this->itemMatchesCriteria($item, $discount)){
				return true;
			}
		}
		return false;
	}

}