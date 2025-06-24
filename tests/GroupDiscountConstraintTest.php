<?php

namespace SilverShop\Discounts\Tests;

use SilverShop\Model\Order;
use SilverStripe\Dev\SapphireTest;
use SilverShop\Tests\ShopTest;
use SilverShop\Discounts\Model\OrderCoupon;
use SilverStripe\Security\Group;
use SilverStripe\Security\Member;

class GroupDiscountConstraintTest extends SapphireTest
{

    protected static $fixture_file = [
        'shop.yml'
    ];

    protected Order $cart;

    protected Order $othercart;

    protected function setUp(): void
    {
        parent::setUp();
        ShopTest::setConfiguration();
        $this->cart = $this->objFromFixture(Order::class, 'cart');
        $this->othercart = $this->objFromFixture(Order::class, 'othercart');
    }

    public function testMemberGroup(): void
    {
        $orderCoupon = OrderCoupon::create(
            [
                'Title' => 'Special Members Coupon',
                'Code' => 'GROUPED',
                'Type' => 'Percent',
                'Percent' => 0.9,
                'Active' => 1,
                'GroupID' => $this->objFromFixture(Group::class, 'resellers')->ID
            ]
        );
        $orderCoupon->write();

        $context = ['CouponCode' => $orderCoupon->Code];
        $this->assertFalse($orderCoupon->validateOrder($this->cart, $context), 'Invalid for memberless order');
        $context = [
            'CouponCode' => $orderCoupon->Code,
            'Member' => $this->objFromFixture(Member::class, 'bobjones')
        ];
        $this->assertTrue(
            $orderCoupon->validateOrder($this->othercart, $context),
            'Valid because member is in resellers group'
        );
    }
}
