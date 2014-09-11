<?php

use Shop\Discount\Calculator;
use Shop\Discount\Adjustment;
use Shop\Discount\PriceInfo;

class CalculatorTest extends SapphireTest{
	
	protected static $fixture_file = array(
		'shop_discount/tests/fixtures/Discounts.yml',
		'shop/tests/fixtures/shop.yml'

	);

	function setUp(){
		parent::setUp();
		ShopTest::setConfiguration();

		Order::config()->modifiers = array(
			"OrderDiscountModifier"
		);

		$this->socks = $this->objFromFixture("Product", "socks");
		$this->socks->publish("Stage", "Live");
		$this->tshirt = $this->objFromFixture("Product", "tshirt");
		$this->tshirt->publish("Stage", "Live");
		$this->mp3player = $this->objFromFixture("Product", "mp3player");
		$this->mp3player->publish("Stage", "Live");

		$this->cart = $this->objFromFixture("Order", "cart");
		$this->othercart = $this->objFromFixture("Order", "othercart");
		$this->megacart = $this->objFromFixture("Order", "megacart");
		$this->emptycart = $this->objFromFixture("Order", "emptycart");
		$this->modifiedcart = $this->objFromFixture("Order", "modifiedcart");
	}

	function testAdjustment(){
		$adjustment1 = new Adjustment(10, null);
		$adjustment2 = new Adjustment(5, null);
		$this->assertEquals(10, $adjustment1->getValue());
		$this->assertEquals($adjustment1, Adjustment::better_of($adjustment1, $adjustment2));
	}

	function testPriceInfo(){
		$i = new PriceInfo(20);
		$this->assertEquals(20, $i->getPrice());
		$this->assertEquals(20, $i->getOriginalPrice());
		$this->assertEquals(0, $i->getCompoundedDiscount());
		$this->assertEquals(0, $i->getBestDiscount());
		$this->assertEquals(array(), $i->getAdjustments());

		$i->adjustPrice($a1 = new Adjustment(1, "a"));
		$i->adjustPrice($a2 = new Adjustment(5, "b"));
		$i->adjustPrice($a3 = new Adjustment(2, "c"));

		$this->assertEquals(12, $i->getPrice());
		$this->assertEquals(20, $i->getOriginalPrice());
		$this->assertEquals(8, $i->getCompoundedDiscount());
		$this->assertEquals(5, $i->getBestDiscount());
		$this->assertEquals(array($a1,$a2,$a3), $i->getAdjustments());
	}

	function testBasicItemDiscount() {
		//activate discounts
		$discount = OrderDiscount::create(array(
			"Title" => "10% off",
			"Type" => "Percent",
			"Percent" => 0.1
		));
		$discount->write();
		//check that discount works as expected
		$this->assertEquals(1, $discount->getDiscountValue(10), "10% of 10 is 1");
		//check that discount matches order
		$matching = Discount::get_matching($this->cart);
		$this->assertDOSEquals(array(
			array("Title" => "10% off")
		), $matching);
		//check valid
		$valid = $discount->validateOrder($this->cart);
		$this->assertTrue($valid, "discount is valid");
		//check calculator
		$calculator = new Calculator($this->cart);
		$this->assertEquals(0.8, $calculator->calculate(), "10% of $8");
	}

	function testZeroOrderDiscount() {
		OrderDiscount::create(array(
			"Title" => "Everything is free!",
			"Type" => "Percent",
			"Percent" => 1,
			"ForItems" => 1,
			"ForCart" => 1,
			"ForShipping" => 1
		))->write();
		$this->markTestIncomplete("Add assertions");
	}

	function testItemLevelPercentAndAmountDiscounts() {
		OrderDiscount::create(array(
			"Title" => "10% off",
			"Type" => "Percent",
			"Percent" => 0.10
		))->write();
		OrderDiscount::create(array(
			"Title" => "$5 off",
			"Type" => "Amount",
			"Amount" => 5
		))->write();
		//check that discount matches order
		$matching = Discount::get_matching($this->cart);
		$this->assertDOSEquals(array(
			array("Title" => "10% off"),
			array("Title" => "$5 off")
		), $matching);

		$calculator = new Calculator($this->emptycart);
		$this->assertEquals(0, $calculator->calculate(), "nothing in cart");
		//check that best discount was chosen
		$calculator = new Calculator($this->cart);
		$this->assertEquals(5, $calculator->calculate(), "$5 off $8 is best discount");

		$calculator = new Calculator($this->othercart);
		$this->assertEquals(20, $calculator->calculate(), "10% off $400 is best discount");
		//total discount calculation
		//20 * socks($8) = 160 ...$5 off each = 100
		//10 * tshirt($25) = 250 ..$5 off each  = 50
		//2 * mp3player($200) = 400 ..10% off each = 40
		//total discount: 190
		$calculator = new Calculator($this->megacart);
		$this->assertEquals(190, $calculator->calculate(), "complex savings example");

		$this->assertDOSEquals(array(
			array("Title" => "10% off"),
			array("Title" => "$5 off")
		), $this->megacart->Discounts());
	}

	function testCouponAndDiscountItemLevel() {
		OrderDiscount::create(array(
			"Title" => "10% off",
			"Type" => "Percent",
			"Percent" => 0.10
		))->write();
		OrderCoupon::create(array(
			"Title" => "$10 off each item",
			"Code" => "TENDOLLARSOFF",
			"Type" => "Amount",
			"Amount" => 10
		))->write();

		//total discount calculation
		//20 * socks($8) = 160 ...$10 off each ($8max) = 160
		//10 * tshirt($25) = 250 ..$10 off each  = 100
		//2 * mp3player($200) = 400 ..10% off each = 40
		//total discount: 300
		$calculator = new Calculator($this->megacart, array(
			'CouponCode' => 'TENDOLLARSOFF'
		));
		$this->assertEquals(300, $calculator->calculate(), "complex savings example");
		//no coupon in context
		$calculator = new Calculator($this->megacart);
		$this->assertEquals(81, $calculator->calculate(), "complex savings example");
		//write a test that combines discounts which sum to a greater discount than
		//the order subtotal
	}

	function testItemAndCartLevelAmountDiscounts() {
		OrderDiscount::create(array(
			"Title" => "$400 savings",
			"Type" => "Amount",
			"Amount" => 400,
			"ForItems" => false,
			"ForCart" => true
		))->write();
		OrderDiscount::create(array(
			"Title" => "$500 off baby!",
			"Type" => "Amount",
			"Amount" => 500,
			"ForItems" => true,
			"ForCart" => false
		))->write();

		$calculator = new Calculator($this->megacart);
		$this->assertEquals(810, $calculator->calculate(), "total shouldn't exceed what is possible");

		$this->markTestIncomplete("test distribution of amounts");
	}

	function testCartLevelAmount() {
		//entire cart
		$discount = OrderDiscount::create(array(
			"Title" => "$25 off cart total",
			"Type" => "Amount",
			"Amount" => 25,
			"ForItems" => false,
			"ForCart" => true
		));
		$discount->write();	
		$this->assertTrue($discount->validateOrder($this->cart));
		$calculator = new Calculator($this->cart);
		$this->assertEquals(8, $calculator->calculate());
		$calculator = new Calculator($this->othercart);
		$this->assertEquals(25, $calculator->calculate());
		$calculator = new Calculator($this->megacart);
		$this->assertEquals(25, $calculator->calculate());
	}

	function testCartLevelPercent() {
		$discount = OrderDiscount::create(array(
			"Title" => "50% off products subtotal",
			"Type" => "Percent",
			"Percent" => 0.5,
			"ForItems" => false,
			"ForCart" => true
		));
		$discount->write();

		//products subtotal
		$discount->Products()->addMany(array(
			$this->socks,
			$this->tshirt
		));
		$calculator = new Calculator($this->cart);
		$this->assertEquals(4, $calculator->calculate());
		$calculator = new Calculator($this->megacart);
		$this->assertEquals(205, $calculator->calculate());
	}

	function testMaxAmount() {
		//percent item discounts
		$discount = OrderDiscount::create(array(
			"Title" => "$200 max Discount",
			"Type" => "Percent",
			"Percent" => 0.8,
			"MaxAmount" => 200,
			"ForItems" => true
		));
		$discount->write();
		$calculator = new Calculator($this->megacart);
		$this->assertEquals(200, $calculator->calculate());
		//clean up
		$discount->Active = 0;
		$discount->write();

		//amount item discounts
		$discount = OrderDiscount::create(array(
			"Title" => "$20 max Discount (using amount)",
			"Type" => "Amount",
			"Amount" => 10,
			"MaxAmount" => 20,
			"ForItems" => true
		));
		$discount->write();
		$calculator = new Calculator($this->megacart);
		$this->assertEquals(20, $calculator->calculate());
		//clean up
		$discount->Active = 0;
		$discount->write();

		//percent cart discounts
		OrderDiscount::create(array(
			"Title" => "40 max Discount (using amount)",
			"Type" => "Percent",
			"Percent" => 0.8,
			"MaxAmount" => 40,
			"ForItems" => false,
			"ForCart" => true
		))->write();
		$calculator = new Calculator($this->megacart);
		$this->assertEquals(40, $calculator->calculate());
	}

	function testSavingsTotal() {
		$discount = $this->objFromFixture("OrderDiscount", "limited");
		$this->assertEquals(44, $discount->getSavingsTotal());
		$discount = $this->objFromFixture("OrderCoupon", "limited");
		$this->assertEquals(22, $discount->getSavingsTotal());
	}

	function testOrderSavingsTotal() {
		$discount = $this->objFromFixture("OrderDiscount", "limited");
		$order = $this->objFromFixture("Order", "limitedcoupon");
		$this->assertEquals(44, $discount->getSavingsforOrder($order));

		$discount = $this->objFromFixture("OrderCoupon", "limited");
		$order = $this->objFromFixture("Order", "limitedcoupon");
		$this->assertEquals(22, $discount->getSavingsforOrder($order));
	}

	function testProcessDiscountedOrder() {
		OrderDiscount::create(array(
			"Title" => "$25 off cart total",
			"Type" => "Amount",
			"Amount" => 25,
			"ForItems" => false,
			"ForCart" => true
		))->write();
		$cart = $this->objFromFixture("Order", "payablecart");
		$this->assertEquals(16, $cart->calculate());
		$processor = new OrderProcessor($cart);
		$processor->placeOrder();
		$this->assertEquals(16, Order::get()->byID($cart->ID)->GrandTotal());
	}

	//shipping discounts
	
	public function testFreeShipping() {
		if (!class_exists('ShippingFrameworkModifier')) return;
		$discount = OrderDiscount::create(array(
			"Title" => "Free shipping",
			"ForShipping" => 1,
			"ForItems" => 0,
			"Percent" => 1
		));
		$discount->write();

		$order = $this->cart;
		$shipping = new ShippingFrameworkModifier(array(
			'Amount' => 12.34,
			'OrderID' => $order->ID
		));
		$shipping->write();
		$order->Modifiers()->add($shipping);

		$calculator = new Calculator($order);
		$this->assertTrue($discount->validateOrder($order), "Free shipping discount is valid");
		$this->assertEquals($calculator->calculate(), 12.34, "Shipping discount");
	}

	public function testShippingAmountDiscount() {
		if (!class_exists('ShippingFrameworkModifier')) return;
		$order = $this->cart;
		$discount = OrderDiscount::create(array(
			"Title" => "Free shipping",
			"ForShipping" => 1,
			"ForItems" => 0,
			"Percent" => 1
		));
		$discount->write();

		$shipping = new ShippingFrameworkModifier(array(
			'Amount' => 30,
			'OrderID' => $order->ID
		));
		$shipping->write();
		$order->Modifiers()->add($shipping);
		$calculator = new Calculator($order);
		$this->assertTrue($discount->validateOrder($order), "100% off shipping is valid");
		$this->assertEquals($calculator->calculate(), 30, "discount is full $30 amount");
	}

	public function testShippingPercentDiscount() {
		if (!class_exists('ShippingFrameworkModifier')) return;
		$order = $this->othercart;
		$discount = OrderDiscount::create(array(
			"Title" => "Save 30% off shipping",
			"Percent" => 0.3,
			"ForShipping" => 1,
			"ForItems" => 0
		));
		$discount->write();

		$shipping = new ShippingFrameworkModifier(array(
			'Amount' => 10,
			'OrderID' => $order->ID
		));
		$shipping->write();
		$order->Modifiers()->add($shipping);
		$calculator = new Calculator($order);
		$this->assertTrue($discount->validateOrder($order), "30% off shipping discount is valid");
		$this->assertEquals($calculator->calculate(), 3, "30% discount on $10 of shipping");
	}

	public function testShipingAndItems() {
		if (!class_exists('ShippingFrameworkModifier')) return;
		//test an edge case, where a discount is for orders, and shipping.
		$order = $this->othercart; //$200
		$discount = OrderDiscount::create(array(
			"Title" => "Save $20 on order",
			"Amount" => 20,
			"ForShipping" => 1,
			"ForItems" => 1
		));
		$discount->write();

		$shipping = new ShippingFrameworkModifier(array(
			'Amount' => 30,
			'OrderID' => $order->ID
		));
		$shipping->write();
		$order->Modifiers()->add($shipping);
		$calculator = new Calculator($order);
		$this->assertTrue($discount->validateOrder($order), "Shipping and items discount is valid");
		$this->assertEquals($calculator->calculate(), 40, "$20 discount");

		//TODO:  test when subtotal & shipping are both < 20
	}
	
}
