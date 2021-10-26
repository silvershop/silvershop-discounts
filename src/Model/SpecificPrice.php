<?php

namespace SilverShop\Discounts\Model;

use SilverStripe\ORM\DataObject;
use SilverShop\Page\Product;
use SilverShop\Model\Variation\Variation;
use SilverStripe\Security\Group;
use SilverStripe\Security\Permission;
use SilverStripe\ORM\DataList;

/**
 * Represents a price change applied to a Product or ProductVariation, for a
 * period of time or for a specific group.
 *
 * @property float Price
 * @property float DiscountPercent
 * @property string StartDate
 * @property string EndDate
 * @property int ProductID
 * @property int ProductVariationID
 * @property int GroupID
 * @method   Product Product()
 * @method   Variation ProductVariation()
 * @method   Group Group()
 */
class SpecificPrice extends DataObject
{
    private static $db = [
        'Price' => 'Currency',
        'DiscountPercent' => 'Percentage',
        'StartDate' => 'Date',
        'EndDate' => 'Date'
    ];

    private static $has_one = [
        'Product' => Product::class,
        'ProductVariation' => Variation::class,
        'Group' => Group::class
    ];

    private static $summary_fields = [
        'Price' => 'Price',
        'StartDate' => 'Start',
        'EndDate' => 'End',
        'Group.Code' => 'Group'
    ];

    private static $default_sort = '"Price" ASC';

    private static $table_name = 'SilverShop_SpecificPrice';

    public function canView($member = null)
    {
        return
            parent::canView($member) ||
            Permission::checkMember($member, 'MANAGE_DISCOUNTS');
    }

    public function canEdit($member = null)
    {
        return
            parent::canEdit($member) ||
            Permission::checkMember($member, 'MANAGE_DISCOUNTS');
    }

    public function canCreate($member = null, $context = [])
    {
        return
            parent::canCreate($member, $context) ||
            Permission::checkMember($member, 'MANAGE_DISCOUNTS');
    }

    public function canDelete($member = null)
    {
        return
            parent::canDelete($member) ||
            Permission::checkMember($member, 'MANAGE_DISCOUNTS');
    }

    public static function filter(DataList $list, $member = null)
    {
        $now = date('Y-m-d H:i:s');
        $nowminusone = date('Y-m-d H:i:s', strtotime('-1 day'));
        $groupids = [0];

        if ($member) {
            $groupids = array_merge($member->Groups()->map('ID', 'ID')->toArray(), $groupids);
        }

        $list = $list->where(
            "(\"SilverShop_SpecificPrice\".\"StartDate\" IS NULL) OR (\"SilverShop_SpecificPrice\".\"StartDate\" < '$now')"
        )
            ->where(
                "(\"SilverShop_SpecificPrice\".\"EndDate\" IS NULL) OR (\"SilverShop_SpecificPrice\".\"EndDate\" > '$nowminusone')"
            )
            ->filter('GroupID', $groupids);

        return $list;
    }

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        $fields->removeByName('ProductID');
        $fields->removeByName('ProductVariationID');

        return $fields;
    }
}
