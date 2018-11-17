<?php

namespace SilverShop\Discounts\Extensions\Constraints;

use SilverShop\Discounts\Model\Discount;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldConfig_RelationEditor;
use SilverStripe\ORM\DataList;
use SilverStripe\Security\Member;
use SilverStripe\Forms\GridField\GridFieldAddNewButton;
use SilverStripe\Forms\GridField\GridFieldEditButton;

class MembershipDiscountConstraint extends DiscountConstraint
{
    private static $many_many = [
        'Members' => Member::class
    ];

    public function updateCMSFields(FieldList $fields)
    {
        if ($this->owner->isInDB()) {
            $fields->addFieldToTab(
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

    public function filter(DataList $list)
    {
        $memberid = 0;
        if ($member = $this->getMember()) {
            $memberid = $member->ID;
        }
        $list = $list->leftJoin(
            'SilverShop_Discount_Members',
            '"SilverShop_Discount_Members"."SilverShop_DiscountID" = "SilverShop_Discount"."ID"'
        )->where("(\"SilverShop_Discount_Members\".\"MemberID\" IS NULL) OR \"SilverShop_Discount_Members\".\"MemberID\" = $memberid");

        return $list;
    }

    public function check(Discount $discount)
    {
        $members = $discount->Members();
        $member = $this->getMember();
        if ($members->exists() && (!$member || !$members->byID($member->ID))) {
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

    public function getMember()
    {
        return isset($this->context['Member']) && is_object($this->context['Member']) ? $this->context['Member'] : $this->order->Member();
    }
}
