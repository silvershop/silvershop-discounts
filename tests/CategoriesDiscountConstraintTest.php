<?php

namespace SilverShop\Discounts\Tests;

use SilverShop\Discounts\Calculator;
use SilverShop\Discounts\Model\OrderDiscount;
use SilverShop\Model\Order;
use SilverShop\Page\Product;
use SilverShop\Page\ProductCategory;
use SilverShop\Tests\ShopTest;
use SilverStripe\Dev\SapphireTest;

class CategoriesDiscountConstraintTest extends SapphireTest
{

    protected static $fixture_file = [
        'shop.yml',
        'vendor/silvershop/core/tests/php/Fixtures/Carts.yml'
    ];

    public function setUp(): void
    {
        parent::setUp();
        ShopTest::setConfiguration();

        $this->socks = $this->objFromFixture(Product::class, "socks");
        $this->socks->publishRecursive();
        $this->tshirt = $this->objFromFixture(Product::class, "tshirt");
        $this->tshirt->publishRecursive();
        $this->mp3player = $this->objFromFixture(Product::class, "mp3player");
        $this->mp3player->publishRecursive();

        $this->cart = $this->objFromFixture(Order::class, 'cart');
        $this->othercart = $this->objFromFixture(Order::class, 'othercart');
        $this->kitecart = $this->objFromFixture(Order::class, 'kitecart');
    }

    public function testCategoryDiscount()
    {
        $discount = OrderDiscount::create(
            [
                'Title' => '5% off clothing',
                'Type' => 'Percent',
                'Percent' => 0.05
            ]
        );
        $discount->write();
        $discount->Categories()->add(
            $this->objFromFixture(ProductCategory::class, "clothing")
        );

        $this->assertTrue($discount->validateOrder($this->cart),
            'Order contains a t-shirt. ' . $discount->getMessage());
        $calculator = new Calculator($this->cart);
        $this->assertEquals($calculator->calculate(), 0.4, '5% discount for socks in cart');

        $this->assertFalse($discount->validateOrder($this->othercart), 'Order does not contain clothing');
        $calculator = new Calculator($this->othercart);
        $this->assertEquals($calculator->calculate(), 0, 'No discount, because no product in category');

        $discount->Categories()->removeAll();

        $discount->Categories()->add(
            $this->objFromFixture(ProductCategory::class, "kites")
        );

        $this->assertTrue($discount->validateOrder($this->kitecart),
            "Order contains a kite. " . $discount->getMessage());
        $calculator = new Calculator($this->kitecart);
        $this->assertEquals($calculator->calculate(), 1.75, '5% discount for kite in cart');
    }
}
