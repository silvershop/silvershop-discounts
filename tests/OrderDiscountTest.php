<?php

namespace SilverShop\Discounts\Tests;

use SilverStripe\Dev\SapphireTest;
use SilverShop\Tests\ShopTest;
use SilverShop\Model\Order;
use SilverShop\Discounts\Model\OrderDiscount;
use SilverShop\Discounts\Model\Discount;
use SilverStripe\Omnipay\Model\Payment;

class OrderDiscountTest extends SapphireTest
{
    protected static $fixture_file = [
        'Discounts.yml',
        'shop.yml'
    ];

    protected Order $cart;

    protected function setUp(): void
    {
        parent::setUp();
        ShopTest::setConfiguration();
        $this->cart = $this->objFromFixture(Order::class, 'cart');
    }

    /**
     * Check that available discounts are matched to the current order.
     */
    public function testManyMatches(): void
    {
        OrderDiscount::create(
            [
                'Title' => '10% off',
                'Type' => 'Percent',
                'Percent' => 0.10
            ]
        )->write();
        OrderDiscount::create(
            [
                'Title' => '$5 off',
                'Type' => 'Amount',
                'Amount' => 5
            ]
        )->write();
        $arrayList = OrderDiscount::get_matching($this->cart);
        $this->assertListEquals(
            [
                ['Title' => '10% off'],
                ['Title' => '$5 off'],
            ],
            $arrayList
        );
    }

    public function testPercent(): void
    {
        OrderDiscount::create(
            [
                'Title' => '10% off',
                'Type' => 'Percent',
                'Percent' => 0.10
            ]
        )->write();
        $this->assertListEquals(
            [
                ['Title' => '10% off']
            ],
            OrderDiscount::get_matching($this->cart)
        );
    }

    public function testAmount(): void
    {
        OrderDiscount::create(
            [
                'Title' => '$5 off',
                'Type' => 'Amount',
                'Amount' => 5
            ]
        )->write();
        $this->assertListEquals(
            [
                ['Title' => '$5 off']
            ],
            OrderDiscount::get_matching($this->cart)
        );
    }

    public function testUseCount(): void
    {
        //check that order with payment started counts as a use
        $orderDiscount = $this->objFromFixture(OrderDiscount::class, 'paymentused');
        $payment = $this->objFromFixture(Payment::class, 'paymentstarted_recent');

        // set timeout to 60 minutes
        Discount::config()->unpaid_use_timeout = 60;
        //set payment to be created 20 min ago
        $payment->Created = date('Y-m-d H:i:s', strtotime('-20 minutes'));
        $payment->write();
        $this->assertSame(1, $orderDiscount->getUseCount());
        //set payment ot be created 2 days ago
        $payment->Created = date('Y-m-d H:i:s', strtotime('-2 days'));
        $payment->write();
        $this->assertSame(0, $orderDiscount->getUseCount());
        //failed payments should be ignored
        $payment->Created = date('Y-m-d H:i:s', strtotime('-20 minutes'));
        $payment->Status = 'Void';
        $payment->write();
        $this->assertSame(0, $orderDiscount->getUseCount());
    }
}
