<?php
/**
 * Represents a price change applied to a Product or ProductVariation,
 * for a period of time or for a specific group.
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
class SpecificPrice extends DataObject{
	
	private static $db = array(
		"Price" => "Currency",
		"DiscountPercent" => "Percentage",
		"StartDate" => "Date",
		"EndDate" => "Date"
	);

	private static $has_one = array(
		"Product" => "Product",
		"ProductVariation" => "ProductVariation",
		"Group" => "Group"
	);

	private static $summary_fields = array(
		"Price" => "Price",
		"StartDate" => "Start",
		"EndDate" => "End",
		"Group.Code" => "Group"
	);

	private static $default_sort = "\"Price\" ASC";

	public static function filter(DataList $list, $member = null) {
		$now = date('Y-m-d H:i:s');
		$nowminusone = date('Y-m-d H:i:s',strtotime("-1 day"));
		$groupids = array(0);
		if($member){
			$groupids = array_merge($member->Groups()->map('ID', 'ID')->toArray(), $groupids);
		}
		return $list->where(
			"(\"SpecificPrice\".\"StartDate\" IS NULL) OR (\"SpecificPrice\".\"StartDate\" < '$now')"
		)
		->where(
			"(\"SpecificPrice\".\"EndDate\" IS NULL) OR (\"SpecificPrice\".\"EndDate\" > '$nowminusone')"
		)
		->filter("GroupID", $groupids);
	}

	function getCMSFields(){
		$fields = parent::getCMSFields();
		$fields->removeByName("ProductID");
		$fields->removeByName("ProductVariationID");
		$fields->fieldByName("Root.Main.StartDate")->setConfig("showcalendar", true);
		$fields->fieldByName("Root.Main.EndDate")->setConfig("showcalendar", true);
		return $fields;
	}

}