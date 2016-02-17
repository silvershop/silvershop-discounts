<?php

/**
 * @package shop_discount
 */
class OrderDiscountModifier extends OrderModifier {

	private static $defaults = array(
		"Type" => "Deductable"
	);

	private static $many_many = array(
		"Discounts" => "Discount"
	);

	private static $many_many_extraFields = array(
		'Discounts' => array(
			'DiscountAmount' => 'Currency'
		)
	);

	private static $singular_name = "Discount";

	private static $plural_name = "Discounts";

	public function value($incoming) {
		$this->Amount = $this->getDiscount();

		return $this->Amount;
	}

	public function getDiscount() {
		$context = array();

		if($code = $this->getCode()) {
			$context['CouponCode'] = $code;
		}

		$order = $this->Order();
		$order->extend("updateDiscountContext", $context);

		$calculator = new Shop\Discount\Calculator($order, $context);
        $amount = $calculator->calculate();

		$this->setField('Amount', $amount);

        return $amount;
	}

    public function getCode() {
        $code = Session::get("cart.couponcode");

        if(!$code && $this->Order()->exists()) {
            $discount = $this->Order()->Discounts()->filter("Code:not", "")->first();

            if($discount) {
                return $discount->Code;
            }
        }

        return $code;
    }

	public function getSubTitle() {
		return $this->getUsedCodes();
	}

	public function getUsedCodes() {
		return implode(",",
			$this->Order()->Discounts()
				->filter("Code:not", "")
				->map('ID','Title')
		);
	}

    public function getAmount() {
        return $this->getDiscount();
    }

	public function ShowInTable() {
		return $this->Amount() > 0;
	}
}
