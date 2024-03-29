<?php

namespace SilverShop\Discounts\Tests;

use SilverShop\Discounts\Page\GiftVoucherProduct;
use SilverShop\Discounts\Page\GiftVoucherProductController;
use SilverStripe\Dev\SapphireTest;

class GiftVoucherTest extends SapphireTest
{
    protected static $fixture_file = [
        'GiftVouchers.yml'
    ];

    public function setUp(): void
    {
        parent::setUp();

        $this->variable = $this->objFromFixture(GiftVoucherProduct::class, 'variable');
        $this->variable->copyVersionToStage('Stage', 'Live');

        $this->fixed10 = $this->objFromFixture(GiftVoucherProduct::class, '10fixed');
        $this->fixed10->copyVersionToStage('Stage', 'Live');
    }

    public function testCusomisableVoucher()
    {
        $controller =  new GiftVoucherProductController($this->variable);
        $form = $controller->Form();

        $form->loadDataFrom(
            $data = [
            'UnitPrice' => 32.35,
            'Quantity' => 1
            ]
        );
        $this->assertTrue($form->validationResult()->isValid(), 'Voucher form is valid');

        $form->loadDataFrom(
            [
            'UnitPrice' => 3,
            'Quantity' => 5
            ]
        );
        $this->assertFalse($form->validationResult()->isValid(), 'Tested unit price is below minimum amount');

        $form->loadDataFrom(
            [
            'UnitPrice' => 0,
            'Quantity' => 5
            ]
        );
        $this->assertFalse($form->validationResult()->isValid(), 'Tested unit price is zero');
    }

    public function testFixedVoucher()
    {
        $controller =  new GiftVoucherProductController($this->fixed10);
        $form = $controller->Form();
        $form->loadDataFrom(
            [
            'Quantity' => 2
            ]
        );

        $this->assertTrue($form->validationResult()->isValid(), 'Valid voucher');
    }

    public function testCreateCoupon()
    {
        $item = $this->variable->createItem(
            1,
            [
            'UnitPrice' => 15.00
            ]
        );

        $coupon = $item->createCoupon();

        $this->assertEquals($coupon->Amount, 15, 'Coupon value is $15, as per order item');
        $this->assertEquals($coupon->Type, 'Amount', "Coupon type is 'Amount'");
    }
}
