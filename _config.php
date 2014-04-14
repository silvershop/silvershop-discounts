<?php

define('ECOMMERCE_COUPON_DIR', 'shop_discount');
Object::add_extension("CheckoutPage_Controller", "CouponFormCheckoutDecorator");
DataObject::add_extension("Product_OrderItem", "DiscountedOrderItem");

//SS_Report::register('ReportAdmin', 'CouponReport',3);
