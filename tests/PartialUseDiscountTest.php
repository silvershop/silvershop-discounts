<?php

class PartialUseDiscountTest extends SapphireTest{
	
	protected static $fixture_file = array(
		'shop/tests/fixtures/shop.yml',
		'shop_discount/tests/fixtures/PartialUseDiscount.yml'
	);

	function testCreateRemainder() {
		//basic remainder
		$discount = $this->objFromFixture("PartialUseDiscount", "partial");
		$this->assertNull($discount->createRemainder(5000));
		$this->assertNull($discount->createRemainder(90));
		$remainderdiscount = $discount->createRemainder(40);
		$this->assertEquals(50, $remainderdiscount->Amount, "Subtract $40 from $90 discount");
		$this->assertNull($discount->createRemainder(30), "Cannot recreate remainder");

		//TODO: check basic relationships match, e.g. group

		//check constraints copying works
		$discount = $this->objFromFixture("PartialUseDiscount", "constrained");
		$remainder = $discount->createRemainder(40);
		$this->assertDOSEquals(array(
			array("FirstName" => "Joe")
		), $remainder->Members());
		$this->assertDOSEquals(array(
			array("Title" => "ProductA"),
			array("Title" => "ProductB")
		), $remainder->Products());
	}

	function testCheckoutProcessing() {
		//create remainder on payment
		$this->markTestIncomplete('This should be tested');
	}

}