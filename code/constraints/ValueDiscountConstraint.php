<?php

class ValueDiscountConstraint extends DiscountConstraint{
	
	private static $db = array(
		"MinOrderValue" => "Currency"
	);

	public function updateCMSFields(FieldList $fields) {
		$fields->addFieldToTab("Root.Main",
			CurrencyField::create("MinOrderValue", "Minimum order subtotal")
		);
	}
	
	public function apply(DataList $list) {
		return $list->filterAny(array(
			"MinOrderValue" => 0,
			"MinOrderValue:LessThan" => $this->order->SubTotal()
		));
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