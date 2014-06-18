<?php
/**
 * Tests coupons
 * @package shop-discount
 */

use Shop\Discount\Calculator;

class OrderCouponTest extends SapphireTest{

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

		$this->placedorder = $this->objFromFixture("Order", "unpaid");
		$this->cart = $this->objFromFixture("Order", "cart");
		$this->othercart = $this->objFromFixture("Order", "othercart");
	}

	public function testMinimumLengthCode() {
		Config::inst()->update('OrderCoupon', 'minimum_code_length', 8);
		$coupon = new OrderCoupon();
		$coupon->Code = '1234567';
		$result = $coupon->validate();
		$this->assertContains('INVALIDMINLENGTH', $result->codeList());

		$coupon = new OrderCoupon();
		$result = $coupon->validate();
		$this->assertNotContains('INVALIDMINLENGTH', $result->codeList(), 'Leaving the Code field generates a code');

		$coupon = new OrderCoupon(array('Code' => '12345678'));
		$result = $coupon->validate();
		$this->assertNotContains('INVALIDMINLENGTH', $result->codeList());

		Config::inst()->update('OrderCoupon', 'minimum_code_length', null);

		$coupon = new OrderCoupon(array('Code' => '1'));
		$result = $coupon->validate();
		$this->assertNotContains('INVALIDMINLENGTH', $result->codeList());
	}

	public function testPercent() {
		$coupon = OrderCoupon::create(array(
			"Title" => "40% off each item",
			"Code" => "5B97AA9D75",
			"Type" => "Percent",
			"Percent" => 0.40,
			"StartDate" => "2000-01-01 12:00:00",
			"EndDate" => "2200-01-01 12:00:00"
		));
		$coupon->write();
		$context = array("CouponCode" => $coupon->Code);
		$this->assertTrue($coupon->valid($this->cart, $context), $coupon->getMessage());
		$this->assertEquals(4, $coupon->getDiscountValue(10), "40% off value");
		$this->assertEquals(200, $this->calc($this->placedorder, $coupon), "40% off order");
	}
	
	public function testAmount() {
		$coupon = OrderCoupon::create(array(
			"Title" => "$10 off each item",
			"Code" => "TENDOLLARSOFF",
			"Type" => "Amount",
			"Amount" => 10,
			"Active" => 1
		));
		$coupon->write();
		
		$context = array("CouponCode" => $coupon->Code);
		$this->assertTrue($coupon->valid($this->cart, $context), $coupon->getMessage());
		$this->assertEquals($coupon->getDiscountValue(1000), 10, "$10 off fixed value");
		$this->assertEquals(60, $this->calc($this->placedorder, $coupon), "$10 off each item: $60 total");
		//TODO: test amount that is greater than item value
	}

	public function testInactiveCoupon() {
		$inactivecoupon = OrderCoupon::create(array(
 			"Title" => "Not active",
 			"Code" => "EE891574D6",
 			"Type" => "Amount",
 			"Amount" => 10,
 			"Active" => 0
		));
		$inactivecoupon->write();
		$context = array("CouponCode" => $inactivecoupon->Code);
		$this->assertFalse($inactivecoupon->valid($this->cart, $context), "Coupon is not set to active");
	}

	public function testFreeShipping() {
		if (!class_exists('ShippingFrameworkModifier')) return;
		$coupon = OrderCoupon::create(array(
			"Title" => "Free shipping",
			"Code" => "FREESHIPPING",
			"ForShipping" => 1,
			"ForItems" => 0,
			"Percent" => 1
		));
		$coupon->write();

		$order = $this->cart;
		$shipping = new ShippingFrameworkModifier(array(
			'Amount' => 12.34,
			'OrderID' => $order->ID
		));
		$shipping->write();
		$order->Modifiers()->add($shipping);
		$context = array("CouponCode" => $coupon->Code);
		$this->assertTrue($coupon->valid($order, $context), "Free shipping coupon is valid");
		$this->assertEquals($this->calc($order, $coupon), 12.34, "Shipping discount");
	}

	public function testShippingAmountDiscount() {
		if (!class_exists('ShippingFrameworkModifier')) return;
		$order = $this->cart;
		$coupon = OrderCoupon::create(array(
			"Title" => "Free shipping",
			"Code" => "FREESHIPPING",
			"ForShipping" => 1,
			"ForItems" => 0,
			"Percent" => 1
		));
		$coupon->write();

		$shipping = new ShippingFrameworkModifier(array(
			'Amount' => 30,
			'OrderID' => $order->ID
		));
		$shipping->write();
		$order->Modifiers()->add($shipping);
		$context = array("CouponCode" => $coupon->Code);
		$this->assertTrue($coupon->valid($order, $context), "10 dollars off shipping discount is valid");
		$this->assertEquals($this->calc($order, $coupon), 10, "$10 discount");
	}

	public function testShippingPercentDiscount() {
		if (!class_exists('ShippingFrameworkModifier')) return;
		$order = $this->othercart;
		$coupon = OrderCoupon::create(array(
			"Title" => "Save 30% off shipping",
			"Code" => "30PERCENTSHIPPING",
			"Percent" => 0.3,
			"ForShipping" => 1,
			"ForItems" => 0
		));
		$coupon->write();

		$shipping = new ShippingFrameworkModifier(array(
			'Amount' => 10,
			'OrderID' => $order->ID
		));
		$shipping->write();
		$order->Modifiers()->add($shipping);
		$context = array("CouponCode" => $coupon->Code);
		$this->assertTrue($coupon->valid($order, $context), "30% off shipping discount is valid");
		$this->assertEquals($this->calc($order, $coupon), 3, "30% discount on $10 of shipping");
	}

	public function testShipingAndItems() {
		if (!class_exists('ShippingFrameworkModifier')) return;
		//test an edge case, where a discount is for orders, and shipping.
		$order = $this->othercart; //$200
		$coupon = OrderCoupon::create(array(
			"Title" => "Save $20 on order",
			"Code" => "SAVE20",
			"Amount" => 20,
			"ForShipping" => 1,
			"ForItems" => 1
		));
		$coupon->write();

		$shipping = new ShippingFrameworkModifier(array(
			'Amount' => 30,
			'OrderID' => $order->ID
		));
		$shipping->write();
		$order->Modifiers()->add($shipping);
		$context = array("CouponCode" => $coupon->Code);
		$this->assertTrue($coupon->valid($order, $context), "Shipping and items coupon is valid");
		$this->assertEquals($this->calc($order, $coupon), 40, "$20 discount");

		//TODO:  test when subtotal & shipping are both < 20
	}

	protected function getCalculator($order, $coupon) {
		return new Calculator($order, array("CouponCode" => $coupon->Code));
	}

	protected function calc($order, $coupon) {
		return $this->getCalculator($order, $coupon)->calculate();
	}

}
