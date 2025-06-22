<?php

namespace SilverShop\Discounts\Tests;

use SilverShop\Discounts\Calculator;
use SilverStripe\Dev\SapphireTest;
use SilverShop\Tests\ShopTest;
use SilverStripe\Core\Config\Config;
use SilverShop\Discounts\Model\OrderCoupon;
use SilverShop\Page\Product;
use SilverShop\Model\Order;

class OrderCouponTest extends SapphireTest
{

    protected static $fixture_file = [
        'shop.yml'
    ];

    protected Order $cart;

    protected Order $unpaid;

    protected Order $othercart;

    protected Product $socks;

    protected Product $tshirt;

    protected Product $mp3player;

    protected function setUp(): void
    {
        parent::setUp();
        ShopTest::setConfiguration();

        Config::inst()->set(OrderCoupon::class, 'minimum_code_length', null);

        $this->socks = $this->objFromFixture(Product::class, 'socks');
        $this->socks->publishRecursive();

        $this->tshirt = $this->objFromFixture(Product::class, 'tshirt');
        $this->tshirt->publishRecursive();

        $this->mp3player = $this->objFromFixture(Product::class, 'mp3player');
        $this->mp3player->publishRecursive();

        $this->unpaid = $this->objFromFixture(Order::class, 'unpaid');
        $this->cart = $this->objFromFixture(Order::class, 'cart');
        $this->othercart = $this->objFromFixture(Order::class, 'othercart');
    }

    public function testMinimumLengthCode(): void
    {
        Config::inst()->set(OrderCoupon::class, 'minimum_code_length', 8);
        $coupon = OrderCoupon::create();
        $coupon->Code = '1234567';
        $result = $coupon->validate();
        $this->assertSame('INVALIDMINLENGTH', key($result->getMessages()));

        $coupon = OrderCoupon::create();
        $result = $coupon->validate();
        self::assertNotContains('INVALIDMINLENGTH', $result->getMessages(), 'Leaving the Code field generates a code');

        $coupon = OrderCoupon::create(['Code' => '12345678']);
        $result = $coupon->validate();
        self::assertNotContains('INVALIDMINLENGTH', $result->getMessages());

        Config::inst()->set(OrderCoupon::class, 'minimum_code_length', null);

        $coupon = OrderCoupon::create(['Code' => '1']);
        $result = $coupon->validate();
        self::assertNotContains('INVALIDMINLENGTH', $result->getMessages());
    }

    public function testPercent(): void
    {
        $coupon = OrderCoupon::create(
            [
            'Title' => '40% off each item',
            'Code' => '5B97AA9D75',
            'Type' => 'Percent',
            'Percent' => 0.40,
            'StartDate' => '2000-01-01 12:00:00',
            'EndDate' => '2200-01-01 12:00:00'
            ]
        );
        $coupon->write();

        $context = ['CouponCode' => $coupon->Code];
        $this->assertTrue($coupon->validateOrder($this->cart, $context), (string)$coupon->getMessage());
        $this->assertEquals(4, $coupon->getDiscountValue(10), '40% off value');
        $this->assertEquals(200, $this->calc($this->unpaid, $coupon), '40% off order');
    }

    public function testAmount(): void
    {
        $coupon = OrderCoupon::create(
            [
            'Title' => '$10 off each item',
            'Code' => 'TENDOLLARSOFF',
            'Type' => 'Amount',
            'Amount' => 10,
            'Active' => 1
            ]
        );
        $coupon->write();

        $context = ['CouponCode' => $coupon->Code];
        $this->assertTrue($coupon->validateOrder($this->cart, $context), (string)$coupon->getMessage());
        $this->assertEquals($coupon->getDiscountValue(1000), 10, '$10 off fixed value');
        $this->assertTrue($coupon->validateOrder($this->unpaid, $context), (string)$coupon->getMessage());
        $this->assertEquals(60, $this->calc($this->unpaid, $coupon), '$10 off each item: $60 total');
        //TODO: test amount that is greater than item value
    }

    public function testInactiveCoupon(): void
    {
        $inactivecoupon = OrderCoupon::create(
            [
            'Title' => 'Not active',
            'Code' => 'EE891574D6',
            'Type' => 'Amount',
            'Amount' => 10,
            'Active' => 0
            ]
        );
        $inactivecoupon->write();

        $context = ['CouponCode' => $inactivecoupon->Code];
        $this->assertFalse($inactivecoupon->validateOrder($this->cart, $context), 'Coupon is not set to active');
    }

    protected function getCalculator(Order $order, OrderCoupon $coupon): Calculator
    {
        return new Calculator($order, ['CouponCode' => $coupon->Code]);
    }

    protected function calc(Order $order, OrderCoupon $order_coupon): int|float
    {
        return $this->getCalculator($order, $order_coupon)->calculate();
    }
}
