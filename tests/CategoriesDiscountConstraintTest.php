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

    protected Order $cart;

    protected Order $emptycart;

    protected Order $kitecart;

    protected Order $megacart;

    protected Order $modifiedcart;

    protected Order $othercart;

    protected Product $socks;

    protected Product $tshirt;

    protected Product $mp3player;

    protected function setUp(): void
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

    public function testCategoryDiscount(): void
    {
        $orderDiscount = OrderDiscount::create(
            [
                'Title' => '5% off clothing',
                'Type' => 'Percent',
                'Percent' => 0.05
            ]
        );
        $orderDiscount->write();
        $orderDiscount->Categories()->add(
            $this->objFromFixture(ProductCategory::class, "clothing")
        );

        $this->assertTrue(
            $orderDiscount->validateOrder($this->cart),
            'Order contains a t-shirt. ' . $orderDiscount->getMessage()
        );
        $calculator = new Calculator($this->cart);
        $this->assertEqualsWithDelta(0.4, $calculator->calculate(), PHP_FLOAT_EPSILON);

        $this->assertFalse($orderDiscount->validateOrder($this->othercart), 'Order does not contain clothing');
        $calculator = new Calculator($this->othercart);
        $this->assertSame(0, $calculator->calculate(), 'No discount, because no product in category');

        $orderDiscount->Categories()->removeAll();

        $orderDiscount->Categories()->add(
            $this->objFromFixture(ProductCategory::class, "kites")
        );

        $this->assertTrue(
            $orderDiscount->validateOrder($this->kitecart),
            "Order contains a kite. " . $orderDiscount->getMessage()
        );
        $calculator = new Calculator($this->kitecart);
        $this->assertEqualsWithDelta(1.75, $calculator->calculate(), PHP_FLOAT_EPSILON);
    }
}
