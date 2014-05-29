<?php

class OrderDiscountTest extends SapphireTest{
	
	protected static $fixture_file = array(
		'shop_discount/tests/fixtures/Discounts.yml',
		'shop/tests/fixtures/shop.yml',
		'shop/tests/fixtures/Zones.yml',
		'shop/tests/fixtures/Addresses.yml'
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
		$this->makeDiscountsActive(array(
			"10percentoff", "5dollarsoff"
		));
		$matches = OrderDiscount::get_matching($this->cart);
		$this->assertDOSEquals(array(
			array("Title" => "10% off"),
			array("Title" => "$5 off"),
		), $matches);
		//check best match is chosen
	}

	public function testPercent() {
		$this->makeDiscountActive("10percentoff");
		$this->assertDOSEquals(array(
			array("Title" => "10% off")
		), OrderDiscount::get_matching($this->cart));
	}

	public function testAmount() {
		$this->makeDiscountActive("5dollarsoff");
		$this->assertDOSEquals(array(
			array("Title" => "$5 off")
		), OrderDiscount::get_matching($this->cart));
	}
	
	public function testProducts() {
		$discount = $this->makeDiscountActive("products20percentoff");
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

	public function testMembership() {
		$discount = $this->makeDiscountActive("membership");
		$member = $this->objFromFixture("Member", "joebloggs");
		$discount->Members()->add($member);
		$this->assertFalse($discount->valid($this->cart), "Invalid, because no member");
		$context = array(
			"Member" => $this->objFromFixture("Member", "bobjones")
		);
		$this->assertFalse($discount->valid($this->cart, $context), "Invalid because wrong member present");
		$context = array("Member" => $member);
		$this->assertTrue($discount->valid($this->cart, $context), "Valid because correct member present".$discount->getMessage());
	}

	protected function makeDiscountActive($fixturename) {
		$discount = $this->objFromFixture("OrderDiscount", $fixturename);
		$discount->Active = true;
		$discount->write();
		return $discount;
	}

	protected function makeDiscountsActive(array $fixturenames) {
		foreach ($fixturenames as $name) {
			$this->makeDiscountActive($name);
		}
	}

}