<?php

class DiscountedOrderItem extends DataExtension {
	
	static $db = array(
		'Discount' => 'Currency'
	);
	
}