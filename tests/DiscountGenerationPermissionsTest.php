<?php

namespace SilverShop\Discounts\Tests;

use SilverShop\Discounts\Admin\DiscountModelAdmin;
use SilverShop\Discounts\Form\GridFieldGenerateCodesButton;
use SilverShop\Discounts\Model\OrderCoupon;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse_Exception;
use SilverStripe\Control\Session;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\GridField\GridField;

/**
 * Code and coupon generation writes must enforce MANAGE_DISCOUNTS. DataObject::write() does not run
 * canCreate()/canEdit(), so the action handlers guard the write themselves: a member with CMS access
 * to the section but without MANAGE_DISCOUNTS must not be able to mint codes or coupons.
 */
class DiscountGenerationPermissionsTest extends SapphireTest
{
    protected $usesDatabase = true;

    public function testBulkCouponGenerationRequiresPermission(): void
    {
        $this->logInWithPermission('CMS_ACCESS_LeftAndMain'); // CMS access, but not MANAGE_DISCOUNTS

        $admin = DiscountModelAdmin::create();
        $request = new HTTPRequest('GET', '/admin/discounts');
        $request->setSession(new Session([]));
        $admin->setRequest($request);
        $form = Form::create($admin, 'GenerateCouponsForm', FieldList::create(), FieldList::create());

        $before = OrderCoupon::get()->count();

        try {
            $admin->generate(['Number' => 3, 'Title' => 'Sneaky', 'Type' => 'Percent'], $form);
            $this->fail('bulk generation without MANAGE_DISCOUNTS should be blocked');
        } catch (HTTPResponse_Exception $e) {
            $this->assertSame(403, $e->getResponse()->getStatusCode());
        }

        $this->assertSame($before, OrderCoupon::get()->count(), 'no coupons were created');
    }

    public function testChildCodeGenerationRequiresPermission(): void
    {
        $coupon = OrderCoupon::create(['Title' => 'Multi', 'Type' => 'Percent', 'Percent' => 0.1]);
        $coupon->write();

        $gridField = GridField::create('Codes', 'Codes', $coupon->Codes());
        $form = Form::create(null, 'EditForm', FieldList::create($gridField), FieldList::create());
        $form->loadDataFrom($coupon);

        $button = GridFieldGenerateCodesButton::create();
        $data = ['GenerateCodesCount' => 5, 'GenerateCodesUseLimit' => 1];

        $this->logInWithPermission('CMS_ACCESS_LeftAndMain');
        $this->assertSame([], $button->getHTMLFragments($gridField), 'no generate form without MANAGE_DISCOUNTS');
        $button->handleAction($gridField, 'generatecodes', [], $data);
        $this->assertSame(0, $coupon->Codes()->count(), 'no codes minted without MANAGE_DISCOUNTS');

        $this->logInWithPermission('MANAGE_DISCOUNTS');
        $this->assertNotEmpty($button->getHTMLFragments($gridField), 'generate form shown with permission');
        $button->handleAction($gridField, 'generatecodes', [], $data);
        $this->assertSame(5, $coupon->Codes()->count(), 'codes minted with permission');
    }
}
