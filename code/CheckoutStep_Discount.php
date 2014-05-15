<?php

class CheckoutStep_Discount extends CheckoutStep{

	public static $allowed_actions = array(
		'discount',
		'CouponForm',
		'setcoupon'
	);

	protected function checkoutconfig() {
		$config = new CheckoutComponentConfig(ShoppingCart::curr(), true);
		$config->addComponent($comp = new CouponCheckoutComponent());
		$comp->setValidWhenBlank(true);

		return $config;
	}

	public function discount() {
		return array(
			'OrderForm' => $this->CouponForm()
		);
	}

	public function CouponForm() {		
		$form = new CheckoutForm($this->owner, "CouponForm", $this->checkoutconfig());
		$form->setActions(new FieldList(
			FormAction::create("setcoupon", "Continue")
		));
		$this->owner->extend('updateCouponForm', $form);

		return $form;
	}

	public function setcoupon($data, $form) {
		$this->checkoutconfig()->setData($form->getData());
		return $this->owner->redirect($this->NextStepLink());
	}

}
