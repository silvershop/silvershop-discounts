<?php

namespace SilverShop\Discounts\Tests;

use SilverShop\Discounts\Calculator;
use SilverShop\Discounts\Model\DiscountCode;
use SilverShop\Discounts\Model\OrderCoupon;
use SilverShop\Model\Order;
use SilverShop\Page\Product;
use SilverShop\Tests\ShopTestBootstrap;
use SilverStripe\Core\Config\Config;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Security\Member;

/**
 * Usage tracking for multi-code coupons (issue #31): the stored per-discount / per-code counter,
 * per-code use limits, and per-customer limits.
 */
class DiscountCodeUsageTest extends SapphireTest
{
    protected static $fixture_file = [
        'shop.yml'
    ];

    protected Order $paid;

    protected Order $cart;

    protected Order $unpaid;

    protected OrderCoupon $coupon;

    protected function setUp(): void
    {
        parent::setUp();
        ShopTestBootstrap::setConfiguration();

        Config::modify()->set(OrderCoupon::class, 'minimum_code_length', null);

        foreach (['socks', 'tshirt', 'mp3player'] as $handle) {
            $this->objFromFixture(Product::class, $handle)->publishRecursive();
        }

        $this->paid = $this->objFromFixture(Order::class, 'paid');
        $this->cart = $this->objFromFixture(Order::class, 'cart');
        $this->unpaid = $this->objFromFixture(Order::class, 'unpaid');

        $this->coupon = OrderCoupon::create([
            'Title' => 'Campaign',
            'Code' => 'PRIMARY',
            'Type' => 'Percent',
            'Percent' => 0.5,
            'Active' => 1
        ]);
        $this->coupon->write();
    }

    protected function childCode(string $code, int $useLimit = 0, int $perCustomer = 0): DiscountCode
    {
        $child = DiscountCode::create([
            'Code' => $code,
            'Active' => 1,
            'UseLimit' => $useLimit,
            'PerCustomerUseLimit' => $perCustomer
        ]);
        $child->DiscountID = $this->coupon->ID;
        $child->write();

        return $child;
    }

    public function testOnPaidIncrementsStoredCounters(): void
    {
        $child = $this->childCode('CHILDA');

        // redeem the code on an already-paid order, then fire the paid hook
        Calculator::create($this->paid, ['CouponCodes' => ['CHILDA']])->calculate();
        $this->paid->extend('onPaid');

        $this->assertSame(1, (int) DiscountCode::get()->byID($child->ID)->TimesUsed, 'code counter increments');
        $this->assertSame(1, (int) OrderCoupon::get()->byID($this->coupon->ID)->TimesUsed, 'discount counter increments');

        // a code that was never redeemed stays at zero
        $other = $this->childCode('CHILDB');
        $this->assertSame(0, (int) $other->TimesUsed);
    }

    public function testPerCodeUseLimitBlocksSecondUse(): void
    {
        $this->childCode('LIMITED', useLimit: 1);

        // one paid redemption of the code
        Calculator::create($this->paid, ['CouponCodes' => ['LIMITED']])->calculate();

        // a different order can no longer use it (limit reached)
        $this->assertFalse(
            $this->coupon->validateOrder($this->unpaid, ['CouponCodes' => ['LIMITED']]),
            'a code at its use limit is rejected'
        );

        // a sibling code on the same coupon is unaffected
        $this->childCode('FRESH', useLimit: 1);
        $this->assertTrue(
            $this->coupon->validateOrder($this->unpaid, ['CouponCodes' => ['FRESH']]),
            (string) $this->coupon->getMessage()
        );
    }

    public function testPerCustomerUseLimit(): void
    {
        $joe = $this->objFromFixture(Member::class, 'joebloggs');
        $bob = $this->objFromFixture(Member::class, 'bobjones');

        // the paid order (joebloggs) has used the coupon once
        $this->coupon->PerCustomerUseLimit = 1;
        $this->coupon->write();
        Calculator::create($this->paid, ['CouponCodes' => ['PRIMARY']])->calculate();

        // same customer, a new order: blocked
        $this->cart->MemberID = $joe->ID;
        $this->cart->write();
        $this->assertFalse(
            $this->coupon->validateOrder($this->cart, ['CouponCodes' => ['PRIMARY']]),
            'the same customer is blocked after reaching the per-customer limit'
        );

        // a different customer: allowed
        $this->cart->MemberID = $bob->ID;
        $this->cart->write();
        $this->assertTrue(
            $this->coupon->validateOrder($this->cart, ['CouponCodes' => ['PRIMARY']]),
            (string) $this->coupon->getMessage()
        );
    }

    public function testReconcileBackfillsStoredCounter(): void
    {
        $this->childCode('BACKFILL');
        Calculator::create($this->paid, ['CouponCodes' => ['BACKFILL']])->calculate();

        // simulate pre-existing paid orders (counter never maintained): reconcile from the truth
        $this->coupon->reconcileTimesUsed();
        $code = DiscountCode::get_by_code('BACKFILL');
        $code->reconcileTimesUsed();

        $this->assertSame(1, (int) $this->coupon->TimesUsed);
        $this->assertSame(1, (int) $code->TimesUsed);
    }
}
