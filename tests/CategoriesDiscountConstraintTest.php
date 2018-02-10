<?php

namespace SilverShop\Discounts\Tests;


use SilverShop\Discount\Calculator;
use SilverStripe\Dev\SapphireTest;
use SilverShop\Tests\ShopTest;

use SilverShop\Discounts\Model\OrderDiscount;



class CategoriesDiscountConstraintTest extends SapphireTest{

    protected static $fixture_file = [
        'shop.yml',
        'Carts.yml'
    ];

    public function setUp() {
        parent::setUp();
        ShopTest::setConfiguration();

        $this->socks = $this->objFromFixture("Product", "socks");
        $this->socks->publish("Stage", "Live");
        $this->tshirt = $this->objFromFixture("Product", "tshirt");
        $this->tshirt->publish("Stage", "Live");
        $this->mp3player = $this->objFromFixture("Product", "mp3player");
        $this->mp3player->publish("Stage", "Live");

        $this->cart = $this->objFromFixture(Order::class, "cart");
        $this->othercart = $this->objFromFixture(Order::class, "othercart");
        $this->kitecart = $this->objFromFixture(Order::class, "kitecart");
    }

    public function testCategoryDiscount() {
        $discount = OrderDiscount::create([
            "Title" => "5% off clothing",
            "Type" => "Percent",
            "Percent" => 0.05
        ]);
        $discount->write();
        $discount->Categories()->add($this->objFromFixture("ProductCategory", "clothing"));

        $this->assertTrue($discount->validateOrder($this->cart), "Order contains a t-shirt. ".$discount->getMessage());
        $calculator = new Calculator($this->cart);
        $this->assertEquals($calculator->calculate(), 0.4, "5% discount for socks in cart");

        $this->assertFalse($discount->validateOrder($this->othercart), "Order does not contain clothing");
        $calculator = new Calculator($this->othercart);
        $this->assertEquals($calculator->calculate(), 0, "No discount, because no product in category");

        $discount->Categories()->removeAll();

        $discount->Categories()->add($this->objFromFixture("ProductCategory", "kites"));
        $this->assertTrue($discount->validateOrder($this->kitecart), "Order contains a kite. ".$discount->getMessage());
        $calculator = new Calculator($this->kitecart);
        $this->assertEquals($calculator->calculate(), 1.75, "5% discount for kite in cart");
    }

}
