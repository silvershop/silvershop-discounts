<?php

/**
 * Enter cupon codes at checkout.
 * @package shop_discount
 */
class CouponForm extends OrderModifierForm{

	function __construct($controller = null, $name){
		$fields = new FieldSet();
		$fields->push(new HeaderField('CouponHeading',_t("CouponForm.COUPONHEADING", 'Coupon/Voucher Code'),3));
		$fields->push(new TextField('Code',_t("CouponForm.COUPON", 'Enter your coupon code if you have one.')));
		$actions = new FieldSet(new FormAction('apply', _t("CouponForm.APPLY", 'Apply')));
		$validator = new CouponFormValidator(array('Code'));

		parent::__construct($controller, $name, $fields, $actions, $validator);
	}

	/**
	 * Apply a given cupon to the current order, based on passed code.
	 * 
	 * @param array $data
	 * @param Form $form
	 */
	function apply($data,$form){
		
		$order = ShoppingCart::current_order();
		$coupon = OrderCoupon::get_by_code($data['Code']); //already validated

		//add a new discount modifier to the cart, linking to the entered coupon
		if($modifier = $order->getModifier('OrderCouponModifier',true)){
			$modifier->setCoupon($coupon);
			$modifier->write();
			$order->calculate(); //makes sure prices are up-to-date
		}

		$successmessage = sprintf(_t("OrderCouponModifier.APPLIED",'"%s" coupon has been applied'),$coupon->Title);

		if(Director::is_ajax()) {
			return ShoppingCart::return_message("success",$successmessage);
		}
		else {
			$form->sessionMessage($successmessage,"good");
			Director::redirect(CheckoutPage::find_link());
		}
		return;
	}
	
}

/**
 * Validate coupon code form.
 * @package shop_discount
 */
class CouponFormValidator extends RequiredFields{

	function php($data) {
		$valid = parent::php($data);
		$cart = ShoppingCart::getInstance();
		$order = $cart->current();
		if(!$order || !$order->Items()){
			$this->validationError('Code',_t("OrderCouponModifier.NOORDERSTARTED","No order found. You must start an order before using a coupon."),"bad");
			return false;
		}
		//check the coupon exists, and can be used
		if($coupon = OrderCoupon::get_by_code($data['Code'])){
			if(!$coupon->valid($cart)){
				$this->validationError('Code',$coupon->validationerror,"bad");
				return false;
			}			
		}else{
			$this->validationError('Code',_t("OrderCouponModifier.NOTFOUND","Coupon could not be found"),"bad");
			$valid = false;
		}
		return $valid;
	}

}

/**
 * Decorates checkout with CouponForm
 * @package shop_discount
 */
class CouponFormCheckoutDecorator extends Extension{
	
	public static $allowed_actions = array(
		'CouponForm'
	);
	
	function CouponForm(){
		if(ShoppingCart::order_started()){
			return new CouponForm($this->owner,'CouponForm');
		}
	}
	
}