<?php

define('ECOMMERCE_COUPON_DIR','shop_discount');
Object::add_extension("CheckoutPage_Controller", "CouponFormCheckoutDecorator");
DataObject::add_extension("Product_OrderItem", "DiscountedOrderItem");
DataObject::add_extension("CheckoutPage_Controller", "Product_OrderItem_Coupon");

SS_Report::register('ReportAdmin', 'CouponReport',3);