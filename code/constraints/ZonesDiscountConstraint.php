<?php

class ZonesDiscountConstraint extends DiscountConstraint{

	private static $many_many = array(
		"Zones" => "Zone"
	);

	public function updateCMSFields(FieldList $fields) {
		$fields->fieldByname("Root")->push(new Tab("Zones",
			$zones = new GridField("Zones", "Zones", $this->owner->Zones(),
				GridFieldConfig_RelationEditor::create()
					->removeComponentsByType("GridFieldAddNewButton")
					->removeComponentsByType("GridFieldEditButton")
			)
		));
	}
	
	public function filter(DataList $list) {
		//TODO: restrict to zone?
		return $list;
	}

	public function check(Discount $discount) {
		$zones = $discount->Zones();
		if(!$zones->exists()){
			return true;
		}
		$address = $this->order->getShippingAddress();
		if(!$address){
			$this->error(_t(
				"OrderCouponModifier.NOTINZONE",
				"This coupon can only be used for a specific shipping location."
			));
			return false;
		}
		$currentzones = Zone::get_zones_for_address($address);
		if(!$currentzones || !$currentzones->exists()){
			$this->error(_t(
				"OrderCouponModifier.NOTINZONE",
				"This discount can only be used for a specific shipping location."
			));
			return false;
		}
		//check if any of currentzones is in zones
		$inzone = false;
		foreach($currentzones as $zone){
			if($zones->find('ID', $zone->ID)){
				$inzone = true;
				break;
			}
		}
		if(!$inzone){
			$this->error(_t(
				"OrderCouponModifier.NOTINZONE",
				"This discount can only be used for a specific shipping location."
			));
			return false;
		}

		return true;
	}
	
}