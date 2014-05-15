<?php

class DatetimeDiscountConstraint extends DiscountConstraint{

	private static $db = array(
		"StartDate" => "Datetime",
		"EndDate" => "Datetime"
	);
	
	public function updateCMSFields(FieldList $fields) {
		$fields->addFieldToTab("Root.Main.Constraints.Main",
			FieldGroup::create("Valid date range:",
				CouponDatetimeField::create("StartDate", "Start Date / Time"),
				CouponDatetimeField::create("EndDate", "End Date / Time")
			)->setDescription(
				"You should set the end time to 23:59:59, if you want to include the entire end day."
			)
		);
	}

	public function filter(DataList $list) {
		$now = date('Y-m-d H:i:s');
		//to bad ORM filtering for NULL doesn't work...so we need to use where
		return $list->where(
			"(\"Discount\".\"StartDate\" IS NULL) OR (\"Discount\".\"StartDate\" < '$now')"
		)
		->where(
			"(\"Discount\".\"EndDate\" IS NULL) OR (\"Discount\".\"EndDate\" > '$now')"
		);
	}

	public function check(Discount $discount) {
		//time period
		$startDate = strtotime($discount->StartDate);
		$endDate = strtotime($discount->EndDate);
		$now = time();
		if($endDate && $endDate < $now){
			$this->error(_t("OrderCoupon.EXPIRED", "This coupon has already expired."));
			return false;
		}
		if($startDate && $startDate > $now){
			$this->error(_t("OrderCoupon.TOOEARLY", "It is too early to use this coupon."));
			return false;
		}

		return true;
	}

}