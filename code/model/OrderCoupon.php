<?php
/**
 * Applies a discount to current order, if applicable, when entered at checkout.
 * @package shop-discounts
 */
class OrderCoupon extends Discount {

	private static $has_one = array(
		//used to link to gift voucher purchase
		"GiftVoucher" => "GiftVoucher_OrderItem"
	);

	private static $searchable_fields = array(
		"Code"
	);

	private static $summary_fields = array(
		"Code",
		"Title",
		"DiscountNice",
		"StartDate",
		"EndDate"
	);

	private static $singular_name = "Coupon";
	private static $plural_name = "Coupons";
	public static $code_length = 10;

	public static function get_by_code($code) {
		return self::get()
				->filter('Code:nocase', $code)
				->first();
	}

	/**
	* Generates a unique code.
	* @return string the new code
	*/
	public static function generate_code($length = null) {
		$length = ($length) ? $length : self::$code_length;
		$code = null;
		do{
			$code = strtoupper(substr(md5(microtime()), 0, $length));
		}while(
			self::get()->filter("Code:nocase", $code)->exists()
		);

		return $code;
	}

	public function getCMSFields($params = null) {
		$fields = parent::getCMSFields();
		$fields->addFieldsToTab(
			"Root.Main", array(
				TextField::create("Code")->setMaxLength(25),
				NumericField::create("UseLimit", "Limit number of uses")
						->setDescription("Note: 0 = unlimited")
			), 
			"Active"
		);
		return $fields;
	}

	/**
	 * Autogenerate the code, if needed
	 */
	protected function onBeforeWrite() {
		if (empty($this->Code)){
			$this->Code = self::generate_code();
		}
		parent::onBeforeWrite();
	}


	/**
	* Forces codes to be alpha-numeric, without spaces, and uppercase
	*/
	public function setCode($code) {
		$code = preg_replace('/[^0-9a-zA-Z]+/', '', $code);
//		$code = trim(preg_replace('/\s+/', "", $code)); //gets rid of any white spaces
		$this->setField("Code", strtoupper($code));
	}

	public function canDelete($member = null) {
		if($this->getUseCount()) {
			return false;
		}
		return true;
	}

	public function canEdit($member = null) {
		if($this->getUseCount() && !$this->Active) {
			return false;
		}
		return true;
	}

}
