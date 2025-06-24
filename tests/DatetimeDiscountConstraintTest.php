<?php

namespace SilverShop\Discounts\Tests;

use SilverStripe\Dev\SapphireTest;
use SilverShop\Tests\ShopTest;
use SilverShop\Discounts\Model\OrderCoupon;
use SilverShop\Model\Order;

class DatetimeDiscountConstraintTest extends SapphireTest
{
    protected static $fixture_file = [
        'shop.yml'
    ];

    protected Order $cart;

    protected function setUp(): void
    {
        parent::setUp();

        ShopTest::setConfiguration();

        $this->cart = $this->objFromFixture(Order::class, 'cart');
    }

    public function testDates(): void
    {
        $orderCoupon = OrderCoupon::create(
            [
                'Title' => 'Unreleased $10 off',
                'Code' => '0444444440',
                'Type' => 'Amount',
                'Amount' => 10,
                'StartDate' => '2200-01-01 12:00:00'
            ]
        );

        $orderCoupon->write();

        $context = ['CouponCode' => $orderCoupon->Code];
        $this->assertFalse(
            $orderCoupon->validateOrder($this->cart, $context),
            'Coupon is un released (start date has not arrived)'
        );

        $expiredcoupon = OrderCoupon::create(
            [
                'Title' => 'Save lots',
                'Code' => '04994C332A',
                'Type' => 'Percent',
                'Percent' => 0.8,
                'Active' => 1,
                'StartDate' => '',
                'EndDate' => '12/12/1990'
            ]
        );

        $expiredcoupon->write();

        $context = ['CouponCode' => $expiredcoupon->Code];
        $this->assertFalse(
            $expiredcoupon->validateOrder($this->cart, $context),
            'Coupon has expired (end date has passed)'
        );
    }
}
