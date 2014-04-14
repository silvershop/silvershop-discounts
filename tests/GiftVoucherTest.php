<?php

class GiftVoucherTest extends SapphireTest{

	protected static $fixture_file = array(
		'shop_discount/tests/fixtures/GiftVouchers.yml'
	);

	public function setUp() {
		parent::setUp();

		$this->variable = $this->objFromFixture("GiftVoucherProduct", "variable");
		$this->variable->publish('Stage', 'Live');

		$this->fixed10 = $this->objFromFixture("GiftVoucherProduct", "10fixed");
		$this->fixed10->publish('Stage', 'Live');
	}

	public function testCusomisableVoucher() {

		$controller =  new GiftVoucherProduct_Controller($this->variable);
		$form = $controller->Form();

		$form->loadDataFrom($data = array(
			"UnitPrice" => 32.35,
			"Quantity" => 1
		));
		$this->assertTrue($form->validate(), "Voucher form is valid");

		$form->loadDataFrom(array(
			"UnitPrice" => 3,
			"Quantity" => 5
		));
		$this->assertFalse($form->validate(), "Tested unit price is below minimum amount");

		$form->loadDataFrom(array(
			"UnitPrice" => 0,
			"Quantity" => 5
		));
		$this->assertFalse($form->validate(), "Tested unit price is zero");
	}

	public function testFixedVoucher() {
		$controller =  new GiftVoucherProduct_Controller($this->fixed10);
		$form = $controller->Form();
		$form->loadDataFrom(array(
			"Quantity" => 2
		));
		$this->assertTrue($form->validate(), "Valid voucher");
	}

	public function testCreateCoupon() {
		$item = $this->variable->createItem(1, array(
			"UnitPrice" => 15.00
		));
		$coupon = $item->createCoupon();
		$this->assertEquals($coupon->Amount, 15, "Coupon value is $15, as per order item");
		$this->assertEquals($coupon->Type, "Amount", "Coupon type is 'Amount'");
	}

	public function testOnPayment() {

	}

	//TODO: ensure gift vouchers can only be used once

}
