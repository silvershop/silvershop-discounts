<?php

namespace SilverShop\Discounts\Tests;

use SilverShop\Model\Order;
use SilverStripe\Dev\FunctionalTest;
use SilverShop\Page\Product;
use SilverShop\Page\CheckoutPage;
use SilverShop\Page\CheckoutPageController;
use SilverShop\Discounts\Model\OrderCoupon;
use SilverShop\Discounts\Form\CouponForm;
use SilverStripe\Control\Controller;
use SilverStripe\Security\Member;

class CouponFormTest extends FunctionalTest
{

    protected static $fixture_file = [
        'shop.yml',
        'Page.yml'
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->objFromFixture(Product::class, 'socks')->publishRecursive();
    }

    public function testCouponForm(): void
    {
        $member = $this->objFromFixture(Member::class, "joebloggs");
        $this->logInAs($member);
        OrderCoupon::create(
            [
                'Title' => '40% off each item',
                'Code' => '5B97AA9D75',
                'Type' => 'Percent',
                'Percent' => 0.40
            ]
        )->write();

        $checkoutpage = $this->objFromFixture(CheckoutPage::class, 'checkout');
        $checkoutpage->publishRecursive();

        $checkoutPageController = CheckoutPageController::create($checkoutpage);
        $order =  $this->objFromFixture(Order::class, 'cart');
        $couponForm = CouponForm::create($checkoutPageController, CouponForm::class, $order);
        $data = ['Code' => '5B97AA9D75'];
        $couponForm->loadDataFrom($data);

        $valid = $couponForm->validationResult()->isValid();
        $this->assertTrue($valid);

        $errors = $couponForm->getValidator()->getErrors();
        $this->assertTrue($valid, print_r($errors, true));

        $couponForm->applyCoupon($data, $couponForm);
        $configData = $couponForm->config->getData();
        $this->assertSame('5B97AA9D75', $configData['Code']);

        $coupon = Controller::curr()->getRequest()->getSession()->get('cart.couponcode');
        $this->assertSame('5B97AA9D75', $coupon);

        $couponForm->removeCoupon([], $couponForm);
        $fresh_copy_of_order = Order::get()->byID($order->ID);
        $this->assertEmpty($fresh_copy_of_order->CouponCode);
    }
}
