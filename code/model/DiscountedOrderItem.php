<?php

class DiscountedOrderItem extends DataObjectDecorator{
	
	static $db = array(
		'Discount' => 'Currency'
	);
	
}