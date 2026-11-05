<?php

namespace SilverShop\Discounts\Tests;

use SilverShop\Discounts\Model\DiscountCode;
use SilverShop\Discounts\Model\OrderCoupon;
use SilverShop\Discounts\Model\OrderDiscount;
use SilverStripe\Dev\SapphireTest;

/**
 * Discounts, coupons and their codes must not be world-readable: only a member with MANAGE_DISCOUNTS
 * (or admin) may view or create them. Guards against coupon-code disclosure through any generic read
 * API that honours canView().
 */
class DiscountPermissionsTest extends SapphireTest
{
    protected $usesDatabase = true;

    public function testModelsAreNotWorldViewable(): void
    {
        $discount = OrderDiscount::create(['Title' => 'Secret', 'Type' => 'Percent', 'Percent' => 0.1]);
        $discount->write();

        $coupon = OrderCoupon::create(
            ['Title' => 'Secret coupon', 'Code' => 'SECRETCODE1', 'Type' => 'Percent', 'Percent' => 0.1]
        );
        $coupon->write();

        $code = DiscountCode::create(['Code' => 'CHILDSECRET1', 'DiscountID' => $coupon->ID]);
        $code->write();

        $this->logOut();
        $this->assertFalse($discount->canView(), 'anonymous cannot view a discount');
        $this->assertFalse($coupon->canView(), 'anonymous cannot view a coupon');
        $this->assertFalse($code->canView(), 'anonymous cannot view a discount code');
        $this->assertFalse($coupon->canCreate(), 'anonymous cannot create a coupon');

        $this->logInWithPermission('MANAGE_DISCOUNTS');
        $this->assertTrue($discount->canView(), 'a MANAGE_DISCOUNTS member can view a discount');
        $this->assertTrue($coupon->canView(), 'a MANAGE_DISCOUNTS member can view a coupon');
        $this->assertTrue($code->canView(), 'a MANAGE_DISCOUNTS member can view a discount code');
        $this->assertTrue($coupon->canCreate(), 'a MANAGE_DISCOUNTS member can create a coupon');
    }
}
