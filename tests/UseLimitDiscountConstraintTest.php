<?php

namespace SilverShop\Discounts\Tests;

use SilverStripe\Dev\SapphireTest;
use SilverShop\Tests\ShopTest;
use SilverShop\Discounts\Model\OrderCoupon;
use SilverShop\Model\Order;

class UseLimitDiscountConstraintTest extends SapphireTest
{

    protected static $fixture_file = [
        'shop.yml',
        'Discounts.yml'
    ];

    protected Order $cart;

    public function setUp(): void
    {
        parent::setUp();
        ShopTest::setConfiguration();
        $this->cart = $this->objFromFixture(Order::class, 'cart');
    }

    public function testUseLimit(): void
    {
        $coupon = $this->objFromFixture(OrderCoupon::class, 'used');
        $context = ['CouponCode' => $coupon->Code];
        $this->assertFalse($coupon->validateOrder($this->cart, $context), 'Coupon is already used');
        $coupon = $this->objFromFixture(OrderCoupon::class, 'limited');
        $context = ['CouponCode' => $coupon->Code];
        $this->assertTrue(
            $coupon->validateOrder($this->cart, $context),
            'Coupon has been used, but can continue to be used'
        );
    }
}
