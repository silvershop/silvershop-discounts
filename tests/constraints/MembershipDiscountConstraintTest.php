<?php

class MembershipDiscountConstraintTest extends SapphireTest{

	protected static $fixture_file = array(
		'shop/tests/fixtures/shop.yml'
	);

	public function setUp() {
		parent::setUp();
		ShopTest::setConfiguration();
		$this->cart = $this->objFromFixture("Order", "cart");
	}

	public function testMembership() {
		$discount = OrderDiscount::create(array(
			"Title" => "Membership Coupon",
			"Type" => "Amount",
			"Amount" => 1.33
		));
		$discount->write();

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

}