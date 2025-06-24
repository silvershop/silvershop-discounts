<?php

namespace SilverShop\Discounts\Tests;

use SilverShop\Model\Order;
use SilverShop\Page\Product;
use SilverStripe\Dev\SapphireTest;
use SilverShop\Tests\ShopTest;
use SilverStripe\Core\Config\Config;
use SilverShop\Discounts\Model\Discount;
use SilverShop\Discounts\Page\GiftVoucherProduct;
use SilverShop\Discounts\Model\OrderDiscount;

class ProductTypeDiscountConstraintTest extends SapphireTest
{

    protected static $fixture_file = [
        'shop.yml',
        'GiftVouchers.yml'
    ];

    protected GiftVoucherProduct $voucher;

    protected Order $cart;

    protected Order $giftcart;

    protected Product $mp3player;

    protected Product $socks;

    protected Product $tshirt;

    protected function setUp(): void
    {
        parent::setUp();
        ShopTest::setConfiguration();
        Config::inst()->merge(
            Discount::class,
            'constraints',
            ['ProductTypeDiscountConstraint']
        );

        $this->cart = $this->objFromFixture(Order::class, 'cart');
        $this->giftcart = $this->objFromFixture(Order::class, 'giftcart');

        $this->socks = $this->objFromFixture(Product::class, 'socks');
        $this->socks->publishRecursive();

        $this->tshirt = $this->objFromFixture(Product::class, 'tshirt');
        $this->tshirt->publishRecursive();

        $this->mp3player = $this->objFromFixture(Product::class, 'mp3player');
        $this->mp3player->publishRecursive();

        $this->voucher = $this->objFromFixture(GiftVoucherProduct::class, '10fixed');
        $this->voucher->copyVersionToStage('Stage', 'Live');
    }

    public function testProducts(): void
    {
        $orderDiscount = OrderDiscount::create(
            [
                'Title' => '20% off each products',
                'Percent' => 0.2,
                'ProductTypes' => Product::class
            ]
        );
        $orderDiscount->write();

        $this->assertTrue($orderDiscount->validateOrder($this->cart));
        $this->assertFalse($orderDiscount->validateOrder($this->giftcart));
    }
}
