<?php

namespace SilverShop\Discounts\Tests;

use SilverShop\Discounts\Calculator;
use SilverStripe\Dev\SapphireTest;
use SilverShop\Tests\ShopTest;
use SilverShop\Discounts\Model\OrderCoupon;
use SilverShop\Model\Order;

class ValueDiscountConstraintTest extends SapphireTest
{

    public $placedorder;

    protected static $fixture_file = [
        'shop.yml'
    ];

    protected Order $cart;

    protected Order $othercart;

    protected Order $placeorder;

    protected function setUp(): void
    {
        parent::setUp();
        ShopTest::setConfiguration();

        $this->cart = $this->objFromFixture(Order::class, 'cart');
        $this->othercart = $this->objFromFixture(Order::class, 'othercart');
        $this->placedorder = $this->objFromFixture(Order::class, 'unpaid');
    }

    public function testMinOrderValue(): void
    {
        $orderCoupon = OrderCoupon::create(
            [
                'Title' => 'Orders 200 and more',
                'Code' => '200PLUS',
                'Type' => 'Amount',
                'Amount' => 35,
                'ForItems' => 0,
                'ForCart' => 1,
                'MinOrderValue' => 200
            ]
        );
        $orderCoupon->write();

        $context = ['CouponCode' => $orderCoupon->Code];
        $this->assertFalse($orderCoupon->validateOrder($this->cart, $context), "$8 order isn't enough");
        $this->assertTrue($orderCoupon->validateOrder($this->othercart, $context), '$200 is enough');
        $this->assertTrue($orderCoupon->validateOrder($this->placedorder, $context), '$500 order is enough');

        $calculator = Calculator::create($this->cart, $context);
        $this->assertSame(0, (int) $calculator->calculate());
        $calculator = Calculator::create($this->othercart, $context);
        $this->assertSame(35, (int) $calculator->calculate());
        $calculator = Calculator::create($this->placedorder, $context);
        $this->assertSame(35, (int) $calculator->calculate());
    }
}
