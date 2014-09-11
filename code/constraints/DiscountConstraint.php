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
	 * Add filtering to a Discount DataList so it matches
	 * this constraint.
	 * 
	 * @param  DataList $list the list to constrain
	 * @return DataList        the updated list
	 */
	abstract function filter(DataList $list);

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