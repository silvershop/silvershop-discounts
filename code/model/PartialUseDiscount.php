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
		'ForShipping' => 0
	);

	/**
	 * Create remainder discount object.
	 * 
	 * @param  float  $used the amount of this discount that was used up
	 * @return PartialUseDiscount  new 'remainder' discount
	 */
	function createRemainder($used) {
		//don't produce remainder if total amount is used up
		if(!$this->Active || $used >= $this->Amount || $this->Child()->exists()){
			return null;
		}
		//duplicate dataobject and update accordingly
		$remainder = $this->duplicate();
		//TODO: there may be some relationships that shouldn't be copied?
		$remainder->Amount = $this->Amount - $used;
		$remainder->ParentID = $this->ID;
		//remove old code
		$remainder->Code = "";
		$remainder->write();
		//deactivate this
		$this->Active = false;
		$this->write();

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

}

