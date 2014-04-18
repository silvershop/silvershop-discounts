<?php

class ProductsDiscountConstraint extends DiscountConstraint{

	//TODO: all products vs some products

	private static $many_many = array(
		"Products" => "Product"
	);

	public function updateCMSFields(FieldList $fields) {
		if($this->owner->isInDB() && $this->owner->ForItems){
			$fields->fieldByname("Root")->push(new Tab("Products",
				LabelField::create("ProductsDescription", "Select specific products that this discount applies to"),
				GridField::create("Products", "Products", $this->Products(),
					GridFieldConfig_RelationEditor::create()
						->removeComponentsByType("GridFieldAddNewButton")
						->removeComponentsByType("GridFieldEditButton")
				)
			));
		}
	}
	
	public function apply(DataList $list) {

		$productids = $this->order->Items()
					->map('ProductID', 'ProductID')
					->toArray();
		//todo update discount list to narrow to products
		return $list;
	}

	public function check(Discount $discount) {
		$products = $discount->Products();
		//valid if no categories defined
		if(!$products->exists()){
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
		
		return true;
	}

	public function itemMatchesCriteria(OrderItem $item, Discount $discount) {
		$products = $discount->Products();
		$itemproduct = $item->Product(true); //true forces the current version of product to be retrieved.
		if(!$products->find('ID', $item->ProductID)){
			return false;
		}

		return true;
	}

}