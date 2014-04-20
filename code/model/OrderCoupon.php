<?php
/**
 * Applies a discount to current order, if applicable, when entered at checkout.
 * @package shop-discounts
 */
class OrderCoupon extends Discount {

	private static $db = array(
		"Code" => "Varchar(25)"
	);

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

	protected $message = null;
	protected $messagetype = null;

	public function getCMSFields($params = null) {
		$fields = parent::getCMSFields();

		$fields->addFieldsToTab(
			"Root.Main", array(
				TextField::create("Code"),
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

	/*
	 * Assign this coupon to a OrderCouponModifier on the given order
	 */
	public function applyToOrder(Order $order) {
		$modifier = $order->getModifier('OrderCouponModifier', true);
		if($modifier){
			$modifier->setCoupon($this);
			$modifier->write();
			$order->calculate(); //makes sure prices are up-to-date
			$order->write();
			$this->message(_t("OrderCoupon.APPLIED", "Coupon applied."), "good");
			return true;
		}
		$this->error(_t("OrderCoupon.CANTAPPLY", "Could not apply"));
		return false;
	}

	/**
	 * Check if this coupon can be used with a given order
	 * @param Order $order
	 * @return boolean
	 */
	public function valid($order) {
		if(!parent::valid($order)){
			return false;
		}
		if($this->UseLimit > 0 && $this->getUseCount($order) >= $this->UseLimit) {
			$this->error(_t(
				"OrderCoupon.LIMITREACHED",
				"Limit of $this->UseLimit uses for this code has been reached."
			));
			return false;
		}
		$valid = true;
		$this->extend("updateValidation", $order, $valid, $error);
		if(!$valid){
			$this->error($error);
		}

		return $valid;
	}

	/**
	* How many times the coupon has been used
	* @param string $order - ignore this order when counting uses
	* @return int
	*/
	public function getUseCount($order = null) {
		$filter = "\"Order\".\"Paid\" IS NOT NULL";
		if($order){
			$filter .= " AND \"OrderAttribute\".\"OrderID\" != ".$order->ID;
		}

		return OrderCouponModifier::get()
			->where($filter)
			->innerJoin('Order', '"OrderAttribute"."OrderID" = "Order"."ID"')
			->count();
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
		//TODO: reintroduce this code, once table fields have been fixed to paginate in read-only state
		/*if($this->getUseCount() && !$this->Active) {
			return false;
		}*/
		return true;
	}

}
