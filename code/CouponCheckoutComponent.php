<?php

/**
 * @package shop_discount
 */
class CouponCheckoutComponent extends CheckoutComponent {

	protected $validwhenblank = false;

	public function getFormFields(Order $order) {
		$fields = FieldList::create(
			TextField::create('Code', _t("CouponForm.COUPON",
				'Enter your coupon code if you have one.'
			))
		);

		return $fields;
	}

	public function setValidWhenBlank($valid) {
		$this->validwhenblank = $valid;
	}

	public function validateData(Order $order, array $data) {
		$result = new ValidationResult();
		$code = $data['Code'];

		if($this->validwhenblank && !$code){
			return $result;
		}

		//check the coupon exists, and can be used
		if($coupon = OrderCoupon::get_by_code($code)){
			if(!$coupon->validateOrder($order, array("CouponCode" => $code))){
				$result->error($coupon->getMessage(), "Code");
				throw new ValidationException($result);
			}
		}else{
			$result->error(
				_t("OrderCouponModifier.NOTFOUND", "Coupon could not be found"),
				"Code"
			);
			throw new ValidationException($result);
		}


		return $result;
	}

	public function getData(Order $order) {
		return array(
			'Code' => Session::get("cart.couponcode")
		);
	}

	public function setData(Order $order, array $data) {
		Session::set("cart.couponcode", strtoupper($data['Code']));

		$order->getModifier("OrderDiscountModifier", true);
	}
}
