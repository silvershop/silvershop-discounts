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

    public function testEditAndDeleteRequirePermission(): void
    {
        $discount = OrderDiscount::create(['Title' => 'Open', 'Type' => 'Percent', 'Percent' => 0.1]);
        $discount->write();

        $coupon = OrderCoupon::create(
            ['Title' => 'Open coupon', 'Code' => 'OPENCODE1', 'Type' => 'Percent', 'Percent' => 0.1, 'Active' => true]
        );
        $coupon->write();

        // CMS access to the section, but not MANAGE_DISCOUNTS
        $this->logInWithPermission('CMS_ACCESS_LeftAndMain');
        $this->assertFalse($coupon->canEdit(), 'no editing a coupon without MANAGE_DISCOUNTS');
        $this->assertFalse($coupon->canDelete(), 'no deleting a coupon without MANAGE_DISCOUNTS');
        $this->assertFalse($discount->canDelete(), 'no deleting a discount without MANAGE_DISCOUNTS');

        $this->logInWithPermission('MANAGE_DISCOUNTS');
        $this->assertTrue($coupon->canEdit(), 'a MANAGE_DISCOUNTS member can edit an unused coupon');
        $this->assertTrue($coupon->canDelete(), 'a MANAGE_DISCOUNTS member can delete an unused coupon');
        $this->assertTrue($discount->canDelete(), 'a MANAGE_DISCOUNTS member can delete an unused discount');
    }

    public function testUsedRecordsStayLockedEvenWithPermission(): void
    {
        $this->logInWithPermission('MANAGE_DISCOUNTS');

        $usedInactive = $this->getMockBuilder(OrderCoupon::class)->onlyMethods(['getUseCount'])->getMock();
        $usedInactive->method('getUseCount')->willReturn(2);
        $usedInactive->Active = false;
        $this->assertFalse($usedInactive->canEdit(), 'a used, deactivated coupon is locked');
        $this->assertFalse($usedInactive->canDelete(), 'a used coupon cannot be deleted');

        $usedActive = $this->getMockBuilder(OrderCoupon::class)->onlyMethods(['getUseCount'])->getMock();
        $usedActive->method('getUseCount')->willReturn(2);
        $usedActive->Active = true;
        $this->assertTrue($usedActive->canEdit(), 'a used but active coupon can still be edited');

        $usedDiscount = $this->getMockBuilder(OrderDiscount::class)->onlyMethods(['isUsed'])->getMock();
        $usedDiscount->method('isUsed')->willReturn(true);
        $this->assertFalse($usedDiscount->canDelete(), 'a used discount cannot be deleted');
    }
}
