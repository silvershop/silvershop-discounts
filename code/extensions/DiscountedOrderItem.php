<?php

/**
 * @package shop_discount
 */
class DiscountedOrderItem extends DataExtension {

	private static $db = array(
		'Discount' => 'Currency'
	);

	private static $many_many = array(
		'Discounts' => 'Discount'
	);

	private static $many_many_extraFields = array(
		'Discounts' => array(
			'DiscountAmount' => 'Currency'
		)
	);

	/**
	 * @return int
	 */
	public function getDiscountedProductID() {
        $productKey = OrderItem::config()->buyable_relationship . "ID";

		return $this->owner->{$productKey};
	}

}
