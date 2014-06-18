<?php

class GroupDiscountConstraintTest extends SapphireTest{

	protected static $fixture_file = array(
		'shop/tests/fixtures/shop.yml'
	);

	public function setUp() {
		parent::setUp();
		ShopTest::setConfiguration();
		$this->cart = $this->objFromFixture("Order", "cart");
		$this->othercart = $this->objFromFixture("Order", "othercart");
	}
	
	public function testMemberGroup() {
		$coupon = OrderCoupon::create(array(
			"Title" => "Special Members Coupon",
			"Code" => "GROUPED",
			"Type" => "Percent",
			"Percent" => 0.9,
			"Active" => 1,
			"GroupID" => $this->objFromFixture("Group", "resellers")->ID	
		));
		$coupon->write();
		
		$context = array("CouponCode" => $coupon->Code);
		$this->assertFalse($coupon->valid($this->cart, $context), "Invalid for memberless order");
		$context = array(
			"CouponCode" => $coupon->Code,
			"Member" => $this->objFromFixture("Member", "bobjones")
		);
		$this->assertTrue($coupon->valid($this->othercart, $context), "Valid because member is in resellers group");
	}
	
}
