<?php
/**
 * Tests coupons
 * @package shop-discount
 */

use Shop\Discount\Calculator;

class OrderCouponTest extends SapphireTest{

	protected static $fixture_file = array(
		'shop/tests/fixtures/shop.yml',
		'shop_discount/tests/fixtures/Discounts.yml',
		'shop/tests/fixtures/Zones.yml',
		'shop/tests/fixtures/Addresses.yml'
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

		$coupon = new OrderCoupon(array('Code' => '1234567'));
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
		$coupon = $this->objFromFixture('OrderCoupon', '40percentoff');
		$context = array("CouponCode" => $coupon->Code);
		$this->assertTrue($coupon->valid($this->cart, $context), $coupon->getMessage());
		$this->assertEquals(4, $coupon->getDiscountValue(10), "40% off value");
		$this->assertEquals(200, $this->calc($this->placedorder, $coupon), "40% off order");
	}

	public function testAmount() {
		$coupon = $this->objFromFixture('OrderCoupon', '10dollarsoff');
		$context = array("CouponCode" => $coupon->Code);
		$this->assertTrue($coupon->valid($this->cart, $context), $coupon->getMessage());
		$this->assertEquals($coupon->getDiscountValue(1000), 10, "$10 off fixed value");
		$this->assertEquals(60, $this->calc($this->placedorder, $coupon), "$10 off each item: $60 total");
		//TODO: test amount that is greater than item value
	}

	public function testProductsDiscount() {
		$coupon = $this->objFromFixture("OrderCoupon", "products20percentoff");
		$context = array("CouponCode" => $coupon->Code);
		//add product to coupon product list
		$coupon->Products()->add($this->objFromFixture("Product", "tshirt"));
		$this->assertEquals($this->calc($this->placedorder, $coupon), 20);
		//add another product to coupon product list
		$coupon->Products()->add($this->objFromFixture("Product", "mp3player"));
		$this->assertEquals($this->calc($this->placedorder, $coupon), 100);
	}

	public function testCategoryDiscount() {
		$coupon = $this->objFromFixture("OrderCoupon", "clothing5percent");
		$coupon->Categories()->add($this->objFromFixture("ProductCategory", "clothing"));
		$context = array("CouponCode" => $coupon->Code);
		$this->assertTrue($coupon->valid($this->cart, $context), "Order contains a t-shirt. ".$coupon->getMessage());
		$this->assertEquals($this->calc($this->cart, $coupon), 0.4, "5% discount for socks in cart");
		$this->assertFalse($coupon->valid($this->othercart, $context), "Order does not contain clothing");
		$this->assertEquals($this->calc($this->othercart, $coupon), 0, "No discount, because no product in category");
	}

	public function testZoneDiscount() {
		$coupon = $this->objFromFixture('OrderCoupon', 'zoned');
		$coupon->Zones()->add($this->objFromFixture('Zone', 'transtasman'));
		$coupon->Zones()->add($this->objFromFixture('Zone', 'special'));
		$address = $this->objFromFixture("Address", 'bukhp193eq');
		$context = array("CouponCode" => $coupon->Code);
		$this->cart->ShippingAddressID = $address->ID; //set address
		$this->assertFalse($coupon->valid($this->cart, $context), "check order is out of zone");
		$address = $this->objFromFixture("Address", 'sau5024');
		$this->othercart->ShippingAddressID = $address->ID; //set address
		$valid = $coupon->valid($this->othercart, $context);
		$this->assertTrue($valid, "check order is in zone");
	}

	public function testMinOrderValue() {
		$coupon = $this->objFromFixture("OrderCoupon", "ordersabove200");
		$context = array("CouponCode" => $coupon->Code);
		$this->assertFalse($coupon->valid($this->cart, $context), "$8 order isn't enough");
		$this->assertTrue($coupon->valid($this->placedorder, $context), "$500 order is enough");
	}

	public function testUseLimit() {
		$coupon = $this->objFromFixture("OrderCoupon", "used");
		$context = array("CouponCode" => $coupon->Code);
		$this->assertFalse($coupon->valid($this->cart, $context), "Coupon is already used");
		$coupon = $this->objFromFixture("OrderCoupon", "limited");
		$context = array("CouponCode" => $coupon->Code);
		$this->assertTrue($coupon->valid($this->cart, $context), "Coupon has been used, but can continue to be used");
	}

	public function testMemberGroup() {
		$group = $this->objFromFixture("Group", "resellers");
		$coupon = $this->objFromFixture("OrderCoupon", "grouped");
		$coupon->GroupID = $group->ID;
		$coupon->write();
		$context = array("CouponCode" => $coupon->Code);
		$this->assertFalse($coupon->valid($this->cart, $context), "Invalid for memberless order");
		$context = array(
			"CouponCode" => $coupon->Code,
			"Member" => $this->objFromFixture("Member", "bobjones")
		);
		$this->assertTrue($coupon->valid($this->othercart, $context), "Valid because member is in resellers group");
	}

	public function testInactiveCoupon() {
		$inactivecoupon = $this->objFromFixture('OrderCoupon', 'inactivecoupon');
		$context = array("CouponCode" => $inactivecoupon->Code);
		$this->assertFalse($inactivecoupon->valid($this->cart, $context), "Coupon is not set to active");
	}

	public function testDates() {
		$unreleasedcoupon = $this->objFromFixture('OrderCoupon', 'unreleasedcoupon');
		$context = array("CouponCode" => $unreleasedcoupon->Code);
		$this->assertFalse($unreleasedcoupon->valid($this->cart, $context), "Coupon is un released (start date has not arrived)");
		$expiredcoupon = $this->objFromFixture('OrderCoupon', 'expiredcoupon');
		$context = array("CouponCode" => $expiredcoupon->Code);
		$this->assertFalse($expiredcoupon->valid($this->cart, $context), "Coupon has expired (end date has passed)");
	}

	public function testFreeShipping() {
		if (!class_exists('ShippingFrameworkModifier')) return;
		$coupon = $this->objFromFixture("OrderCoupon", "freeshipping");
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
		$coupon = $this->objFromFixture("OrderCoupon", "10dollarsoffshipping");
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
		$coupon = $this->objFromFixture("OrderCoupon", "30percentoffshipping");
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
		$coupon = $this->objFromFixture("OrderCoupon", "shippinganditems");
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
