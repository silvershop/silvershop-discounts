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
		return $list;
			//->filter("Code", ?);
	}

	public function check(Discount $discount) {
		if($this->MinOrderValue > 0 && $order->SubTotal() < $this->MinOrderValue){
			$this->error(
				sprintf(
					_t(
						"Discount.MINORDERVALUE",
						"Your cart subtotal must be at least %s to use this discount"
					),
					$this->dbObject("MinOrderValue")->Nice()
				)
			);
			return false;
		}

		return true;
	}	

}