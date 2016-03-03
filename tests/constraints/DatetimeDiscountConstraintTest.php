<?php

class DatetimeDiscountConstraintTest extends SapphireTest{

    protected static $fixture_file = array(
        'silvershop/tests/fixtures/shop.yml'
    );

    public function setUp() {
        parent::setUp();
        ShopTest::setConfiguration();
        $this->cart = $this->objFromFixture("Order", "cart");
    }

    public function testDates() {
        $unreleasedcoupon = OrderCoupon::create(array(
            "Title" => "Unreleased $10 off",
            "Code" => '0444444440',
            "Type" => "Amount",
            "Amount" => 10,
            "StartDate" => "2200-01-01 12:00:00"
        ));
        $unreleasedcoupon->write();
        $context = array("CouponCode" => $unreleasedcoupon->Code);
        $this->assertFalse($unreleasedcoupon->validateOrder($this->cart, $context), "Coupon is un released (start date has not arrived)");

        $expiredcoupon = OrderCoupon::create(array(
            "Title" => "Save lots",
            "Code" => "04994C332A",
            "Type" => "Percent",
            "Percent" => 0.8,
            "Active" => 1,
            "StartDate" => "",
            "EndDate" => "12/12/1990"
        ));
        $expiredcoupon->write();
        $context = array("CouponCode" => $expiredcoupon->Code);
        $this->assertFalse($expiredcoupon->validateOrder($this->cart, $context), "Coupon has expired (end date has passed)");
    }
    
}