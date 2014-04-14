<?php
/**
 * Order Coupon Modifier
 * @package shop-discount
 */
class OrderCouponModifier extends OrderModifier {

	private static $has_one = array(
		"Coupon" => "OrderCoupon"
	);

	private static $defaults = array(
		"Type" => "Deductable"
	);

	private static $singular_name = "Coupon";
	private static $plural_name = "Coupons";

	/**
	 * @see OrderModifier::required()
	 */
	public function required() {
		return false;
	}

	/**
	 * Validate cart against coupon
	 */
	public function valid() {
		$order = $this->Order();
		if(!$order){
			return false;
		}
		$coupon = $this->Coupon();
		if(!$coupon){
			return false;
		}
		if(!$coupon->valid($order)){
			return false;
		}
		return true;
	}

	public function canRemove() {
		return true;
	}

	/**
	 * @see OrderModifier::value()
	 */
	public function value($incoming) {
		if($coupon = $this->Coupon()){
			$this->Amount = $coupon->orderDiscount($this->Order());
		}
		return $this->Amount;
	}

	/**
	 * @see OrderModifier::TableTitle()
	 */
	public function TableTitle() {
		if($coupon = $this->Coupon()) {
			return sprintf(_t("OrderCouponModifier.COUPON", "Coupon: %s"), $coupon->Title);
		}
		return _t("OrderCouponModifier.NOCOUPONENTERED", "No Coupon Entered");
	}

	/**
	 * Helper function for setting the coupon for this modifier.
	 * @param OrderCoupon $discountCoupon
	 */
	public function setCoupon(OrderCoupon $discountCoupon) {
		$this->CouponID = $discountCoupon->ID;
		$this->write();
	}

	//TODO: remove functions below

	/**
	* form functions (e. g. showform and getform)
	*/
	public static function show_form() {
		return true;
	}

	public function getModifierForm($controller) {
		return self::get_form();
	}
	public static function get_form($controller) {
		return new CouponForm($controller, "CouponForm");
	}

	/**
	* Type Functions (IsChargeable, IsDeductable, IsNoChange, IsRemoved)
	*/
	public function IsDeductable() {
		return true;
	}

	/**
	 * Gets the order subtotal
	* @return float
	*/
	protected function LiveSubTotalAmount() {
		if($this->OrderCoupon()) {
			$order = $this->Order();
			return $order->SubTotal();
		}
		return 0;
	}
}
