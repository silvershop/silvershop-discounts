<?php

class CodeDiscountConstraint extends DiscountConstraint{
	
	private static $db = array(
		"Code" => "Varchar(25)"
	);

	public function updateCMSFields(FieldList $fields) {
		$fields->addFieldsToTab(
			"Root.Main", array(
				TextField::create("Code"),
				NumericField::create("UseLimit", "Limit number of uses")
						->setDescription("Note: 0 = unlimited")
			), 
			"Active"
		);
	}

	public function filter(DataList $list) {
		if($code = $this->findCouponCode()){
			$list = $list
				->where("(\"Code\" IS NULL) OR (\"Code\" = '$code')");
		}else{
			$list = $list->where("\"Code\" IS NULL");
		}

		return $list;
	}

	public function check(Discount $discount) {
		$code = $this->findCouponCode();
		if($discount->Code && ($code != $discount->Code)){
			$this->error("Coupon code doesn't match");
			return false;
		}

		return true;
	}

	protected function findCouponCode() {
		return isset($this->context['CouponCode']) ? $this->context['CouponCode'] : null;
	}

}