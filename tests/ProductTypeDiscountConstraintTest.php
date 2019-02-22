<?php

namespace SilverShop\Discounts\Tests;

use SilverShop\Discounts\Calculator;
use SilverStripe\Dev\SapphireTest;
use SilverShop\Tests\ShopTest;
use SilverStripe\Core\Config\Config;
use SilverShop\Model\Order;
use SilverShop\Page\Product;
use SilverShop\Discounts\Model\Discount;
use SilverShop\Discounts\Page\GiftVoucherProduct;
use SilverShop\Discounts\Model\OrderDiscount;

class ProductTypeDiscountConstraintTest extends SapphireTest
{

    protected static $fixture_file = [
        'shop.yml',
        'GiftVouchers.yml'
    ];

    public function setUp()
    {
        parent::setUp();
        ShopTest::setConfiguration();
        Config::inst()->update(
            Discount::class,
            "constraints",
            [
            "ProductTypeDiscountConstraint"
            ]
        );

        $this->cart = $this->objFromFixture(Order::class, "cart");
        $this->giftcart = $this->objFromFixture(Order::class, "giftcart");

        $this->socks = $this->objFromFixture(Product::class, "socks");
        $this->socks->publishRecursive();
        $this->tshirt = $this->objFromFixture(Product::class, "tshirt");
        $this->tshirt->publishRecursive();
        $this->mp3player = $this->objFromFixture(Product::class, "mp3player");
        $this->mp3player->publishRecursive();

        $this->voucher = $this->objFromFixture(GiftVoucherProduct::class, "10fixed");
        $this->voucher->publish("Stage", "Live");
    }

    public function testProducts()
    {
        $discount = OrderDiscount::create(
            [
            "Title" => "20% off each products",
            "Percent" => 0.2,
            "ProductTypes" => Product::class
            ]
        );
        $discount->write();

        $this->assertTrue($discount->validateOrder($this->cart));
        $this->assertFalse($discount->validateOrder($this->giftcart));
    }
}
