<?php

class CategoriesDiscountConstraint extends ItemDiscountConstraint{
	
	private static $many_many = array(
		"Categories" => "ProductCategory"
	);

	public function updateCMSFields(FieldList $fields) {
		if($this->owner->isInDB()){
			$fields->fieldByName("Root.Main.Constraints")->push(new Tab("Categories",
				LabelField::create("CategoriesDescription", "Select specific product categories that this discount applies to"),
				GridField::create("Categories", "Categories", $this->owner->Categories(),
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
		$incart = $this->itemsInCart($discount);
		if(!$incart){
			$this->error("The required products (categories) are not in the cart.");
		}
		
		return $incart;
	}

	public function itemMatchesCriteria(OrderItem $item, Discount $discount) {
		$discountcategoryids = $discount->Categories()->getIDList();
		if(empty($discountcategoryids)){

			return true;
		}
		//get category ids from buyable
		$buyable = $item->Buyable();
		if(!method_exists($buyable, "getCategoryIDs")){

			return false;
		}
		$ids = array_intersect(
			$buyable->getCategoryIDs(),
			$discountcategoryids
		);

		return !empty($ids);
	}

}