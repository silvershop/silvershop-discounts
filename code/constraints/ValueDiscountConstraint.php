<?php

class ValueDiscountConstraint extends DiscountConstraint{
	
	private static $db = array(
		"MinOrderValue" => "Currency"
	);

	private static $field_labels = array(
		"MinOrderValue" => "Minimum subtotal of order"
	);

	public function updateCMSFields(FieldList $fields) {
		$fields->addFieldToTab("Root.Main",
			CurrencyField::create("MinOrderValue", "Minimum order subtotal")
		);
	}
	
	public function filter(DataList $list) {
		return $list->filterAny(array(
			"MinOrderValue" => 0,
			"MinOrderValue:LessThan" => $this->order->SubTotal()
		));
	}

	public function check(Discount $discount) {
		if($discount->MinOrderValue > 0 && $this->order->SubTotal() < $discount->MinOrderValue){
			$this->error(
				sprintf(
					_t("Discount.MINORDERVALUE",
						"Your cart subtotal must be at least %s to use this discount"),
					$discount->dbObject("MinOrderValue")->Nice()
				)
			);
			return false;
		}

		return true;
	}	
	
}