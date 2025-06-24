<?php

namespace SilverShop\Discounts\Tests;

use SilverShop\Discounts\Calculator;
use SilverStripe\Dev\SapphireTest;
use SilverShop\Tests\ShopTest;
use SilverShop\Discounts\Model\OrderDiscount;
use SilverShop\Discounts\Model\OrderCoupon;
use SilverShop\Page\Product;
use SilverShop\Model\Order;
use SilverShop\Model\Product\OrderItem;

class ProductsDiscountConstraintTest extends SapphireTest
{
    protected static $fixture_file = [
        'shop.yml'
    ];

    protected Order $cart;

    protected Order $megacart;

    protected Order $modifiedcart;

    protected Order $othercart;

    protected Order $placedorder;

    protected Product $mp3player;

    protected Product $socks;

    protected Product $tshirt;

    protected function setUp(): void
    {
        parent::setUp();

        ShopTest::setConfiguration();

        $this->cart = $this->objFromFixture(Order::class, 'cart');
        $this->placedorder = $this->objFromFixture(Order::class, 'unpaid');
        $this->megacart = $this->objFromFixture(Order::class, 'megacart');
        $this->modifiedcart = $this->objFromFixture(Order::class, 'modifiedcart');

        $this->socks = $this->objFromFixture(Product::class, 'socks');
        $this->socks->publishRecursive();

        $this->tshirt = $this->objFromFixture(Product::class, 'tshirt');
        $this->tshirt->publishRecursive();

        $this->mp3player = $this->objFromFixture(Product::class, 'mp3player');
        $this->mp3player->publishRecursive();
    }

    public function testProducts(): void
    {
        $orderDiscount = OrderDiscount::create(
            [
                'Title' => '20% off each selected products',
                'Percent' => 0.2
            ]
        );
        $orderDiscount->write();
        $orderDiscount->Products()->add($this->objFromFixture(Product::class, 'tshirt'));
        $this->assertFalse($orderDiscount->validateOrder($this->cart));
        //no products match
        $this->assertListEquals([], OrderDiscount::get_matching($this->cart));
        //add product discount list
        $orderDiscount->Products()->add($this->objFromFixture(Product::class, 'tshirt'));
        $this->assertFalse($orderDiscount->validateOrder($this->cart));
        //no products match
        $this->assertListEquals([], OrderDiscount::get_matching($this->cart));
    }

    public function testProductsCoupon(): void
    {
        $orderCoupon = OrderCoupon::create(
            [
                'Title' => 'Selected products',
                'Code' => 'PRODUCTS',
                'Percent' => 0.2
            ]
        );
        $orderCoupon->write();
        $orderCoupon->Products()->add($this->objFromFixture(Product::class, 'tshirt'));

        $calculator = new Calculator(
            $this->placedorder,
            [
                'CouponCode' => $orderCoupon->Code
            ]
        );

        $this->assertSame(20, (int) $calculator->calculate());
        //add another product to coupon product list
        $orderCoupon->Products()->add($this->objFromFixture(Product::class, 'mp3player'));
        $this->assertSame(100, (int) $calculator->calculate());
    }

    public function testProductDiscount(): void
    {
        $discount = OrderDiscount::create(
            [
                'Title' => '20% off each selected products',
                'Percent' => 0.2,
                'Active' => 1,
                'ExactProducts' => 1
            ]
        );
        $discount->write();
        //selected products
        $discount->Products()->add($this->socks);
        $discount->Products()->add($this->tshirt);
        //should work for megacart
        //20 * socks($8) = 160 ...20% off each = 32
        //10 * tshirt($25) = 250 ..20% off each  = 50
        //2 * mp3player($200) = 400 ..nothing off = 0
        //total discount: 82
        $calculator = new Calculator($this->megacart);
        $this->assertSame(82, (int) $calculator->calculate(), '20% off selected products');
        //no discount for cart
        $calculator = new Calculator($this->cart);
        $this->assertSame(0, (int) $calculator->calculate(), '20% off selected products');
        //no discount for modifiedcart
        $calculator = new Calculator($this->modifiedcart);
        $this->assertSame(0, (int) $calculator->calculate(), '20% off selected products');

        //partial match
        $discount->ExactProducts = 0;
        $discount->write();
        //total discount: 82
        $calculator = new Calculator($this->megacart);
        $this->assertSame(82, (int) $calculator->calculate(), '20% off selected products');
        //discount for cart: 32 (just socks)
        $calculator = new Calculator($this->cart);
        $this->assertEqualsWithDelta(1.6, $calculator->calculate(), PHP_FLOAT_EPSILON);
        //no discount for modified cart
        $calculator = new Calculator($this->modifiedcart);
        $this->assertSame(0, (int) $calculator->calculate(), '20% off selected products');

        //get individual item discounts
        $discount = $this->objFromFixture(OrderItem::class, 'megacart_socks')
            ->Discounts()->first();
        $this->assertSame(32, (int) $discount->DiscountAmount);
    }


    public function testProductDiscountWithUnpublishedProduct(): void
    {
        $product = $this->socks->duplicate();
        $product->writeToStage('Stage');
        $product->doUnpublish();

        $orderDiscount = OrderDiscount::create(
            [
                'Title' => '20% off each selected products',
                'Percent' => 0.2,
                'Active' => 1,
                'ExactProducts' => 1
            ]
        );

        $orderDiscount->write();
        $orderDiscount->Products()->add($product);

        $order = $this->objFromFixture(Order::class, 'othercart');
        $calculator = new Calculator($order);

        $this->assertSame(0, (int) $calculator->calculate(), "Product coupon does not apply as draft products don't exist");
    }
}
