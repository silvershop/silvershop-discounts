<?php

class CodeDiscountConstraint extends DiscountConstraint{
	
	private static $db = array(
		"Code" => "Varchar(25)"
	);

	//cms field is added in OrderCoupon class

	public function filter(DataList $list) {
		if($code = $this->findCouponCode()){
			$list = $list
				->where("(\"Code\" IS NULL) OR (\"Code\" = '$code')");
		}else{
			$list = $list->where("\"Code\" IS NULL");
		}

		return $list;
	}

	public function check(Discount $discount) {
		$code = strtolower($this->findCouponCode());
		if($discount->Code && ($code != strtolower($discount->Code))){
			$this->error("Coupon code doesn't match");
			return false;
		}

		return true;
	}

	protected function findCouponCode() {
		return isset($this->context['CouponCode']) ? $this->context['CouponCode'] : null;
	}

}