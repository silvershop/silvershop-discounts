<?php

/**
 * Adds relationship to order item
 */
class Product_OrderItem_Coupon extends DataExtension {

	private static $has_one = array(
		'Coupon' => 'OrderCoupon'
	);

}
