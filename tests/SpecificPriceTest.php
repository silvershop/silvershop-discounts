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

}
