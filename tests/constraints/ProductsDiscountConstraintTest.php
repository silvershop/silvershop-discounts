<?php

use Shop\Discount\Calculator;

class ProductsDiscountConstraintTest extends SapphireTest{

	protected static $fixture_file = array(
		'shop/tests/fixtures/shop.yml'
	);

	public function setUp() {
		parent::setUp();
		ShopTest::setConfiguration();
		$this->cart = $this->objFromFixture("Order", "cart");
		$this->placedorder = $this->objFromFixture("Order", "unpaid");
		$this->megacart = $this->objFromFixture("Order", "megacart");
		$this->modifiedcart = $this->objFromFixture("Order", "modifiedcart");

		$this->socks = $this->objFromFixture("Product", "socks");
		$this->socks->publish("Stage", "Live");
		$this->tshirt = $this->objFromFixture("Product", "tshirt");
		$this->tshirt->publish("Stage", "Live");
		$this->mp3player = $this->objFromFixture("Product", "mp3player");
		$this->mp3player->publish("Stage", "Live");
	}
	
	public function testProducts() {
		$discount = OrderDiscount::create(array(
			"Title" => "20% off each selected products",
			"Percent" => 0.2
		));
		$discount->write();
		$discount->Products()->add($this->objFromFixture("Product", "tshirt"));
		$this->assertFalse($discount->validateOrder($this->cart));
		//no products match
		$this->assertDOSEquals(array(), OrderDiscount::get_matching($this->cart));
		//add product discount list
		$discount->Products()->add($this->objFromFixture("Product", "tshirt"));
		$this->assertFalse($discount->validateOrder($this->cart));
		//no products match
		$this->assertDOSEquals(array(), OrderDiscount::get_matching($this->cart));

		$this->markTestIncomplete("Test variations also");
	}

	public function testProductsCoupon() {
		$coupon = OrderCoupon::create(array(
			"Title" => "Selected products",
			"Code" => "PRODUCTS",
			"Percent" => 0.2
		));
		$coupon->write();
		$coupon->Products()->add($this->objFromFixture("Product", "tshirt"));

		$calculator = new Calculator($this->placedorder, array("CouponCode" => $coupon->Code));
		$this->assertEquals($calculator->calculate(), 20);
		//add another product to coupon product list
		$coupon->Products()->add($this->objFromFixture("Product", "mp3player"));
		$this->assertEquals($calculator->calculate(), 100);

		$this->markTestIncomplete("Test variations also");
	}

	function testProductDiscount() {
		$discount = OrderDiscount::create(array(
			"Title" => "20% off each selected products",
			"Percent" => 0.2,
			"Active" => 1,
			"ExactProducts" => 1
		));
		$discount->write();
		//selected products
		$discount->Products()->add($this->socks);
		$discount->Products()->add($this->tshirt);
		//should work for megacart
		//20 * socks($8) = 160 ...20% off each = 32
		//10 * tshirt($25) = 250 ..20% off each  = 50
		//2 * mp3player($200) = 400 ..nothing off = 0
		//total discount: 82
		$calculator = new Calculator($this->megacart);
		$this->assertEquals(82, $calculator->calculate(), "20% off selected products");
		//no discount for cart
		$calculator = new Calculator($this->cart);
		$this->assertEquals(0, $calculator->calculate(), "20% off selected products");		
		//no discount for modifiedcart
		$calculator = new Calculator($this->modifiedcart);
		$this->assertEquals(0, $calculator->calculate(), "20% off selected products");
		
		//partial match
		$discount->ExactProducts = 0;
		$discount->write();
		//total discount: 82
		$calculator = new Calculator($this->megacart);
		$this->assertEquals(82, $calculator->calculate(), "20% off selected products");
		//discount for cart: 32 (just socks)
		$calculator = new Calculator($this->cart);
		$this->assertEquals(1.6, $calculator->calculate(), "20% off selected products");			
		//no discount for modified cart
		$calculator = new Calculator($this->modifiedcart);
		$this->assertEquals(0, $calculator->calculate(), "20% off selected products");

		//get individual item discounts
		$discount = $this->objFromFixture("Product_OrderItem", "megacart_socks")
						->Discounts()->first();
		$this->assertEquals(32, $discount->DiscountAmount);

		$this->markTestIncomplete("Test variations also");
	}

}