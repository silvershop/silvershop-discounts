<?php

/**
 *OrderCoupon
 *
 *@author nicolaas [at] sunnysideup.co.nz, jeremy [at] burnbright.co.nz
 *
 **/

class OrderCoupon extends DataObject {

	static $db = array(
		"Title" => "Varchar(255)", //store the promotion name, or whatever you like
		"Code" => "Varchar(25)",
		"Value" => "Currency",
		"PercentageDiscount" => "Decimal(4,2)",

		"StartDate" => "Date",
		"EndDate" => "Date",
		"DiscountAbsolute" => "Currency",
		"DiscountPercentage" => "Decimal(4,2)",
		//"CanOnlyBeUsedOnce" => "Boolean",

		//TODO: Order must be greater than...


		"MinOrderValue" => "Currency",
		"UseLimit" => 'Int'
		//"Type" => "Enum('Voucher,GiftCard,Coupon','Coupon')" //for managing purposes
		//"UseInConjunction" => "Boolean"
	);

	public static $has_many = array(
		//'FreeItems' => 'OrderItem'
		//'SpecificProducts' => 'Product' //ie: not for any order
	);

	public static $casting = array(
		"UseCount" => "Int",
		"IsValid" => "Boolean"
	);

	public static $searchable_fields = array(
		"Code"
	);

	public static $field_labels = array(
		"DiscountAbsolute" => "Discount as absolute reduction of total - if any (e.g. 10 = -$10.00)",
		"DiscountPercentage" => "Discount as percentage of total - if any (e.g. 10 = -10%)",
		"UseLimit" => "number of times the code can been used. (or enter 0 for unlimited)"
	);

	public static $summary_fields = array(
		"Code",
		"Title",
		"StartDate",
		"EndDate"
	);

	protected static $coupons_can_only_be_used_once = "";
		static function set_coupons_can_only_be_used_once($b) {self::$coupons_can_only_be_used_once = $b;}
		static function get_coupons_can_only_be_used_once() {return self::$coupons_can_only_be_used_once;}

	protected static $code_length = 10;
		static function set_code_length($l) {self::$code_length = $l;}
		static function get_code_length() {return self::$code_length;}


	public static $singular_name = "Order Coupon";
		function i18n_singular_name() { return _t("OrderCoupon.ORDERCOUPON", "Order Coupon");}

	public static $plural_name = "Order Coupon";
		function i18n_plural_name() { return _t("OrderCoupon.ORDERCOUPONS", "Order Coupons");}

	public static $default_sort = "EndDate DESC, StartDate DESC";

	static function get_by_code($code){
		return DataObject::get_one('OrderCoupon',"\"Code\" = UPPER('$code')");
	}

	function UseCount() {
		$objects = DataObject::get("OrderCouponModifier", "\"OrderCouponID\" = ".$this->ID);
		if($objects) {
			return $objects->count();
		}
		return 0;
	}

	function IsValid() {

		if($this->UseLimit > 0 && $this->UseCount() < $this->UseLimit) {
			//$this->getForm()->sessionMessage(_t("OrderCoupon.LIMITREACHED","Limit of $this->UseLimit has been reached"),'bad');
			return false;
		}

		//TODO:check order minimum

		$startDate = strtotime($this->StartDate);
		$endDate = strtotime($this->EndDate);
		$today = strtotime("today");
		$yesterday = strtotime("yesterday");

		if($this->EndDate && $endDate < $yesterday){
			//$this->getForm()->sessionMessage(_t("OrderCoupon.EXPIRED","This coupon has already expired"),'bad');
			return false;
		}

		if($this->StartDate && $startDate > $today){
			//$this->getForm()->sessionMessage(_t("OrderCoupon.TOOEARLY","It is too early to use this coupon"),'bad');
			return false;
		}

		return true;
	}

	function getDiscountValue($subTotal){
		if($this->DiscountAbsolute) {
			return abs($this->Value);
		}
		if($this->PercentageDiscount) {
			return $subTotal * ($this->PercentageDiscount / 100);
		}
		return 0;
	}


	function canDelete($member = null) {
		return $this->canEdit($member);
	}

	function canEdit($member = null) {
		if($this->UseCount()) {
			return false;
		}
		return true;
	}

	function getCMSFields() {
		$fields = parent::getCMSFields();

		if(!$this->Code){
			$this->Code = self::generateNewCode();
		}
		return $fields;
	}

	function setCode($code){
		$code = eregi_replace("[^[:alnum:]]", " ", $code); //EXPLAIN: what does this do?
		$code = trim(eregi_replace(" +", "", $code)); //gets rid of any white spaces
		$this->setField("Code", strtoupper($code));
	}

	/**
	 * Static function for generating new codes.
	 * @return string - the new code, in uppercase, based on the first x characters of md5(time())
	 */
	static function generateNewCode(){
		$code = null;
		do{
			$code = strtoupper(substr(md5(time()),0,self::$code_length));
		}while(DataObject::get('OrderCoupon',"\"Code\" = '$code'"));
		return $code;
	}

}

