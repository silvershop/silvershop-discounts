<?php

abstract class DiscountConstraint extends DataExtension{

	protected $order;

	function setOrder(Order $order) {
		$this->order = $order;
	}

	abstract function apply(DataList $list);

	abstract function check(Discount $discount);


	//messaging

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