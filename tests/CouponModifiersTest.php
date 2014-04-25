<?php

class CouponModifiersTest extends SapphireTest{

	protected static $fixture_file = array(
		'shop_discount/tests/fixtures/OrderCoupons.yml',
		'shop/tests/fixtures/Cart.yml'
	);

	public function setUp() {
		parent::setUp();
		ShopTest::setConfiguration();
		$this->laptop = $this->objFromFixture('Product', 'laptop');
		$this->laptop->publish('Stage', 'Live');
		$this->bag = $this->objFromFixture('Product', 'bag');
		$this->bag->publish('Stage', 'Live');
		$this->battery = $this->objFromFixture('Product', 'battery');
		$this->battery->publish('Stage', 'Live');
	}

	public function testPlaceDiscountedOrder() {
		Order::config()->modifiers = array(
			'OrderCouponModifier'
		);
		$order = $this->objFromFixture("Order", "cart1");
		$order->calculate();
		$this->assertEquals($order->GrandTotal(), 2000, "Price without coupon is $2000");
		$coupon = $this->objFromFixture("OrderCoupon", "40percentoff");
		$valid = $coupon->valid($order);
		$this->assertTrue($valid, 'valid coupon: '.$coupon->getMessage());
		$coupon->applyToOrder($order);
		$this->assertEquals($order->GrandTotal(), 1200, "Half price");
	}

	//TODO: test product discounts

}
