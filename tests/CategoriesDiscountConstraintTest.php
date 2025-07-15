<?php

namespace SilverShop\Discounts\Tests;

use SilverShop\Discounts\Calculator;
use SilverShop\Discounts\Model\OrderDiscount;
use SilverShop\Model\Order;
use SilverShop\Model\Variation\AttributeValue;
use SilverShop\Model\Variation\Variation;
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

    protected Product $extremekite2000;

    protected Variation $extremekite2000_redsmall;

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

        $this->extremekite2000 = $this->objFromFixture(Product::class, "extremekite2000");
        $this->extremekite2000->publishRecursive();

        $this->extremekite2000_redsmall = $this->objFromFixture(Variation::class, "extremekite2000_redsmall");
        $this->extremekite2000_redsmall->publishRecursive();

        $this->cart = $this->objFromFixture(Order::class, 'cart');
        $this->othercart = $this->objFromFixture(Order::class, 'othercart');
        $this->kitecart = $this->objFromFixture(Order::class, 'kitecart');
    }

    public function testCategoryDiscount(): void
    {
        $clothing_category = $this->objFromFixture(ProductCategory::class, "clothing");
        $this->assertEquals(
            2,
            $clothing_category->ProductsShowable()->count(),
            'There is one product under the clothing Category'
        );

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
            'The Order contains a t-shirt. ' . $orderDiscount->getMessage()
        );
        $calculator = Calculator::create($this->cart);
        $this->assertEqualsWithDelta(0.4, $calculator->calculate(), PHP_FLOAT_EPSILON, '5% discount for socks in cart');

        $this->assertFalse($orderDiscount->validateOrder($this->othercart), 'Order does not contain clothing');
        $calculator = Calculator::create($this->othercart);
        $this->assertSame(0, $calculator->calculate(), 'No discount, because no product in category');
    }

    public function testCategoryDiscountWhenProductsHaveVariations(): void
    {
        $this->assertEquals(
            'Kites',
            $this->objFromFixture(ProductCategory::class, "kites")->Title,
            'The Category Kites has Kites as a title'
        );

        $this->assertEquals(
            1,
            $this->objFromFixture(ProductCategory::class, "kites")->ProductsShowable()->count(),
            'There is one product under the Kites Category'
        );

        $this->assertEquals(
            1,
            $this->kitecart->Items()->count(),
            'There is one item in the cart'
        );

        $orderDiscount = OrderDiscount::create(
            [
                'Title' => '5% off kites',
                'Type' => 'Percent',
                'Percent' => 0.05
            ]
        );
        $orderDiscount->write();

        $orderDiscount->Categories()->add(
            $this->objFromFixture(ProductCategory::class, "kites")
        );

        $this->assertEquals(
            1,
            $orderDiscount->Categories()->count(),
            'There is a product category listed under $orderDiscounts'
        );

        $this->assertTrue(
            $orderDiscount->validateOrder($this->kitecart),
            'The Order contains a kite. ' . $orderDiscount->getMessage()
        );
        $calculator = Calculator::create($this->kitecart);

        $attributeValue = $this->objFromFixture(AttributeValue::class, 'color_red');
        $attributes = [$attributeValue->ID];
        $variation = $this->extremekite2000->getVariationByAttributes($attributes);
        $this->assertInstanceOf(
            Variation::class,
            $variation,
            'Variation exists'
        );
        $this->assertEquals(
            35,
            $variation->Product()->sellingPrice(),
            'Variation price is $35, price of the red kite before discount'
        );

        $this->assertEqualsWithDelta(1.75, $calculator->calculate(), PHP_FLOAT_EPSILON, '5% discount for kite in cart.  Uses variations.');
    }
}
