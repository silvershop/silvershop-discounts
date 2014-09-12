<?php

/**
 * Encapsulate a single kind of constraint.
 * This class extends DataExtension, because constraint data
 * needs to be stored in the Discount object - the class
 * which each constraint extends.
 *
 * Constraints are also instantiated on their own. See
 * ItemDiscountConstraint::match and Discount->valid
 */
abstract class DiscountConstraint extends DataExtension{

	protected $order;
	protected $context;

	function setOrder(Order $order) {
		$this->order = $order;

		return $this;
	}

	function setContext(array $context) {
		$this->context = $context;

		return $this;
	}

	/**
	 * Filter a list of discounts according to this
	 * constraint.
	 * 
	 * @param  DataList $discounts discount list constrain
	 * @return DataList
	 */
	public function filter(DataList $discounts){
		return $discounts;
	}

	/**
	 * Check if the current set order falls within
	 * this constraint.
	 * @param  Discount $discount
	 * @return boolean
	 */
	abstract function check(Discount $discount);


	/**
	 * Set up constraints via _config.php
	 */
	public static function set_up_constraints() {
		$constraints = Config::inst()->forClass("Discount")->constraints;

		foreach($constraints as $constraint){
			Object::add_extension("Discount", $constraint);
		}
	}

	//messaging
	protected $message;
	protected $messagetype;

	protected function message($messsage, $type = "good") {
		$this->message = $messsage;
		$this->messagetype = $type;
	}

	protected function error($message) {
		$this->message($message, "bad");
	}

	public function getMessage() {
		return $this->message;
	}

	public function getMessageType() {
		return $this->messagetype;
	}

}