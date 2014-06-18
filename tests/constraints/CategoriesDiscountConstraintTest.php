<?php

use Shop\Discount\Calculator;

class CategoriesDiscountConstraintTest extends SapphireTest{
	
	protected static $fixture_file = array(
		'shop/tests/fixtures/shop.yml'
	);

	public function setUp() {
		parent::setUp();
		ShopTest::setConfiguration();

		Config::inst()->update('OrderCoupon', 'minimum_code_length', null);

		$this->socks = $this->objFromFixture("Product", "socks");
		$this->socks->publish("Stage", "Live");
		$this->tshirt = $this->objFromFixture("Product", "tshirt");
		$this->tshirt->publish("Stage", "Live");
		$this->mp3player = $this->objFromFixture("Product", "mp3player");
		$this->mp3player->publish("Stage", "Live");

		$this->cart = $this->objFromFixture("Order", "cart");
		$this->othercart = $this->objFromFixture("Order", "othercart");
	}

	public function testCategoryDiscount() {
		$coupon = OrderCoupon::create(array(
			"Title" => "5% off clothing",
			"Code" => "CHEAPCLOTHING",
			"Type" => "Percent",
			"Percent" => 0.05
		));
		$coupon->write();
		$coupon->Categories()->add($this->objFromFixture("ProductCategory", "clothing"));
		$context = array("CouponCode" => $coupon->Code);
		$this->assertTrue($coupon->valid($this->cart, $context), "Order contains a t-shirt. ".$coupon->getMessage());
		$this->assertEquals($this->calc($this->cart, $coupon), 0.4, "5% discount for socks in cart");
		$this->assertFalse($coupon->valid($this->othercart, $context), "Order does not contain clothing");
		$this->assertEquals($this->calc($this->othercart, $coupon), 0, "No discount, because no product in category");
	}

	protected function getCalculator($order, $coupon) {
		return new Calculator($order, array("CouponCode" => $coupon->Code));
	}

	protected function calc($order, $coupon) {
		return $this->getCalculator($order, $coupon)->calculate();
	}

}