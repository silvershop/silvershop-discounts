<?php

namespace SilverShop\Discounts\Tests;

use SilverStripe\Dev\SapphireTest;
use SilverShop\Tests\ShopTest;
use SilverShop\Model\Order;
use SilverShop\Discounts\Model\OrderCoupon;
use SilverStripe\Security\Group;
use SilverStripe\Security\Member;

class GroupDiscountConstraintTest extends SapphireTest
{

    protected static $fixture_file = [
        'shop.yml'
    ];

    public function setUp()
    {
        parent::setUp();
        ShopTest::setConfiguration();
        $this->cart = $this->objFromFixture(Order::class, "cart");
        $this->othercart = $this->objFromFixture(Order::class, "othercart");
    }

    public function testMemberGroup()
    {
        $coupon = OrderCoupon::create(
            [
            "Title" => "Special Members Coupon",
            "Code" => "GROUPED",
            "Type" => "Percent",
            "Percent" => 0.9,
            "Active" => 1,
            "GroupID" => $this->objFromFixture(Group::class, "resellers")->ID
            ]
        );
        $coupon->write();

        $context = ["CouponCode" => $coupon->Code];
        $this->assertFalse($coupon->validateOrder($this->cart, $context), "Invalid for memberless order");
        $context = [
            "CouponCode" => $coupon->Code,
            "Member" => $this->objFromFixture("Member", "bobjones")
        ];
        $this->assertTrue($coupon->validateOrder($this->othercart, $context), "Valid because member is in resellers group");
    }
}
