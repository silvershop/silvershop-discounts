<?php

class OrderDiscountTest extends SapphireTest{
	
	protected static $fixture_file = array(
		'shop_discount/tests/fixtures/Discounts.yml',
		'shop/tests/fixtures/shop.yml'
	);

	public function setUp() {
		parent::setUp();
		ShopTest::setConfiguration();
		$this->cart = $this->objFromFixture("Order", "cart");
	}

	/**
	 * Check that available discounts are matched to the current order.
	 */
	public function testManyMatches() {
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
		$matches = OrderDiscount::get_matching($this->cart);
		$this->assertDOSEquals(array(
			array("Title" => "10% off"),
			array("Title" => "$5 off"),
		), $matches);
	}

	public function testPercent() {
		OrderDiscount::create(array(
			"Title" => "10% off",
			"Type" => "Percent",
			"Percent" => 0.10
		))->write();		
		$this->assertDOSEquals(array(
			array("Title" => "10% off")
		), OrderDiscount::get_matching($this->cart));
	}

	public function testAmount() {
		OrderDiscount::create(array(
			"Title" => "$5 off",
			"Type" => "Amount",
			"Amount" => 5
		))->write();
		$this->assertDOSEquals(array(
			array("Title" => "$5 off")
		), OrderDiscount::get_matching($this->cart));
	}

	public function testUseCount() {
		//check that order with payment started counts as a use
		$discount = $this->objFromFixture("OrderDiscount", "paymentused");
		$payment = $this->objFromFixture("Payment", "paymentstarted_recent");
		//set timeout to 60 minutes
		Discount::config()->unpaid_use_timeout = 60;
		//set payment to be created 20 min ago
		$payment->Created = date('Y-m-d H:i:s', strtotime("-20 minutes"));
		$payment->write();
		$this->assertEquals(1, $discount->getUseCount());
		//set payment ot be created 2 days ago
		$payment->Created = date('Y-m-d H:i:s', strtotime("-2 days"));
		$payment->write();
		$this->assertEquals(0, $discount->getUseCount());
		//failed payments should be ignored
		$payment->Created = date('Y-m-d H:i:s', strtotime("-20 minutes"));
		$payment->Status = 'Void';
		$payment->write();
		$this->assertEquals(0, $discount->getUseCount());
	}

}