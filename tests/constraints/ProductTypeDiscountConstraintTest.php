<?php

use Shop\Discount\Calculator;

class ProductTypeDiscountConstraintTest extends SapphireTest{

	protected static $fixture_file = array(
		'shop/tests/fixtures/shop.yml',
		'shop_discount/tests/fixtures/GiftVouchers.yml'
	);

	public function setUp() {
		parent::setUp();
		ShopTest::setConfiguration();
		Config::inst()->update("Discount","constraints", array(
			"ProductTypeDiscountConstraint"
		));

		$this->cart = $this->objFromFixture("Order", "cart");
		$this->giftcart = $this->objFromFixture("Order", "giftcart");

		$this->socks = $this->objFromFixture("Product", "socks");
		$this->socks->publish("Stage", "Live");
		$this->tshirt = $this->objFromFixture("Product", "tshirt");
		$this->tshirt->publish("Stage", "Live");
		$this->mp3player = $this->objFromFixture("Product", "mp3player");
		$this->mp3player->publish("Stage", "Live");

		$this->voucher = $this->objFromFixture("GiftVoucherProduct", "10fixed");
		$this->voucher->publish("Stage", "Live");
	}
	
	public function testProducts() {
		$discount = OrderDiscount::create(array(
			"Title" => "20% off each products",
			"Percent" => 0.2,
			"ProductTypes" => "Product"
		));
		$discount->write();

		$this->assertTrue($discount->validateOrder($this->cart));
		$this->assertFalse($discount->validateOrder($this->giftcart));
	}

}