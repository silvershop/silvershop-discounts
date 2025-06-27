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

    protected GiftVoucherProduct $fixed10GiftVoucherProduct;

    protected GiftVoucherProduct $variableGiftVoucherProduct;

    protected function setUp(): void
    {
        parent::setUp();

        $this->variableGiftVoucherProduct = $this->objFromFixture(GiftVoucherProduct::class, 'variable');
        $this->variableGiftVoucherProduct->copyVersionToStage('Stage', 'Live');

        $this->fixed10GiftVoucherProduct = $this->objFromFixture(GiftVoucherProduct::class, '10fixed');
        $this->fixed10GiftVoucherProduct->copyVersionToStage('Stage', 'Live');
    }

    public function testCusomisableVoucher(): void
    {
        $giftVoucherProductController =  GiftVoucherProductController::create($this->variableGiftVoucherProduct);
        $form = $giftVoucherProductController->Form();

        $form->loadDataFrom(
            [
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

    public function testFixedVoucher(): void
    {
        $giftVoucherProductController =  GiftVoucherProductController::create($this->fixed10GiftVoucherProduct);
        $form = $giftVoucherProductController->Form();
        $form->loadDataFrom(
            ['Quantity' => 2]
        );

        $this->assertTrue($form->validationResult()->isValid(), 'Valid voucher');
    }

    public function testCreateCoupon(): void
    {
        $orderItem = $this->variableGiftVoucherProduct->createItem(
            1,
            ['UnitPrice' => 15.00]
        );

        $coupon = $orderItem->createCoupon();

        $this->assertEqualsWithDelta(15.00, $coupon->Amount, PHP_FLOAT_EPSILON);
        $this->assertSame('Amount', $coupon->Type, "Coupon type is 'Amount'");
    }
}
