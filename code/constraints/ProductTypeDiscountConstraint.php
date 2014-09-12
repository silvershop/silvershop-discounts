<?php

class ProductTypeDiscountConstraint extends ItemDiscountConstraint{

	private static $db = array(
		'ProductTypes' => 'Text'
	);

	function updateCMSFields(FieldList $fields){
		//multiselect subtypes of orderitem
		if($this->owner->isInDB() && $this->owner->ForItems){
			$fields->addFieldToTab("Root.Main.Constraints.Products",
				ListBoxField::create(
					"ProductTypes",
					"Product Types",
					$this->getTypes(false, $this->owner)
				)->setMultiple(true)
			);
		}
	}

	public function filter(DataList $list) {
		//classname is in x,y,z
		//$this->getTypes();

		return $list;
	}

	public function check(Discount $discount) {
		$types = $this->getTypes(true, $discount);
		//valid if no categories defined
		if(!$types){
			return true;
		}
		$incart = $this->itemsInCart($discount);
		if(!$incart){
			$this->error("The required product type(s), are not in the cart.");
		}
		
		return $incart;
	}

	/**
	 * This function is used by ItemDiscountAction, and the check function above.
	 */
	public function itemMatchesCriteria(OrderItem $item, Discount $discount) {
		$types = $this->getTypes(true, $discount);
		if(!$types){
			return true;
		}
		$buyable = $item->Buyable();
		return isset($types[$buyable->class]);
	}

	protected function getTypes($selected, Discount $discount){
		$types = $selected ? array_filter(explode(",", $discount->ProductTypes)) : $this->BuyableClasses();
		if($types && !empty($types)){
			$types = array_combine($types, $types);
			foreach($types as $type => $name){
				$types[$type] = singleton($type)->i18n_singular_name();
			}
			return $types;
		}
	}

	protected function BuyableClasses(){
		$implementors = ClassInfo::implementorsOf("Buyable");
		$classes = array();
		foreach ($implementors as $key => $class) {
			$classes = array_merge($classes,array_values(ClassInfo::subclassesFor($class)));
		}
		$classes = array_combine($classes, $classes);
		unset($classes['ProductVariation']);
		return $classes;
	}

}