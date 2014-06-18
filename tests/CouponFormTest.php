<?php

class CouponFormTest extends FunctionalTest{

	protected static $fixture_file = array(
		'shop/tests/fixtures/shop.yml'
	);

	function setUp() {
		parent::setUp();

		$this->objFromFixture("Product", "socks")
			->publish("Stage", "Live");
	}
	
	function testCouponForm() {

		OrderCoupon::create(array(
			"Title" => "40% off each item",
			"Code" => "5B97AA9D75",
			"Type" => "Percent",
			"Percent" => 0.40
		))->write();

		$checkoutpage = $this->objFromFixture("CheckoutPage", "checkout");
		$checkoutpage->publish("Stage", "Live");
		$controller = new CheckoutPage_Controller($checkoutpage);
		$order =  $this->objFromFixture("Order", "cart");
		$form = new CouponForm($controller, "CouponForm", $order);
		$data = array("Code" => "5B97AA9D75");
		$form->loadDataFrom($data);
		$this->assertTrue($form->validate());
		$form->applyCoupon($data, $form);
		$this->assertEquals("5B97AA9D75", Session::get("cart.couponcode"));
		$form->removeCoupon(array(), $form);
	}

}