<?php

namespace SilverShop\Discounts\Extensions\Constraints;

use SilverShop\Discounts\Model\Discount;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Security\Group;
use SilverStripe\ORM\DataList;
use SilverStripe\Security\Member;

/**
 * @property int $GroupID
 * @method   Group Group()
 */
class GroupDiscountConstraint extends DiscountConstraint
{
    private static array $has_one = [
        'Group' => Group::class
    ];

    public function updateCMSFields(FieldList $fieldList): void
    {
        $fieldList->addFieldToTab(
            'Root.Constraints.ConstraintsTabs.Membership',
            DropdownField::create(
                'GroupID',
                _t(__CLASS__ . '.MEMBERISINGROUP', 'Member is in group'),
                Group::get()->map('ID', 'Title')
            )->setHasEmptyDefault(true)
            ->setEmptyString(_t(__CLASS__ . '.ANYORNOGROUP', 'Any or no group'))
        );
    }

    public function filter(DataList $dataList): DataList
    {
        $groupids = [0];
        $member = $this->getMember();
        if ($member->exists()) {
            $groupids += $member->Groups()
                ->map('ID', 'ID')
                ->toArray();
        }

        return $dataList->filter('GroupID', $groupids);
    }

    public function check(Discount $discount): bool
    {
        $group = $discount->Group();
        $member = $this->getMember();
        if ($group->exists() && (!$member instanceof Member || !$member->inGroup($group))) {
            $this->error(
                _t(
                    'Discount.GROUPED',
                    'Only specific members can use this discount.'
                )
            );
            return false;
        }

        return true;
    }

    public function getMember(): Member
    {
        return $this->context['Member'] ?? $this->order->Member();
    }
}
