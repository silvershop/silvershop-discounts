<?php

class ZonesDiscountConstraintTest extends SapphireTest{
    
    protected static $fixture_file = array(
        'silvershop/tests/fixtures/shop.yml',
        'silvershop/tests/fixtures/Zones.yml',
        'silvershop/tests/fixtures/Addresses.yml'
    );

    public function setUp() {
        parent::setUp();
        ShopTest::setConfiguration();

        Config::inst()->update('OrderCoupon', 'minimum_code_length', null);

        $this->cart = $this->objFromFixture("Order", "cart");
        $this->othercart = $this->objFromFixture("Order", "othercart");
    }

    public function testZoneDiscount() {
        $coupon = OrderCoupon::create(array(
            "Title" => "Zoned Coupon",
            "Type" => "Percent",
            "Percent" => 0.16
        ));
        $coupon->write();
        $coupon->Zones()->add($this->objFromFixture('Zone', 'transtasman'));
        $coupon->Zones()->add($this->objFromFixture('Zone', 'special'));
        $address = $this->objFromFixture("Address", 'bukhp193eq');
        $context = array("CouponCode" => $coupon->Code);
        $this->cart->ShippingAddressID = $address->ID; //set address
        $this->assertFalse($coupon->validateOrder($this->cart, $context), "check order is out of zone");
        $address = $this->objFromFixture("Address", 'sau5024');
        $this->othercart->ShippingAddressID = $address->ID; //set address
        $valid = $coupon->validateOrder($this->othercart, $context);
        $this->assertTrue($valid, "check order is in zone");
    }

}