<?php

class UseLimitDiscountConstraintTest extends SapphireTest{

	protected static $fixture_file = array(
		'shop/tests/fixtures/shop.yml',
		'shop_discount/tests/fixtures/Discounts.yml'
	);

	public function setUp() {
		parent::setUp();
		ShopTest::setConfiguration();
		$this->cart = $this->objFromFixture("Order", "cart");
	}
	
	public function testUseLimit() {
		$coupon = $this->objFromFixture("OrderCoupon", "used");
		$context = array("CouponCode" => $coupon->Code);
		$this->assertFalse($coupon->validateOrder($this->cart, $context), "Coupon is already used");
		$coupon = $this->objFromFixture("OrderCoupon", "limited");
		$context = array("CouponCode" => $coupon->Code);
		$this->assertTrue($coupon->validateOrder($this->cart, $context), "Coupon has been used, but can continue to be used");
	}

}