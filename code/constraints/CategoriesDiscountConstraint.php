<?php

class CategoriesDiscountConstraint extends DiscountConstraint{
	
	private static $many_many = array(
		"Categories" => "ProductCategory"
	);

	public function updateCMSFields(FieldList $fields) {
		if($this->owner->isInDB()){
			$fields->fieldByName("Root")->push(new Tab("Categories",
				LabelField::create("CategoriesDescription", "Select specific product categories that this discount applies to"),
				GridField::create("Categories", "Categories", $this->Categories(),
					GridFieldConfig_RelationEditor::create()
						->removeComponentsByType("GridFieldAddNewButton")
						->removeComponentsByType("GridFieldEditButton")
				)
			));
		}
	}

	public function filter(DataList $list) {
		//TODO: filter discounts to match categories
		return $list;
	}

	public function check(Discount $discount) {
		$categories = $discount->Categories();
		//valid if no categories defined
		if(!$categories->exists()){
			return true;
		}
		$items = $this->order->Items();
		$incart = false; //note that this means an order without items will always be invalid
		foreach($items as $item){
			//check at least one item in the cart meets the discount's criteria
			if($this->itemMatchesCriteria($item, $discount)){
				$incart = true;
				break;
			}
		}
		
		return $incart;
	}

	public function itemMatchesCriteria(OrderItem $item, Discount $discount) {
		$categories = $discount->Categories();
		if($categories->exists()){
			$itemproduct = $item->Product(true); //true forces the current version of product to be retrieved.
			if(!$itemproduct || !$categories->find('ID', $itemproduct->ParentID)){

				return false;
			}
		}

		return true;
	}

}