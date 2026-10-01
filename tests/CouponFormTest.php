<?php

namespace SilverShop\Discounts\Tests;

use SilverShop\Model\Order;
use SilverStripe\Dev\FunctionalTest;
use SilverShop\Page\Product;
use SilverShop\Page\CheckoutPage;
use SilverShop\Page\CheckoutPageController;
use SilverShop\Discounts\Model\OrderCoupon;
use SilverShop\Discounts\Form\CouponForm;
use SilverShop\Discounts\Checkout\AppliedCouponCodes;
use SilverShop\Discounts\Checkout\CouponCheckoutComponent;
use SilverStripe\Core\Validation\ValidationException;
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

        $validationResult = $couponForm->validate();
        $valid = $validationResult->isValid();
        $this->assertTrue($valid);

        $errors = $validationResult->getMessages();
        $this->assertEmpty($errors, print_r($errors, true));

        $couponForm->applyCoupon($data, $couponForm);
        $configData = $couponForm->config->getData();
        $this->assertSame('5B97AA9D75', $configData['Code']);

        $controller = Controller::curr();
        $this->assertNotNull($controller);
        $coupon = $controller->getRequest()->getSession()->get('cart.couponcode');
        $this->assertSame('5B97AA9D75', $coupon);

        $couponForm->removeCoupon([], $couponForm);
        $fresh_copy_of_order = Order::get()->byID($order->ID);
        $this->assertNotNull($fresh_copy_of_order);
        $this->assertEmpty($fresh_copy_of_order->CouponCode);
    }

    public function testStackableCouponsAccumulate(): void
    {
        $this->createCoupon('STACKA', true);
        $this->createCoupon('STACKB', true);

        $order = $this->objFromFixture(Order::class, 'cart');
        $component = CouponCheckoutComponent::create();

        $this->applyCode($component, $order, 'STACKA');
        $this->applyCode($component, $order, 'stackb');

        $this->assertSame(['STACKA', 'STACKB'], AppliedCouponCodes::get());
        $this->assertSame('STACKB', $component->getData($order)['Code']);

        // re-entering an applied code doesn't duplicate it
        $this->applyCode($component, $order, 'STACKA');
        $this->assertSame(['STACKA', 'STACKB'], AppliedCouponCodes::get());

        AppliedCouponCodes::remove('STACKA');
        $this->assertSame(['STACKB'], AppliedCouponCodes::get());
    }

    public function testNonStackableCouponReplacesByDefault(): void
    {
        $this->createCoupon('STACKA', true);
        $this->createCoupon('SOLO', false);

        $order = $this->objFromFixture(Order::class, 'cart');
        $component = CouponCheckoutComponent::create();

        $this->applyCode($component, $order, 'STACKA');
        $this->applyCode($component, $order, 'SOLO');
        $this->assertSame(['SOLO'], AppliedCouponCodes::get());

        // a stackable coupon can't join a non-stackable one either
        $this->applyCode($component, $order, 'STACKA');
        $this->assertSame(['STACKA'], AppliedCouponCodes::get());
    }

    public function testNonStackableCouponRejectedWhenConfigured(): void
    {
        CouponCheckoutComponent::config()->set('replace_non_stackable', false);

        $this->createCoupon('STACKA', true);
        $this->createCoupon('SOLO', false);

        $order = $this->objFromFixture(Order::class, 'cart');
        $component = CouponCheckoutComponent::create();

        $this->applyCode($component, $order, 'STACKA');

        try {
            $this->applyCode($component, $order, 'SOLO');
            $this->fail('Non-stackable coupon should be rejected');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('STACKA', $e->getMessage());
        }

        $this->assertSame(['STACKA'], AppliedCouponCodes::get());
    }

    public function testMaxCouponsPerOrder(): void
    {
        CouponCheckoutComponent::config()->set('max_coupons_per_order', 2);

        $this->createCoupon('STACKA', true);
        $this->createCoupon('STACKB', true);
        $this->createCoupon('STACKC', true);

        $order = $this->objFromFixture(Order::class, 'cart');
        $component = CouponCheckoutComponent::create();

        $this->applyCode($component, $order, 'STACKA');
        $this->applyCode($component, $order, 'STACKB');

        $this->expectException(ValidationException::class);
        $this->applyCode($component, $order, 'STACKC');
    }

    public function testRemoveSelectedCoupons(): void
    {
        $this->createCoupon('STACKA', true);
        $this->createCoupon('STACKB', true);

        $checkoutpage = $this->objFromFixture(CheckoutPage::class, 'checkout');
        $checkoutpage->publishRecursive();
        $order = $this->objFromFixture(Order::class, 'cart');

        AppliedCouponCodes::set(['STACKA', 'STACKB']);

        $couponForm = CouponForm::create(CheckoutPageController::create($checkoutpage), CouponForm::class, $order);
        $this->assertNotNull($couponForm->Fields()->dataFieldByName('RemoveCodes'));
        $this->assertCount(2, $couponForm->AppliedCoupons());

        $couponForm->removeCoupon(['RemoveCodes' => ['STACKA']], $couponForm);
        $this->assertSame(['STACKB'], AppliedCouponCodes::get());

        $couponForm->removeCoupon([], $couponForm);
        $this->assertSame([], AppliedCouponCodes::get());
    }

    protected function createCoupon(string $code, bool $stacking): OrderCoupon
    {
        $coupon = OrderCoupon::create([
            'Title' => $code,
            'Code' => $code,
            'Type' => 'Percent',
            'Percent' => 0.1,
            'AllowStacking' => $stacking
        ]);
        $coupon->write();

        return $coupon;
    }

    protected function applyCode(CouponCheckoutComponent $component, Order $order, string $code): void
    {
        $component->validateData($order, ['Code' => $code]);
        $component->setData($order, ['Code' => $code]);
    }
}
