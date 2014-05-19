<?php

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

}
