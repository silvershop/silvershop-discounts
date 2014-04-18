<?php

class OrderDiscountTest extends SapphireTest{
	
	protected static $fixture_file = array(
		'shop_discount/tests/fixtures/OrderDiscounts.yml',
		'shop/tests/fixtures/shop.yml',
		'shop/tests/fixtures/Zones.yml',
		'shop/tests/fixtures/Addresses.yml'
	);

	public function setUp() {
		parent::setUp();
		ShopTest::setConfiguration();

		$this->cart = $order = $this->objFromFixture("Order", "cart");
	}

	/**
	 * Check that available discounts are matched to the current order.
	 * 
	 * @return [type] [description]
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
		//
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

	//test start, end dates

	//test group
	
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

	//test categories

	//test zone matches

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