<?php

namespace SilverShop\Discounts\Extensions\Constraints;

use SilverStripe\ORM\ManyManyList;
use SilverShop\Discounts\Model\Discount;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldConfig_RelationEditor;
use SilverStripe\ORM\DataList;
use SilverStripe\Security\Member;
use SilverStripe\Forms\GridField\GridFieldAddNewButton;
use SilverStripe\Forms\GridField\GridFieldEditButton;

/**
 * @method ManyManyList<Member> Members()
 */
class MembershipDiscountConstraint extends DiscountConstraint
{
    private static array $many_many = [
        'Members' => Member::class
    ];

    public function updateCMSFields(FieldList $fieldList): void
    {
        if ($this->owner->isInDB()) {
            $fieldList->addFieldToTab(
                'Root.Constraints.ConstraintsTabs.Membership',
                GridField::create(
                    'Members',
                    _t(__CLASS__ . '.MEMBERS', 'Members'),
                    $this->owner->Members(),
                    GridFieldConfig_RelationEditor::create()
                        ->removeComponentsByType(GridFieldAddNewButton::class)
                        ->removeComponentsByType(GridFieldEditButton::class)
                )
            );
        }
    }

    public function filter(DataList $dataList): DataList
    {
        $memberid = 0;
        $member = $this->getMember();
        if ($member->exists()) {
            $memberid = $member->ID;
        }

        $dataList = $dataList->leftJoin(
            'SilverShop_Discount_Members',
            '"SilverShop_Discount_Members"."SilverShop_DiscountID" = "SilverShop_Discount"."ID"'
        )->where('("SilverShop_Discount_Members"."MemberID" IS NULL) OR "SilverShop_Discount_Members"."MemberID" = ' . $memberid);

        return $dataList;
    }

    public function check(Discount $discount): bool
    {
        $manyManyList = $discount->Members();
        $member = $this->getMember();
        if ($manyManyList->exists() && (!$member instanceof Member || !$manyManyList->byID($member->ID))) {
            $this->error(
                _t(
                    'Discount.MEMBERSHIP',
                    'Only specific members can use this discount.'
                )
            );
            return false;
        }

        return true;
    }

    public function getMember(): Member
    {
        return isset($this->context['Member']) && is_object($this->context['Member']) ? $this->context['Member'] : $this->order->Member();
    }
}
