<?php
/**
 * Tests coupons
 * @package shop-discount
 */
class OrderCouponTest extends FunctionalTest{
	
	static $fixture_file = array(
		'shop_discount/tests/fixtures/OrderCoupons.yml',
		'shop/tests/fixtures/shop.yml',
		'shop/tests/fixtures/Zones.yml',
		'shop/tests/fixtures/Addresses.yml'
	);
	
	function setUp(){
		parent::setUp();
		ShopTest::setConfiguration();		
		$this->placedorder = $this->objFromFixture("Order", "placed");
		$this->cart = $this->objFromFixture("Order", "cart");
		$this->othercart = $this->objFromFixture("Order", "othercart");
	}
	
	function testPercent(){
		$coupon = $this->objFromFixture('OrderCoupon', '40percentoff');
		$this->assertTrue($coupon->valid($this->cart));
		$this->assertEquals($coupon->getDiscountValue(10), 4, "40% off value");
		$this->assertEquals($coupon->orderDiscount($this->placedorder), 200, "40% off order");
	}
	
	function testAmount(){
		$coupon = $this->objFromFixture('OrderCoupon', '10dollarsoff');
		$this->assertTrue($coupon->valid($this->cart));
		$this->assertEquals($coupon->getDiscountValue(1000), 10, "$10 off fixed value");
		$this->assertEquals($coupon->orderDiscount($this->placedorder), 10, "$10 off order");
		//TODO: test ammount that is greater than order value
	}
	
	function testProductsDiscount(){
		$coupon = $this->objFromFixture("OrderCoupon","products20percentoff");
		$coupon->Products()->add($this->objFromFixture("Product", "tshirt")); //add product to coupon product list
		$this->assertEquals($coupon->orderDiscount($this->placedorder), 20);
		$coupon->Products()->add($this->objFromFixture("Product", "mp3player")); //add another product to coupon product list
		$this->assertEquals($coupon->orderDiscount($this->placedorder), 100);
	}
	
	function testCategoryDiscount(){
		$coupon = $this->objFromFixture("OrderCoupon","clothing5percent");
		$coupon->Categories()->add($this->objFromFixture("ProductCategory", "clothing"));
		$this->socks = $this->objFromFixture("Product", "socks");
		$this->socks->publish('Stage','Live');
		$this->assertTrue($coupon->valid($this->cart), "Order contains a t-shirt. ".$coupon->getMessage());
		$this->assertEquals($coupon->orderDiscount($this->cart), 0.4,"5% discount for socks in cart");
		$this->assertFalse($coupon->valid($this->othercart),"Order does not contain clothing");
		$this->assertEquals($coupon->orderDiscount($this->othercart), 0, "No discount, because no product in category");
	}
	
	function testZoneDiscount(){
		$coupon = $this->objFromFixture('OrderCoupon', 'zoned');
		//add zones to coupon
		$coupon->Zones()->add($this->objFromFixture('Zone', 'transtasman'));
		$coupon->Zones()->add($this->objFromFixture('Zone', 'special'));
		$address = $this->objFromFixture("Address", 'bukhp193eq');
		$this->cart->ShippingAddressID = $address->ID; //set address
		$this->assertFalse($coupon->valid($this->cart),"check order is out of zone");
		$address = $this->objFromFixture("Address", 'sau5024');
		$this->othercart->ShippingAddressID = $address->ID; //set address
		$valid = $coupon->valid($this->othercart);
		$this->assertTrue($valid,"check order is in zone");	
	}
	
	function testMinOrderValue(){
		$coupon = $this->objFromFixture("OrderCoupon", "ordersabove200");
		$this->assertFalse($coupon->valid($this->cart),"$8 order isn't enough");
		$this->assertTrue($coupon->valid($this->placedorder),"$500 order is enough");
	}
	
	function testUseLimit(){
		$coupon = $this->objFromFixture("OrderCoupon", "used");
		$this->assertFalse($coupon->valid($this->cart),"Coupon is already used");
		$coupon = $this->objFromFixture("OrderCoupon", "limited");
		$this->assertTrue($coupon->valid($this->cart),"Coupon has been used, but can continue to be used");
	}
	
	function testMemberGroup(){
		$group = $this->objFromFixture("Group","resellers");
		$coupon = $this->objFromFixture("OrderCoupon", "grouped");
		$coupon->GroupID = $group->ID;
		$this->assertFalse($coupon->valid($this->cart),"Invalid for memberless order");
		$this->assertTrue($coupon->valid($this->othercart),"Valid because member is in resellers group");
	}
	
	function testInactiveCoupon(){
		$inactivecoupon = $this->objFromFixture('OrderCoupon', 'inactivecoupon');
		$this->assertFalse($inactivecoupon->valid($this->cart),"Coupon is not set to active");
	}
	
	function testDates(){
		$unreleasedcoupon = $this->objFromFixture('OrderCoupon', 'unreleasedcoupon');
		$this->assertFalse($unreleasedcoupon->valid($this->cart),"Coupon is un released (start date has not arrived)");
		$expiredcoupon = $this->objFromFixture('OrderCoupon', 'expiredcoupon');
		$this->assertFalse($expiredcoupon->valid($this->cart),"Coupon has expired (end date has passed)");
	}
	
	function testFreeShipping(){
		if (!class_exists('ShippingFrameworkModifier')) return;
		$coupon = $this->objFromFixture("OrderCoupon", "freeshipping");
		$order = $this->cart;
		$shipping = new ShippingFrameworkModifier(array(
			'Amount' => 12.34,
			'OrderID' => $order->ID	
		));
		$shipping->write();
		$order->Modifiers()->add($shipping);
		$this->assertTrue($coupon->valid($order),"Free shipping coupon is valid");
		$this->assertEquals($coupon->orderDiscount($order), 12.34, "Shipping discount");
	}
	
	function testShippingAmountDiscount(){
		if (!class_exists('ShippingFrameworkModifier')) return;
		$order = $this->cart;
		$coupon = $this->objFromFixture("OrderCoupon", "10dollarsoffshipping");
		$shipping = new ShippingFrameworkModifier(array(
			'Amount' => 30,
			'OrderID' => $order->ID
		));
		$shipping->write();
		$order->Modifiers()->add($shipping);
		$this->assertTrue($coupon->valid($order),"10 dollars off shipping discount is valid");
		$this->assertEquals($coupon->orderDiscount($order),10,"$10 discount");
	}
	
	function testShippingPercentDiscount(){
		if (!class_exists('ShippingFrameworkModifier')) return;
		$order = $this->othercart;
		$coupon = $this->objFromFixture("OrderCoupon", "30percentoffshipping");
		$shipping = new ShippingFrameworkModifier(array(
			'Amount' => 10,
			'OrderID' => $order->ID
		));
		$shipping->write();
		$order->Modifiers()->add($shipping);
		$this->assertTrue($coupon->valid($order),"30% off shipping discount is valid");
		$this->assertEquals($coupon->orderDiscount($order),3,"30% discount on $10 of shipping");
	}
	
	function testShipingAndItems(){
		if (!class_exists('ShippingFrameworkModifier')) return;
		//test an edge case, where a discount is for orders, and shipping.
		$order = $this->othercart; //$200
		$coupon = $this->objFromFixture("OrderCoupon", "shippinganditems");
		$shipping = new ShippingFrameworkModifier(array(
			'Amount' => 30,
			'OrderID' => $order->ID
		));
		$shipping->write();
		$order->Modifiers()->add($shipping);
		$this->assertTrue($coupon->valid($order),"Shipping and items coupon is valid");
		$this->assertEquals($coupon->orderDiscount($order),20,"$20 discount");
		
		//TODO:  test when subtotal & shipping are both < 20
	}
	
	function testCumulative(){
		$order = $this->cart;
		//$coupon->applyToOrder($order);
		//add coupon
			//check that it remains
		//add non-conflicting
			//check that both remain
		//add conflicting coupon
			//check that only conflicting coupon exists
			//check message given
	}
	
}