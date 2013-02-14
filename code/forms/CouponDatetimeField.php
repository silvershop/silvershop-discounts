<?php
class CouponDatetimeField extends DatetimeField{
	
	function __construct($name, $title = null, $value = ""){
		parent::__construct($name, $title, $value);
		
		$this->dateField->setConfig("showcalendar", true);
		
	}
	
	function setValue($val){
		if($val == array('date' => '', 'time' => '')){
			$val = null;
		}
		parent::setValue($val);
	}
	
}