<?php

class CouponModifiersTest extends SapphireTest{
	
	static $fixture_file = array(
		'shop_discount/tests/OrderCoupons.yml',
		'shop/tests/fixtures/Cart.yml'
	);
	
	function setUp(){
		parent::setUp();
		ShopTest::setConfiguration();
		$this->laptop = $this->objFromFixture('Product', 'laptop');
		$this->laptop->publish('Stage','Live');
		$this->bag = $this->objFromFixture('Product', 'bag');
		$this->bag->publish('Stage','Live');
		$this->battery = $this->objFromFixture('Product', 'battery');
		$this->battery->publish('Stage','Live');
	}
	
	function testPlaceDiscountedOrder(){
		Order::set_modifiers(array(
			'OrderCouponModifier'
		));
		$order = $this->objFromFixture("Order", "cart1");
		$order->calculate();
		$this->assertEquals($order->GrandTotal(), 2000, "Price without coupon is $2000");
		$coupon = $this->objFromFixture("OrderCoupon", "validcoupon");
		$valid = $coupon->valid($order);
		$this->assertTrue($valid,'Check the coupon is indeed valid');
		$coupon->applyToOrder($order);
		$this->assertEquals($order->GrandTotal(), 998, "Half price & -$2 coupon");
	}
	
	//TODO: test product discounts
	
}