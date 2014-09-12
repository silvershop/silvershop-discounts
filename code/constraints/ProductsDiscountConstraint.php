<?php

class ProductsDiscountConstraint extends ItemDiscountConstraint{

	private static $db = array(
		'ExactProducts' => 'Boolean'
	);

	private static $many_many = array(
		"Products" => "Product"
	);

	public function updateCMSFields(FieldList $fields) {
		if($this->owner->isInDB()){
			$fields->addFieldsToTab("Root.Main.Constraints.Products",array(
				GridField::create("Products", "Specific Products", $this->owner->Products(),
					GridFieldConfig_RelationEditor::create()
						->removeComponentsByType("GridFieldAddNewButton")
						->removeComponentsByType("GridFieldEditButton")
				)->setDescription("Select specific products that this discount applies to"),
				CheckboxField::create("ExactProducts", "All the selected products must be present in cart."),
			));
		}
	}

	public function check(Discount $discount) {
		$products = $discount->Products();
		//valid if no categories defined
		if(!$products->exists()){
			return true;
		}
		$constraintproductids = $products->map('ID','ID')->toArray();
		$cartproductids = $this->order->Items()->map('ProductID','ProductID')->toArray();
		$intersection = array_intersect($constraintproductids, $cartproductids);
		$incart = $discount->ExactProducts ?
					$constraintproductids === $intersection :
					count($intersection) > 0;
		if(!$incart){
			$this->error("The required products (categories) are not in the cart.");
		}
		
		return $incart;
	}

	public function itemMatchesCriteria(OrderItem $item, Discount $discount) {
		$products = $discount->Products();
		$itemproduct = $item->Product(true); //true forces the current version of product to be retrieved.
		if($products->exists() && !$products->find('ID', $item->ProductID)){
			return false;
		}

		return true;
	}

}