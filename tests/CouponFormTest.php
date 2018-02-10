<?php

namespace SilverShop\Discounts\Tests;

use SilverStripe\Dev\FunctionalTest;

use CheckoutPage_Controller;

use SilverStripe\Control\Session;
use SilverShop\Discounts\Model\OrderCoupon;
use SilverShop\Discounts\Form\CouponForm;

class CouponFormTest extends FunctionalTest {

    protected static $fixture_file = [
        'shop.yml',
        'fixtures/Pages.yml'
    ];

    function setUp() {
        parent::setUp();

        $this->objFromFixture("Product", "socks")
            ->publish("Stage", "Live");
    }

    function testCouponForm() {

        OrderCoupon::create([
            "Title" => "40% off each item",
            "Code" => "5B97AA9D75",
            "Type" => "Percent",
            "Percent" => 0.40
        ])->write();

        $checkoutpage = $this->objFromFixture("CheckoutPage", "checkout");
        $checkoutpage->publish("Stage", "Live");
        $controller = new CheckoutPage_Controller($checkoutpage);
        $order =  $this->objFromFixture(Order::class, "cart");
        $form = new CouponForm($controller, CouponForm::class, $order);
        $data = ["Code" => "5B97AA9D75"];
        $form->loadDataFrom($data);
        $this->assertTrue($form->validate());
        $form->applyCoupon($data, $form);
        $this->assertEquals("5B97AA9D75", Session::get("cart.couponcode"));
        $form->removeCoupon([], $form);
    }

}
