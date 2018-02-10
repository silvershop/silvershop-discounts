<?php

namespace SilverShop\Discounts\Model;

use SilverStripe\ORM\DataObject;

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
 * @method Product Product()
 * @method ProductVariation ProductVariation()
 * @method Group Group()
 */
class SpecificPrice extends DataObject
{
    private static $db = [
        "Price" => "Currency",
        "DiscountPercent" => "Percentage",
        "StartDate" => "Date",
        "EndDate" => "Date"
    ];

    private static $has_one = [
        "Product" => "Product",
        "ProductVariation" => "ProductVariation",
        "Group" => "Group"
    ];

    private static $summary_fields = [
        "Price" => "Price",
        "StartDate" => "Start",
        "EndDate" => "End",
        "Group.Code" => "Group"
    ];

    private static $default_sort = "\"Price\" ASC";

    private static $table_name = 'SpecificPrice';

    public static function filter(DataList $list, $member = null)
    {
        $now = date('Y-m-d H:i:s');
        $nowminusone = date('Y-m-d H:i:s', strtotime("-1 day"));
        $groupids = [0];

        if ($member) {
            $groupids = array_merge($member->Groups()->map('ID', 'ID')->toArray(), $groupids);
        }

        $list = $list->where(
            "(\"SpecificPrice\".\"StartDate\" IS NULL) OR (\"SpecificPrice\".\"StartDate\" < '$now')"
        )
        ->where(
            "(\"SpecificPrice\".\"EndDate\" IS NULL) OR (\"SpecificPrice\".\"EndDate\" > '$nowminusone')"
        )
        ->filter("GroupID", $groupids);

        return $list;
    }

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        $fields->removeByName("ProductID");
        $fields->removeByName("ProductVariationID");

        return $fields;
    }
}
