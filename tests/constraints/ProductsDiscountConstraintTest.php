<?php

use Shop\Discount\Calculator;

class ProductsDiscountConstraintTest extends SapphireTest{

	protected static $fixture_file = array(
		'shop/tests/fixtures/shop.yml'
	);

	public function setUp() {
		parent::setUp();
		ShopTest::setConfiguration();
		$this->cart = $this->objFromFixture("Order", "cart");
		$this->placedorder = $this->objFromFixture("Order", "unpaid");
	}
	
	public function testProducts() {
		$discount = OrderDiscount::create(array(
			"Title" => "20% off each selected products",
			"Percent" => 0.2
		));
		$discount->write();
		$discount->Products()->add($this->objFromFixture("Product", "tshirt"));
		$this->assertFalse($discount->valid($this->cart));
		//no products match
		$this->assertDOSEquals(array(), OrderDiscount::get_matching($this->cart));
		//add product discount list
		$discount->Products()->add($this->objFromFixture("Product", "tshirt"));
		$this->assertFalse($discount->valid($this->cart));
		//no products match
		$this->assertDOSEquals(array(), OrderDiscount::get_matching($this->cart));
	}

	public function testProductsCoupon() {
		$coupon = OrderCoupon::create(array(
			"Title" => "Selected products",
			"Code" => "PRODUCTS",
			"Percent" => 0.2
		));
		$coupon->write();
		$coupon->Products()->add($this->objFromFixture("Product", "tshirt"));

		$calculator = new Calculator($this->placedorder, array("CouponCode" => $coupon->Code));
		$this->assertEquals($calculator->calculate(), 20);
		//add another product to coupon product list
		$coupon->Products()->add($this->objFromFixture("Product", "mp3player"));
		$this->assertEquals($calculator->calculate(), 100);
	}

}