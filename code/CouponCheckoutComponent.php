<?php

class CouponCheckoutComponent extends CheckoutComponent{

	public function getFormFields(Order $order) {
		$fields = FieldList::create(
			TextField::create('Code', _t("CouponForm.COUPON", 
				'Enter your coupon code if you have one.'
			))
		);

		return $fields;
	}

	public function validateData(Order $order, array $data) {
		$result = new ValidationResult();
		//check the coupon exists, and can be used
		if($coupon = OrderCoupon::get_by_code($data['Code'])){
			if(!$coupon->valid($order, array("CouponCode" => $data['Code']))){
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
		return $valid;
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