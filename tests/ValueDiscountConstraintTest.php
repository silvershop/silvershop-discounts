<?php

namespace SilverShop\Discounts\Tests;

use SilverShop\Discounts\Calculator;
use SilverStripe\Core\Extension;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Dev\TestOnly;
use SilverShop\Tests\ShopTest;
use SilverShop\Discounts\Model\OrderCoupon;
use SilverShop\Model\Order;

/**
 * Raises the amount used for tax-inclusive minimum-order checks (see ValueDiscountConstraint).
 */
class ValueDiscountConstraintTestOrderExtension extends Extension implements TestOnly
{
    public function updateMinimumOrderValueComparisonAmount(\stdClass $context): void
    {
        $context->amount += 100;
    }
}

class ValueDiscountConstraintTest extends SapphireTest
{

    protected Order $placedorder;

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

    public function testMinOrderValueTaxInclusiveUsesAugmentedSubtotal(): void
    {
        Order::add_extension(ValueDiscountConstraintTestOrderExtension::class);
        try {
            // othercart fixture line subtotal is 200; inclusive compare amount is 300 with test extension.
            $coupon = OrderCoupon::create(
                [
                    'Title' => 'High threshold inclusive',
                    'Code' => 'INC350',
                    'Type' => 'Amount',
                    'Amount' => 10,
                    'ForItems' => 0,
                    'ForCart' => 1,
                    'MinOrderValue' => 350,
                    'MinOrderValueTaxInclusive' => true,
                ]
            );
            $coupon->write();

            $context = ['CouponCode' => $coupon->Code];
            $this->assertFalse(
                $coupon->validateOrder($this->othercart, $context),
                '300 augmented subtotal is below 350 inclusive minimum'
            );

            $coupon2 = OrderCoupon::create(
                [
                    'Title' => 'Lower threshold inclusive',
                    'Code' => 'INC250',
                    'Type' => 'Amount',
                    'Amount' => 10,
                    'ForItems' => 0,
                    'ForCart' => 1,
                    'MinOrderValue' => 250,
                    'MinOrderValueTaxInclusive' => true,
                ]
            );
            $coupon2->write();

            $context2 = ['CouponCode' => $coupon2->Code];
            $this->assertTrue(
                $coupon2->validateOrder($this->othercart, $context2),
                '300 augmented subtotal meets 250 inclusive minimum'
            );

            $coupon3 = OrderCoupon::create(
                [
                    'Title' => 'Same threshold exclusive',
                    'Code' => 'EX250',
                    'Type' => 'Amount',
                    'Amount' => 10,
                    'ForItems' => 0,
                    'ForCart' => 1,
                    'MinOrderValue' => 250,
                    'MinOrderValueTaxInclusive' => false,
                ]
            );
            $coupon3->write();

            $context3 = ['CouponCode' => $coupon3->Code];
            $this->assertFalse(
                $coupon3->validateOrder($this->othercart, $context3),
                '200 line subtotal does not meet 250 exclusive minimum (extension ignored)'
            );
        } finally {
            Order::remove_extension(ValueDiscountConstraintTestOrderExtension::class);
        }
    }
}
