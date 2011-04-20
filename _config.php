<?php

define('ECOMMERCE_COUPON_DIR','ecommerce_coupon');

/**
 * developed by www.sunnysideup.co.nz
 * author: Nicolaas - modules [at] sunnysideup.co.nz
**/
Director::addRules(50, array(
	OrderCouponModifier_Controller::get_url_segment().'//$Action/$ID/$OtherID' => 'OrderCouponModifier_Controller'
));


//copy the lines between the START AND END line to your /mysite/_config.php file and choose the right settings
//===================---------------- START ecommerce_discount_coupon MODULE ----------------===================
//NOTE: add http://svn.gpmd.net/svn/open/multiselectfield/tags/0.2/ for nicer interface
//DiscountCoupon::set_form_header("Delivery Option (REQUIRED)"); //doesn't work
Order::set_modifiers(array('OrderCouponModifier'));
//===================---------------- END ecommerce_discount_coupon MODULE ----------------===================
