<?php
/**
 * Gift voucher products, when purchased will send out a voucher code to the customer via email.
 */
class GiftVoucherProduct extends Product{

	private static $db = array(
		"VariableAmount" => "Boolean",
		"MinimumAmount" => "Currency"
	);

	private static $singular_name = "Gift Voucher";
	private static $plural_name = "Gift Vouchers";

	private static $order_item = "GiftVoucher_OrderItem";

	public function getCMSFields() {
		$fields = parent::getCMSFields();
		$fields->addFieldToTab("Root.Pricing",
			new OptionsetField("VariableAmount", "Price", array(
				0 => "Fixed",
				1 => "Allow customer to choose"
			)),
			"BasePrice"
		);
		$fields->addFieldsToTab("Root.Pricing", array(
			//text field, because of CMS js validation issue
			$minimumamount = new TextField("MinimumAmount", "Minimum Amount")
		));
		$fields->removeByName("CostPrice");
		$fields->removeByName("Variations");
		$fields->removeByName("Model");
		return $fields;
	}

	public function canPurchase($member = null, $quantity = 1) {
		if(!self::config()->get('global_allow_purchase')){
			return false;
		}
		if(!$this->dbObject('AllowPurchase')->getValue()){
			return false;
		}
		if(!$this->isPublished()){
			return false;
		}
		return true;
	}

}

class GiftVoucherProduct_Controller extends Product_Controller{

	private static $allowed_actions = array(
		'Form'
	);
    
	public function Form() {
		$form = parent::Form();
		if($this->VariableAmount){
			$form->setSaveableFields(array(
				"UnitPrice"
			));
			$form->Fields()->push(
				//TODO: set minimum amount
				$giftamount = new CurrencyField("UnitPrice", "Amount", $this->BasePrice)
			);
			$giftamount->setForm($form);
		}
		$form->setValidator($validator = new GiftVoucherFormValidator(array(
			"Quantity", "UnitPrice"
		)));
		return $form;
	}

}

class GiftVoucherFormValidator extends RequiredFields{

	public function php($data) {
		$valid =  parent::php($data);
		if($valid){
			$controller = $this->form->Controller();
			if($controller->VariableAmount){
				$giftvalue = $data['UnitPrice'];
				if($controller->MinimumAmount > 0 && $giftvalue < $controller->MinimumAmount){
					$this->validationError("UnitPrice", "Gift value must be at least ".$controller->MinimumAmount);
					return false;
				}
				if($giftvalue <= 0){
					$this->validationError("UnitPrice", "Gift value must be greater than 0");
					return false;
				}
			}
		}
		return $valid;
	}

}

class GiftVoucher_OrderItem extends Product_OrderItem{

	private static $db = array(
		"GiftedTo" => "Varchar"
	);

	private static $has_many = array(
		"Coupons" => "OrderCoupon"
	);

	public static $required_fields = array(
		"UnitPrice"
	);

	/**
	 * Don't get unit price from product
	 */
	public function UnitPrice() {
		if($this->Product()->VariableAmount){
			return $this->UnitPrice;
		}
		return parent::UnitPrice();
	}

	/**
	 * Create vouchers on order payment success event
	 */
	public function onPayment() {
		parent::onPayment();
		if($this->Coupons()->Count() < $this->Quantity){
			$remaining = $this->Quantity - $this->Coupons()->Count();
			for($i = 0; $i < $remaining; $i++){
				if($coupon = $this->createCoupon()){
					$this->sendVoucher($coupon);
				}
			}
		}
	}

	/**
	 * Create a new coupon, based on this orderitem
	 * @return OrderCoupon
	 */
	public function createCoupon() {
		if(!$this->Product()){
			return false;
		}
		$coupon = new OrderCoupon(array(
			"Title" => $this->Product()->Title,
			"Type" => "Amount",
			"Amount" => $this->UnitPrice,
			"UseLimit" => 1,
			"MinOrderValue" => $this->UnitPrice //safeguard that means coupons must be used entirely
		));
		$this->extend("updateCreateCupon", $coupon);
		$coupon->write();
		$this->Coupons()->add($coupon);
		return $coupon;
	}

	/*
	 * Send the voucher to the appropriate email
	 */
	public function sendVoucher(OrderCoupon $coupon) {
		$from = Email::getAdminEmail();
		$to = $this->Order()->getLatestEmail();
		$subject = _t("Order.GIFTVOUCHERSUBJECT", "Gift voucher");
		$email = new Email($from, $to, $subject);
		$email->setTemplate("GiftVoucherEmail");
		$email->populateTemplate(array(
			'Coupon' => $coupon
		));
		return $email->send();
	}

}
