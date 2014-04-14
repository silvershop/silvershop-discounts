<?php
class CouponDatetimeField extends DatetimeField{

	public function __construct($name, $title = null, $value = "") {
		parent::__construct($name, $title, $value);

		$this->dateField->setConfig("showcalendar", true);

	}

	public function setValue($val) {
		if($val == array('date' => '', 'time' => '')){
			$val = null;
		}
		parent::setValue($val);
	}

}
