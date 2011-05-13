<?php

/**
 * @author Nicolaas [at] sunnysideup.co.nz
 * @package: ecommerce
 * @sub-package: ecommerce_delivery
 * @description: Shipping calculation scheme based on SimpleShippingModifier.
 * It lets you set fixed shipping costs, or a fixed
 * cost for each region you're delivering to.
 */

class OrderCouponModifier extends OrderModifier {

// ######################################## *** model defining static variables (e.g. $db, $has_one)

	public static $db = array(
		'DebugString' => 'HTMLText',
		'SubTotalAmount' => 'Currency',
		'CouponCodeEntered' => 'Varchar(100)'
	);

	public static $has_one = array(
		"OrderCoupon" => "OrderCoupon"
	);

	public static $defaults = array("Type" => "Deductable");

// ######################################## *** cms variables + functions (e.g. getCMSFields, $searchableFields)

	function getCMSFields() {
		$fields = parent::getCMSFields();
		return $fields;
	}

	public static $singular_name = "Order Coupon Reduction";
		function i18n_singular_name() { return _t("OrderCouponModifier.ORDERCOUPONREDUCTION", "Order Coupon Reduction");}

	public static $plural_name = "Order Coupon Reductions";
		function i18n_plural_name() { return _t("OrderCouponModifier.ORDERCOUPONREDUCTIONS", "Order Coupon Reductions");}

// ######################################## *** other (non) static variables (e.g. protected static $special_name_for_something, protected $order)

	protected static $actual_deductions = 0;

	protected $debugMessage = "";

	protected static $code_entered = '';
		static function set_code_entered($s) {self::$code_entered = $s;}
		static function get_code_entered() {return self::$code_entered;}

// ######################################## *** CRUD functions (e.g. canEdit)
// ######################################## *** init and update functions
	public function runUpdate() {
		$this->checkField("SubTotalAmount");
		$this->checkField("CouponCodeEntered");
		$this->checkField("OrderCouponID");
		parent::runUpdate();
	}

	public static function init_for_order($className) {


	}

// ######################################## *** form functions (e. g. showform and getform)


	static function show_form() {
		return true;
	}

	function getModifierForm($controller) {
		return self::get_form();
	}
	static function get_form($controller) {
		//Requirements::themedCSS("OrderCouponModifier");
		//Requirements::javascript(THIRDPARTY_DIR."/jquery/jquery.js");
		//Requirements::javascript(THIRDPARTY_DIR."/jquery-form/jquery.form.js");
		//Requirements::javascript(ECOMMERCE_COUPON_DIR."/javascript/OrderCouponModifier.js");


		return new CouponForm(null,"CouponForm");

		/*
		$fields = new FieldSet();
		$fields->push(new TextField('CouponCode',_t("OrderCouponModifier.COUPON", 'Coupon')));
		$actions = new FieldSet(new FormAction('applycoupon', _t("OrderCouponModifier.APPLY", 'Apply')));
		$controller = new OrderCouponModifier_Controller();
		$validator = null;
		return new OrderCouponModifier_Form($controller, $this->Name.'Form', $fields, $actions, $validator);
		*/
	}


	public function updateCouponCodeEntered($code) {
		self::set_code_entered($code);
		if($this->CouponCodeEntered != $code) {
			$this->CouponCodeEntered = $code;
			$discountCoupon = DataObject::get_one("OrderCoupon", "\"Code\" = '".Convert::raw2sql($code)."'");
			if($discountCoupon && $discountCoupon->IsValid()) {
				$this->OrderCouponID = $discountCoupon->ID;
			}
			else {
				$this->OrderCouponID = 0;
			}
			$this->write();
		}
		return $this->CouponCodeEntered;
	}

	public function setCoupon($discountCoupon) {
		$this->OrderCouponID = $discountCoupon->ID;
		$this->write();
	}


	public function setCouponByID($discountCouponID) {
		$this->OrderCouponID = $discountCouponID;
		$this->write();
	}



// ######################################## *** template functions (e.g. ShowInTable, TableTitle, etc...) ... USES DB VALUES

	/**
	*@return boolean
	**/
	public function ShowInTable() {
		return true;
	}

	/**
	*@return boolean
	**/
	function CanBeHiddenAfterAjaxUpdate() {
		return !$this->CouponCodeEntered;
	}

	/**
	*@return boolean
	**/
	public function CanBeRemoved() {
		return true;
	}

	/**
	*@return float
	**/
	public function TableValue() {
		return $this->Amount * -1;
	}

	/**
	*@return float
	**/
	public function CartValue() {
		return $this->Amount * -1;
	}

	/**
	*@return string
	**/
	public function TableTitle() {
		return $this->Name;
	}

// ######################################## ***  inner calculations.... USES CALCULATED VALUES

// ######################################## *** calculate database fields: protected function Live[field name]  ... USES CALCULATED VALUES

	function LiveCouponCodeEntered (){
		if($newCode = trim(self::get_code_entered())) {
			return $newCode;
		}
		return $this->CouponCodeEntered;
	}

	/**
	*@return int
	**/
	protected function LiveName() {
		$code = $this->LiveCouponCodeEntered();
		$coupon = $this->LiveOrderCoupon();
		if($coupon) {
			return _t("OrderCouponModifier.COUPON", "Coupon '").$code._t("OrderCouponModifier.APPLIED", "' applied.");
		}
		elseif($code) {
			return  _t("OrderCouponModifier.COUPON", "Coupon '").$code._t("OrderCouponModifier.COULDNOTBEAPPLIED", "' could not be applied.");
		}
		return _t("OrderCouponModifier.NOCOUPONENTERED", "No Coupon Entered").$code;
	}

	/**
	*@return int
	**/
	protected function LiveOrderCouponID() {
		return $this->OrderCouponID;
	}

	/**
	*@return OrderCoupon
	**/
	protected function LiveOrderCoupon() {
		if($id = $this->LiveOrderCouponID()){
			return DataObject::get_by_id("OrderCoupon", $id);
		}
	}


	/**
	*@return float
	**/

	protected function LiveSubTotalAmount() {
		$order = $this->Order();
		return $order->SubTotal();
	}

	/**
	*@return float
	**/

	protected function LiveCalculationValue() {
		if(!self::$actual_deductions) {
			self::$actual_deductions = 0;
			$subTotal = $this->LiveSubTotalAmount();
			if($coupon = $this->OrderCoupon()) {
				self::$actual_deductions = $coupon->getDiscountValue($subTotal);
			}
			if($subTotal < self::$actual_deductions) {
				self::$actual_deductions = $subTotal;
			}
		}
		return self::$actual_deductions;
	}


	protected function LiveDebugString() {
		return $this->debugMessage;
	}


// ######################################## *** Type Functions (IsChargeable, IsDeductable, IsNoChange, IsRemoved)

	public function IsDeductable() {
		return true;
	}

// ######################################## *** standard database related functions (e.g. onBeforeWrite, onAfterWrite, etc...)

	function onBeforeWrite() {
		parent::onBeforeWrite();
	}

	function requireDefaultRecords() {
		parent::requireDefaultRecords();
	}

// ######################################## *** AJAX related functions

// ######################################## *** debug functions

}

//TODO: shift some of this to Modifer_Form
class CouponForm extends OrderModifierForm{

	function __construct($controller = null, $name){

		$fields = new FieldSet();
		$fields->push(new HeaderField('CouponHeading',_t("CouponForm.COUPONHEADING", 'Coupon/Voucher Code'),3));
		$fields->push(new TextField('Code',_t("CouponForm.COUPON", 'Enter your coupon code if you have one.')));
		$actions = new FieldSet(new FormAction('apply', _t("CouponForm.APPLY", 'Apply')));
		$validator = new CouponFormValidator(array('Code'));

		parent::__construct($controller, $name, $fields, $actions, $validator);
	}

	function apply($data,$form){

		$coupon = OrderCoupon::get_by_code($data['Code']);

		//add a new discount modifier to the cart, linking to the entered coupon

		//check it hasn't already been added/ used up
		//create new modifier
		$modifier = new OrderCouponModifier();
		$modifier->OrderCouponID = $coupon->ID;
		$modifier->write();

		ShoppingCart::add_new_modifier($modifier);

		//TODO: introduce retry/lockout time per IP address

		$successmessage = sprintf(_t("OrderCouponModifier.APPLIED",'%s - has been applied'),$coupon->Title);

		//Order::save_current_order();
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


class CouponFormValidator extends RequiredFields{

	function php($data) {
		$valid = parent::php($data);
		//check the coupon exists, and can be used
		if(!OrderCoupon::get_by_code($data['Code'])){
			$this->validationError('Code',_t("OrderCouponModifier.NOTFOUND","Sorry, that coupon could not be found"),"bad");
		}
		return $valid;
	}

}

//DELETE THESE:

class OrderCouponModifier_Form extends OrderModifierForm {

	public function applycoupon($data, $form) {
		if(isset($data['CouponCode'])) {
			$newOption = Convert::raw2sql($request['CouponCode']);

			$order = ShoppingCart::current_order();
			$modifiers = $order->Modifiers();
			if($modifiers) {
				foreach($modifiers as $modifier) {
					if ($modifier InstanceOf OrderCouponModifier) {
						$modifier->updateCouponCodeEntered($newOption);
					}
				}
			}

		}

	}
}


class OrderCouponModifier_Controller extends Controller {

	protected static $url_segment = "ordercouponmodifier";
		static function set_url_segment($s) {self::$url_segment = $s;}
		static function get_url_segment() {return self::$url_segment;}

	function ModifierForm($request) {
		if(isset($request['CouponCode'])) {
			$newOption = Convert::raw2sql($request['CouponCode']);
			$order = ShoppingCart::current_order();
			$modifiers = $order->Modifiers();
			if($modifiers) {
				foreach($modifiers as $modifier) {
					//TODO: absstract or tidy this to become part of OrderModifier_Controller
					if ($modifier InstanceOf OrderCouponModifier) {
						$modifier->updateCouponCodeEntered($newOption);
						$modifier->runUpdate();
						return ShoppingCart::return_message("success", _t("OrderCouponModifier.UPDATED", "Coupon updated."));
					}
				}
			}
		}
		return ShoppingCart::return_message("failure", _t("OrderCouponModifier.ERROR", "Coupon Code could NOT be updated."));
	}

	function Link() {
		return self::get_url_segment();
	}


}
