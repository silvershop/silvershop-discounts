<?php

/**
 * Order Coupon - applies a discount when entered at checkout.
 */

class OrderCoupon extends DataObject {

	static $db = array(
		"Title" => "Varchar(255)", //store the promotion name, or whatever you like
		"Code" => "Varchar(25)",
		//"Type" => "Enum('Voucher,GiftCard,Coupon','Coupon')",
		"Amount" => "Currency",
		"Percent" => "Decimal(4,2)",
		
		//Restrictions
		"Active" => "Boolean",
		"StartDate" => "Datetime",
		"EndDate" => "Datetime",
		"UseLimit" => "Int",
		"MinOrderValue" => "Currency",
	);
	
	static $defaults = array(
		"Active" => true,
		"UseLimit" => 1
	);

	static $has_many = array(
		//'FreeItems' => 'OrderItem'
		//'SpecificProducts' => 'Product' //ie: not for any order
	);

	static $casting = array(
		"IsValid" => "Boolean"
	);

	static $searchable_fields = array(
		"Code"
	);

	static $field_labels = array(
		"Amount" => "Amount",
		"Percent" => "Percent",
		"UseLimit" => "Usage Limit"
	);

	static $summary_fields = array(
		"Code",
		"Title",
		"Amount",
		"Percent",
		"StartDate",
		"EndDate"
	);

	static $singular_name = "Discount";
	function i18n_singular_name() { return _t("OrderCoupon.ORDERCOUPON", "Order Coupon");}

	static $plural_name = "Discounts";
	function i18n_plural_name() { return _t("OrderCoupon.ORDERCOUPONS", "Order Coupons");}

	static $default_sort = "EndDate DESC, StartDate DESC";
	
	static $code_length = 10;

	static function get_by_code($code){
		return DataObject::get_one('OrderCoupon',"\"Code\" = UPPER('$code')");
	}

	/**
	* Generates a unique code.
	* @return string - new code
	*/
	static function generateCode($length = null){
		$length = ($length) ? $length : self::$code_length;
		$code = null;
		do{
			$code = strtoupper(substr(md5(microtime()),0,$length));
		}while(DataObject::get('OrderCoupon',"\"Code\" = '$code'"));
		return $code;
	}
	
	function populateDefaults() {
		$this->Code = self::generateCode();
	}
	
	/**
	* Forces codes to be alpha-numeric, without spaces, and uppercase
	*/
	function setCode($code){
		$code = eregi_replace("[^[:alnum:]]", " ", $code);
		$code = trim(eregi_replace(" +", "", $code)); //gets rid of any white spaces
		$this->setField("Code", strtoupper($code));
	}
	
	/**
	 * How many times the coupon has been used.
	 * @return int
	 */
	function getUseCount() {
		$objects = DataObject::get("OrderCouponModifier", "\"OrderCouponID\" = ".$this->ID);
		if($objects) {
			return $objects->count();
		}
		return 0;
	}

	/**
	 * Check if the coupon can be used
	 * @return boolean
	 */
	function getIsValid() {
		//has the code been used
		if($this->UseLimit > 0 && $this->getUseCount() < $this->UseLimit) {
			$this->validationerror = _t("OrderCoupon.LIMITREACHED","Limit of $this->UseLimit uses for this code has been reached.");
			return false;
		}
		//TODO:check order minimum - this should be in a CouponValidator

		$startDate = strtotime($this->StartDate);
		$endDate = strtotime($this->EndDate);
		$today = strtotime("today");
		$yesterday = strtotime("yesterday");

		if($this->EndDate && $endDate < $yesterday){
			$this->validationerror = _t("OrderCoupon.EXPIRED","This coupon has already expired.");
			return false;
		}

		if($this->StartDate && $startDate > $today){
			$this->validationerror = _t("OrderCoupon.TOOEARLY","It is too early to use this coupon.");
			return false;
		}

		return true;
	}

	function getDiscountValue($subTotal){
		$discount = 0;
		if($this->DiscountAbsolute) {
			$discount += abs($this->Value);
		}
		if($this->PercentageDiscount) {
			$discount += $subTotal * ($this->PercentageDiscount / 100);
		}
		return $discount;
	}


	function canDelete($member = null) {
		return $this->canEdit($member);
	}

	function canEdit($member = null) {
		if($this->getUseCount()) {
			return false;
		}
		return true;
	}
	

}