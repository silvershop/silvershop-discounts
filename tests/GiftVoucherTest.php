<?php

namespace SilverShop\Discounts\Tests;

use SilverStripe\Dev\SapphireTest;
use SilverShop\Discounts\Model\GiftVoucherProduct;
use SilverShop\Discounts\Model\GiftVoucherProductController;

class GiftVoucherTest extends SapphireTest
{

    protected static $fixture_file = [
        'GiftVouchers.yml'
    ];

    public function setUp()
    {
        parent::setUp();

        $this->variable = $this->objFromFixture(GiftVoucherProduct::class, "variable");
        $this->variable->publish('Stage', 'Live');

        $this->fixed10 = $this->objFromFixture(GiftVoucherProduct::class, "10fixed");
        $this->fixed10->publish('Stage', 'Live');
    }

    public function testCusomisableVoucher()
    {
        $controller =  new GiftVoucherProductController($this->variable);
        $form = $controller->Form();

        $form->loadDataFrom(
            $data = [
            "UnitPrice" => 32.35,
            "Quantity" => 1
            ]
        );
        $this->assertTrue($form->validate(), "Voucher form is valid");

        $form->loadDataFrom(
            [
            "UnitPrice" => 3,
            "Quantity" => 5
            ]
        );
        $this->assertFalse($form->validate(), "Tested unit price is below minimum amount");

        $form->loadDataFrom(
            [
            "UnitPrice" => 0,
            "Quantity" => 5
            ]
        );
        $this->assertFalse($form->validate(), "Tested unit price is zero");
    }

    public function testFixedVoucher()
    {
        $controller =  new GiftVoucherProductController($this->fixed10);
        $form = $controller->Form();
        $form->loadDataFrom(
            [
            "Quantity" => 2
            ]
        );

        $this->assertTrue($form->validate(), "Valid voucher");
    }

    public function testCreateCoupon()
    {
        $item = $this->variable->createItem(
            1,
            [
            "UnitPrice" => 15.00
            ]
        );

        $coupon = $item->createCoupon();

        $this->assertEquals($coupon->Amount, 15, "Coupon value is $15, as per order item");
        $this->assertEquals($coupon->Type, "Amount", "Coupon type is 'Amount'");
    }
}
