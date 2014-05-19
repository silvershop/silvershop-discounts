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
	public static $generated_code_length = 10;
	private static $minimum_code_length = null;

	public static function get_by_code($code) {
		return self::get()
				->filter('Code:nocase', $code)
				->first();
	}

	/**
	* Generates a unique code.
	* @todo depending on the length, it may be possible that all the possible
	*       codes have been generated.
	* @return string the new code
	*/
	public static function generate_code($length = null, $prefix = "") {
		$length = ($length) ? $length : self::$generated_code_length;
		$code = null;
		$generator = Injector::inst()->create('RandomGenerator');
		do{
			$code = $prefix.strtoupper(substr($generator->randomToken(), 0, $length));
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

	public function validate() {
		$result = parent::validate();

		$minLength = $this->config()->minimum_code_length;
		if($minLength !== null && (strlen($this->getField('Code')) < $minLength)) {
			$result->error(
				_t(
					'OrderCoupon.INVALIDMINLENGTH',
					'Coupon code must be at least {length} characters in length',
					array('length' => $this->config()->minimum_code_length)
				),
				'INVALIDMINLENGTH'
			);
		}

		return $result;
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
	* Forces codes to be alpha-numeric, uppercase, and trimmed
	*/
	public function setCode($code) {
		$code = trim(preg_replace('/[^0-9a-zA-Z]+/', '', $code));
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
