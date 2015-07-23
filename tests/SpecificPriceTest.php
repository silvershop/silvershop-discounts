<?php

class SpecificPriceTest extends SapphireTest{
	
	protected static $fixture_file = array(
		'shop_discount/tests/fixtures/SpecificPrices.yml'
	);
	
	function setUp(){
		parent::setUp();
		Object::add_extension("Product", "SpecificPricingExtension");
		Object::add_extension("ProductVariation", "SpecificPricingExtension");
	}

	function testProductPrice() {
		$product = $this->objFromFixture("Product", "raspberrypi");
		$this->assertEquals(45, $product->sellingPrice());
		$this->assertTrue($product->IsReduced());
		$this->assertEquals(5, $product->getTotalReduction());
	}

	function testProductVariationPrice() {
		$variation = $this->objFromFixture("ProductVariation", "robot_30gb");
		$this->assertEquals(90, $variation->sellingPrice());
		$this->assertTrue($variation->IsReduced());
		$this->assertEquals(10, $variation->getTotalReduction());
	}

	function testProductPricePercentage() {
		$discount = $this->objFromFixture("SpecificPrice", "raspberrypi_dateconstrained");
		$discount->DiscountPercent = 0.5;
		$discount->Price = 0;
		$discount->write();
		$product = $this->objFromFixture("Product", "raspberrypi");
		$this->assertEquals(25, $product->sellingPrice());
		$this->assertTrue($product->IsReduced());
		$this->assertEquals(25, $product->getTotalReduction());
	}

	function testProductVariationPricePercentage() {
		$discount = $this->objFromFixture("SpecificPrice", "robot_30gb_specific");
		$discount->DiscountPercent = 0.5;
		$discount->Price = 0;
		$discount->write();
		$variation = $this->objFromFixture("ProductVariation", "robot_30gb");
		$this->assertEquals(50, $variation->sellingPrice());
		$this->assertTrue($variation->IsReduced());
		$this->assertEquals(50, $variation->getTotalReduction());
	}
}
