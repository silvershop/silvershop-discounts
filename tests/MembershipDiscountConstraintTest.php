<?php

namespace SilverShop\Discounts\Tests;

use SilverStripe\Dev\SapphireTest;
use SilverShop\Tests\ShopTest;
use SilverStripe\Security\Member;
use SilverShop\Model\Order;
use SilverShop\Discounts\Model\OrderDiscount;


class MembershipDiscountConstraintTest extends SapphireTest{

    protected static $fixture_file = [
        'shop.yml'
    ];

    public function setUp()
    {
        parent::setUp();
        ShopTest::setConfiguration();
        $this->cart = $this->objFromFixture(Order::class, 'cart');
    }

    public function testMembership()
    {
        $discount = OrderDiscount::create(
            [
            'Title' => 'Membership Coupon',
            'Type' => 'Amount',
            'Amount' => 1.33
            ]
        );
        $discount->write();

        $member = $this->objFromFixture(Member::class, 'joebloggs');
        $discount->Members()->add($member);

        $this->assertFalse($discount->validateOrder($this->cart), 'Invalid, because no member');
        $context = [
            'Member' => $this->objFromFixture(Member::class, 'bobjones')
        ];
        $this->assertFalse($discount->validateOrder($this->cart, $context), 'Invalid because wrong member present');
        $context = ['Member' => $member];
        $this->assertTrue($discount->validateOrder($this->cart, $context), 'Valid because correct member present' .$discount->getMessage());
    }
}
