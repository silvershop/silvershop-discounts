<?php

class UseLimitDiscountConstraint extends DiscountConstraint{
	
	private static $db = array(
		"UseLimit" => "Int"
	);

	private static $field_labels = array(
		"UseLimit" => "Maximum number of uses"
	);

	public function updateCMSFields(FieldList $fields) {
		$fields->addFieldToTab("Root.Main",
			NumericField::create("UseLimit", "Limit number of uses")
				->setDescription("Note: 0 = unlimited")
		);
	}

	public function filter(DataList $list) {
		return $list;
	}

	public function check(Discount $discount) {
		
		return true;
	}

}