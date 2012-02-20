<?php

define('ECOMMERCE_COUPON_DIR','shop_discount');
Director::addRules(50, array(
	OrderCouponModifier_Controller::get_url_segment().'//$Action/$ID/$OtherID' => 'OrderCouponModifier_Controller'
));

Order::set_modifiers(array('OrderCouponModifier'));
