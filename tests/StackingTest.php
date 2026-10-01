<?php

namespace SilverShop\Discounts\Tests;

use SilverShop\Discounts\Calculator;
use SilverShop\Discounts\Model\Modifiers\OrderDiscountModifier;
use SilverShop\Discounts\Model\OrderCoupon;
use SilverShop\Discounts\Model\OrderDiscount;
use SilverShop\Model\Order;
use SilverShop\Page\Product;
use SilverShop\Tests\ShopTestBootstrap;
use SilverStripe\Dev\SapphireTest;

class StackingTest extends SapphireTest
{
    protected static $fixture_file = [
        'shop.yml'
    ];

    protected Order $othercart;

    protected Order $megacart;

    protected function setUp(): void
    {
        parent::setUp();
        ShopTestBootstrap::setConfiguration();

        Order::config()->modifiers = [
            OrderDiscountModifier::class
        ];

        foreach (['socks', 'tshirt', 'mp3player'] as $product) {
            $this->objFromFixture(Product::class, $product)->publishRecursive();
        }

        // single $200 mp3 player
        $this->othercart = $this->objFromFixture(Order::class, 'othercart');
        // 20 x $8 socks, 10 x $25 tshirts, 2 x $200 mp3 players
        $this->megacart = $this->objFromFixture(Order::class, 'megacart');
    }

    public function testNonStackableDiscountsUseBestOnly(): void
    {
        $this->cartDiscount('10% off', 'Percent', 0.1);
        $this->cartDiscount('20% off', 'Percent', 0.2);

        $calculator = Calculator::create($this->othercart);
        $this->assertEqualsWithDelta(40, $calculator->calculate(), 0.001);
        $this->assertListEquals([['Title' => '20% off']], $this->othercart->Discounts());
    }

    public function testStackableCartDiscountsCompound(): void
    {
        $this->cartDiscount('10% off', 'Percent', 0.1, true, 10);
        $this->cartDiscount('20% off', 'Percent', 0.2, true);

        // 10% of 200 = 20, then 20% of the remaining 180 = 36
        $calculator = Calculator::create($this->othercart);
        $this->assertEqualsWithDelta(56, $calculator->calculate(), 0.001);
        $this->assertListEquals(
            [['Title' => '10% off'], ['Title' => '20% off']],
            $this->othercart->Discounts()
        );
    }

    public function testPriorityControlsStackingOrder(): void
    {
        $this->cartDiscount('$50 off', 'Amount', 50, true, 10);
        $percent = $this->cartDiscount('50% off', 'Percent', 0.5, true, 0);

        // $50 first, then 50% of the remaining $150
        $this->assertEqualsWithDelta(125, Calculator::create($this->othercart)->calculate(), 0.001);

        $percent->Priority = 20;
        $percent->write();

        // 50% first, then $50 off the remaining $100
        $this->assertEqualsWithDelta(150, Calculator::create($this->othercart)->calculate(), 0.001);
    }

    public function testBetterNonStackableBeatsStack(): void
    {
        $this->cartDiscount('10% off', 'Percent', 0.1, true);
        $this->cartDiscount('Another 10% off', 'Percent', 0.1, true);
        $this->cartDiscount('Half price', 'Percent', 0.5);

        $calculator = Calculator::create($this->othercart);
        $this->assertEqualsWithDelta(100, $calculator->calculate(), 0.001);
        $this->assertListEquals([['Title' => 'Half price']], $this->othercart->Discounts());
    }

    public function testStackedDiscountsNeverExceedSubtotal(): void
    {
        $this->cartDiscount('$150 off', 'Amount', 150, true);
        $this->cartDiscount('Another $150 off', 'Amount', 150, true);

        $calculator = Calculator::create($this->othercart);
        $this->assertEqualsWithDelta(200, $calculator->calculate(), 0.001);
    }

    public function testStackableItemDiscountsCompoundPerUnit(): void
    {
        OrderDiscount::create([
            'Title' => '$5 off',
            'Type' => 'Amount',
            'Amount' => 5,
            'AllowStacking' => true,
            'Priority' => 10
        ])->write();

        OrderDiscount::create([
            'Title' => '10% off',
            'Type' => 'Percent',
            'Percent' => 0.1,
            'AllowStacking' => true
        ])->write();

        // socks: $5 + 10% of $3 = 5.30 x 20 = 106
        // tshirt: $5 + 10% of $20 = 7 x 10 = 70
        // mp3: $5 + 10% of $195 = 24.50 x 2 = 49
        $calculator = Calculator::create($this->megacart);
        $this->assertEqualsWithDelta(225, $calculator->calculate(), 0.001);
    }

    public function testStackableCouponsWithMultipleCodes(): void
    {
        $this->cartCoupon('SAVE10', 0.1, true, 10);
        $this->cartCoupon('SAVE20', 0.2, true);

        $this->assertEqualsWithDelta(
            20,
            Calculator::create($this->othercart, ['CouponCodes' => ['SAVE10']])->calculate(),
            0.001
        );

        $this->assertEqualsWithDelta(
            56,
            Calculator::create($this->othercart, ['CouponCodes' => ['save10', 'SAVE20']])->calculate(),
            0.001
        );
    }

    public function testNonStackableCouponIsNotCombined(): void
    {
        $this->cartDiscount('Sitewide 10% off', 'Percent', 0.1, true);
        $this->cartCoupon('SAVE20', 0.2, false);

        // coupon alone beats the automatic discount, and they are not combined
        $calculator = Calculator::create($this->othercart, ['CouponCodes' => ['SAVE20']]);
        $this->assertEqualsWithDelta(40, $calculator->calculate(), 0.001);
        $this->assertListEquals([['Title' => 'SAVE20']], $this->othercart->Discounts());
    }

    protected function cartDiscount(
        string $title,
        string $type,
        float $value,
        bool $stacking = false,
        int $priority = 0
    ): OrderDiscount {
        $discount = OrderDiscount::create([
            'Title' => $title,
            'Type' => $type,
            $type => $value,
            'ForItems' => false,
            'ForCart' => true,
            'AllowStacking' => $stacking,
            'Priority' => $priority
        ]);
        $discount->write();

        return $discount;
    }

    protected function cartCoupon(string $code, float $percent, bool $stacking, int $priority = 0): OrderCoupon
    {
        $coupon = OrderCoupon::create([
            'Title' => $code,
            'Code' => $code,
            'Type' => 'Percent',
            'Percent' => $percent,
            'ForItems' => false,
            'ForCart' => true,
            'AllowStacking' => $stacking,
            'Priority' => $priority
        ]);
        $coupon->write();

        return $coupon;
    }
}
