<?php

namespace SilverShop\Discounts\Tests;

use SilverShop\Discounts\Model\OrderCoupon;
use SilverStripe\Dev\FunctionalTest;

class DiscountModelAdminGenerateCouponsTest extends FunctionalTest
{
    protected $usesDatabase = true;

    protected function setUp(): void
    {
        parent::setUp();
        $this->logInWithPermission('ADMIN');
    }

    public function testGenerateCouponsButtonIsShownOnCouponsTab(): void
    {
        $response = $this->get('admin/discounts/SilverShop-Discounts-Model-OrderCoupon');

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('Generate Multiple Coupons', $response->getBody());
        $this->assertStringContainsString(
            'admin/discounts/SilverShop-Discounts-Model-OrderCoupon/generatecoupons',
            $response->getBody()
        );
    }

    public function testGenerateCouponsButtonIsNotShownOnOtherTabs(): void
    {
        $response = $this->get('admin/discounts/SilverShop-Discounts-Model-OrderDiscount');

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringNotContainsString('Generate Multiple Coupons', $response->getBody());
    }

    public function testModelDescriptionIsShown(): void
    {
        $response = $this->get('admin/discounts/SilverShop-Discounts-Model-OrderCoupon');

        $this->assertStringContainsString('Coupons are like discounts, but have an associated code.', $response->getBody());
    }

    public function testGenerateCoupons(): void
    {
        $response = $this->get('admin/discounts/SilverShop-Discounts-Model-OrderCoupon/generatecoupons');
        $this->assertEquals(200, $response->getStatusCode());

        $this->submitForm('Form_EditForm', 'action_generate', [
            'Title' => 'Bulk coupon',
            'Type' => 'Percent',
            'Percent' => '0.1',
            'Number' => 3,
            'Prefix' => 'BULK',
            'Length' => 8,
        ]);

        $coupons = OrderCoupon::get()->filter('Title', 'Bulk coupon');
        $this->assertCount(3, $coupons);

        foreach ($coupons as $coupon) {
            $this->assertMatchesRegularExpression('/^BULK.{8}$/', $coupon->Code);
            $this->assertSame('Cart', $coupon->getFor());
        }
    }
}
