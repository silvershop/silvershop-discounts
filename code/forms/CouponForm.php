<?php

/**
 * Enter cupon codes at checkout.
 * @package shop_discount
 */
class CouponForm extends Form {

	protected $config;

	public function __construct($controller, $name, Order $order) {
		$this->config = new CheckoutComponentConfig($order, false);
		$this->config->addComponent($couponcompoent = new CouponCheckoutComponent());

        $validator = Injector::inst()->create('CheckoutComponentValidator', $this->config);

        $fields = $this->config->getFormFields();

        $actions = new FieldList(
			FormAction::create('applyCoupon', _t('ApplyCoupon', 'Apply coupon'))
		);

        parent::__construct($controller, $name, $fields, $actions, $validator);

        $this->loadDataFrom($this->config->getData(), Form::MERGE_IGNORE_FALSEISH);

		$storeddata = $couponcompoent->getData($order);

		if(isset($storeddata['Code'])) {
			$actions->push(
				FormAction::create('removeCoupon', _t('RemoveCoupon', 'Remove coupon'))
			);
		}

		$controller->extend("updateCouponForm", $this, $order);
	}

	public function applyCoupon($data, $form) {
		// form validation has passed by this point, so we can save data
		$this->config->setData($form->getData());

		return $this->controller->redirectBack();
	}

	public function removeCoupon($data, $form) {
		Session::clear("cart.couponcode");
		return $this->controller->redirectBack();
	}

}
