<?php

namespace SilverShop\Discounts\Tests;

use SilverShop\Discounts\Form\GridFieldGenerateCodesButton;
use SilverShop\Discounts\Model\OrderCoupon;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\GridField\GridField;

/**
 * Code generation writes must enforce MANAGE_DISCOUNTS. DataObject::write() does not run
 * canCreate()/canEdit(), so the action handler guards the write itself: a member with CMS access
 * to the section but without MANAGE_DISCOUNTS must not be able to mint codes.
 */
class DiscountGenerationPermissionsTest extends SapphireTest
{
    protected $usesDatabase = true;

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
