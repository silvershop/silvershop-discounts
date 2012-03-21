<?php

/**
 * Applies a discount to current order, if applicable, when entered at checkout.
 * @package shop-discounts
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
		//per-order use limit
	);
	
	static $defaults = array(
		"Active" => true,
		"UseLimit" => 1
	);

	static $many_many = array(
		"Products" => "Product" //for restricting to product(s)
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
	function i18n_singular_name() { return _t("OrderCoupon.COUPON", "Coupon");}
	static $plural_name = "Discounts";
	function i18n_plural_name() { return _t("OrderCoupon.COUPONS", "Coupons");}

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
	
	function getCMSFields($params = null){
		$fields = parent::getCMSFields($params);
		$fields->removeByName("Products");
		$products = new ManyManyComplexTableField($this, "Products", "Product");
		$fields->addFieldToTab("Root.Products", $products);
		return $fields;
	}
	
	function populateDefaults() {
		$this->Code = self::generateCode();
	}

	/**
	 * Self check if the coupon can be used.
	 * @return boolean
	 */
	function valid($cart = null){
		if(!$this->Active){
			$this->validationerror = _t("OrderCoupon.INACTIVE","This coupon is not active.");
			return false;
		}
		if($this->UseLimit > 0 && $this->getUseCount() < $this->UseLimit) {
			$this->validationerror = _t("OrderCoupon.LIMITREACHED","Limit of $this->UseLimit uses for this code has been reached.");
			return false;
		}
		$startDate = strtotime($this->StartDate);
		$endDate = strtotime($this->EndDate);
		$today = strtotime("today");
		$yesterday = strtotime("yesterday");
		if($endDate && $endDate < $yesterday){
			$this->validationerror = _t("OrderCoupon.EXPIRED","This coupon has already expired.");
			return false;
		}
		if($startDate && $startDate > $today){
			$this->validationerror = _t("OrderCoupon.TOOEARLY","It is too early to use this coupon.");
			return false;
		}
		
		if($cart && $order = $cart->current()){
			if($this->MinOrderValue && $order->SubTotal() < $this->MinOrderValue){
				$this->validationerror = sprintf(_t("OrderCouponModifier.MINORDERVALUE","The minimum order value has not been reached."),$this->MinOrderValue);
				return false;
			}
			$products = $this->Products();
			if($products->exists()){
				$incart = false;
				foreach($products as $product){
					if($cart->get($product)){
						$incart = true;
						break;
					}
				}
				if(!$incart){
					$this->validationerror = _t("OrderCouponModifier.PRODUCTNOTINCART","The required product is not in the cart.");
					return false;
				}
			}
			
			//TODO: limited number of uses
			
		}
		
		return true;
	}
	
	/**
	 * Works out the discount on a given value.
	 * @param float $subTotal
	 * @return calculated discount
	 */
	function getDiscountValue($subTotal){
		$discount = 0;
		if($this->Amount) {
			$discount += abs($this->Amount);
		}
		if($this->Percent) {
			$discount += $subTotal * $this->Percent;
		}
		return $discount;
	}
	
	/**
	* How many times the coupon has been used.
	* @return int
	*/
	function getUseCount() {
		$objects = DataObject::get("OrderCouponModifier", "\"CouponID\" = ".$this->ID);
		if($objects) {
			return $objects->count();
		}
		return 0;
	}
	
	/**
	* Forces codes to be alpha-numeric, without spaces, and uppercase
	*/
	function setCode($code){
		$code = eregi_replace("[^[:alnum:]]", " ", $code);
		$code = trim(eregi_replace(" +", "", $code)); //gets rid of any white spaces
		$this->setField("Code", strtoupper($code));
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