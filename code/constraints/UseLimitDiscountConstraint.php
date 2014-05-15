<?php

class UseLimitDiscountConstraint extends DiscountConstraint{
	
	private static $db = array(
		"UseLimit" => "Int"
	);

	private static $field_labels = array(
		"UseLimit" => "Maximum number of uses"
	);

	public function updateCMSFields(FieldList $fields) {
		$fields->addFieldToTab("Root.Main.Constraints.Main",
			NumericField::create("UseLimit", "Limit number of uses", 0)
				->setDescription("Note: 0 = unlimited")
		);
	}

	public function filter(DataList $list) {
		//this would require summing order counts in sql
		return $list;
	}

	public function check(Discount $discount) {
		if($discount->UseLimit){
			if($discount->UseCount >= $discount->UseLimit){
				$this->error("This discount has reached it's maximum number of uses.");
				return false;
			}
		}
		
		return true;
	}

}