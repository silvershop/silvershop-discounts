<?php

class PartialUseDiscount extends Discount{
	
	private static $has_one = array(
		"Parent" => "PartialUseDiscount" 
	);

	private static $belongs_to = array(
		"Child" => "PartialUseDiscount"
	);

	private static $defaults = array(
		'Type' => 'Amount',
		'ForCart' => 1,
		'ForItems' => 0,
		'ForShipping' => 0,
		'UseLimit' => 1
	);

	private static $singular_name = "Partial Use Discount";
	private static $plural_name = "Partial Use Discounts";

	function getCMSFields($params = null) {
		$fields = parent::getCMSFields(array(
			'forcetype' => 'Amount'
		));

		$fields->removeByName(array(
			"ForCart",
			"ForItems",
			"ForShipping",
			"For"
		));
		$limitfield = $fields->fieldByName("Root.Main.Constraints")
						->fieldByName("Main.UseLimit");
		$fields->replaceField("UseLimit", $limitfield->performReadOnlyTransformation());
		return $fields;
	}

	/**
	 * Create remainder discount object.
	 * 
	 * @param  float  $used the amount of this discount that was used up
	 * @return PartialUseDiscount  new 'remainder' discount
	 */
	function createRemainder($used) {
		//don't recreate or do stuff with inactive discount
		if(!$this->Active || $this->Child()->exists()){
			return null;
		}
		$remainder = null;
		//only create remainder if used less than amount
		if($used < $this->Amount){
			//duplicate dataobject and update accordingly
			$remainder = $this->duplicate(false);
			$remainder->write();
			//delete any relationships that might be sitting in DB for whatever reason
			$remainder->deleteRelationships();
			//create proper new relationships
			$this->duplicateManyManyRelations($this, $remainder);

			//TODO: there may be some relationships that shouldn't be copied?
			$remainder->Amount = $this->Amount - $used;
			$remainder->ParentID = $this->ID;
			//unset old code
			$remainder->Code = "";
			$remainder->write();
		}

		return $remainder;
	}

	function validate() {
		$result = parent::validate();
		//prevent vital things from changing
		foreach(self::$defaults as $field => $value){
			if($this->isChanged($field)){
				$result->error("$field should not be changed for partial use discounts.");
			}
		}

		return $result;
	}

	/**
	 * Delete complex relations
	 */
	protected function deleteRelationships() {
		if ($this->many_many()) foreach($this->many_many() as $name => $type) {
			//many_many include belongs_many_many
			$this->{$name}()->removeAll();
		}
	}

}

