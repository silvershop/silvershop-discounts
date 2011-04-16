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

	public static $plural_name = "Order Coupon Reductions";

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



// ######################################## *** form functions (e. g. showform and getform)


	public function showForm() {
		return $this->Order()->Items();
	}

	function getForm($controller) {
		//Requirements::themedCSS("OrderCouponModifier");
		Requirements::javascript(THIRDPARTY_DIR."/jquery/jquery.js");
		Requirements::javascript(THIRDPARTY_DIR."/jquery-form/jquery.form.js");
		Requirements::javascript("ecommerce_discount_coupon/javascript/OrderCouponModifier.js");
		$fields = new FieldSet();
		$fields->push(new TextField('CouponCode',_t("OrderCouponModifier.COUPON", 'Coupon')));
		$actions = new FieldSet(new FormAction('applycoupon', _t("OrderCouponModifier.APPLY", 'Apply')));
		$controller = new OrderCouponModifier_AjaxController();
		$validator = null;
		return new OrderCouponModifier_Form($controller, 'ModifierForm', $fields, $actions, $validator);
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
	public function CanRemove() {
		return false;
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

	protected function LiveAmount() {
		if(!self::$actual_deductions) {
			self::$actual_deductions = 0;
			$subTotal = $this->LiveSubTotalAmount();
			if($obj = $this->LiveOrderCoupon()) {
				if($obj->DiscountAbsolute) {
					self::$actual_deductions += $obj->DiscountAbsolute;
					$this->debugMessage .= "<hr />usign absolutes for coupon discount: ".self::$actual_deductions;
				}
				if($obj->DiscountPercentage) {
					self::$actual_deductions += ($obj->DiscountPercentage / 100) * $subTotal;
					$this->debugMessage .= "<hr />usign percentages for coupon discount: ".self::$actual_deductions;
				}
			}
			if($subTotal < self::$actual_deductions) {
				self::$actual_deductions = $subTotal;
			}
			$this->debugMessage .= "<hr />final score: ".self::$actual_deductions;
			if(isset($_GET["debug"])) {
				print_r($this->debugMessage);
			}
		}
		return self::$actual_deductions;
	}


	protected function LiveDebugString() {
		return $this->debugMessage;
	}


// ######################################## *** Type Functions (IsChargeable, IsDeductable, IsNoChange, IsRemoved)

	protected function IsDeductable() {
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
		Order::save_current_order();
		if(Director::is_ajax()) {
			return ShoppingCart::return_message("success", _t("OrderCouponModifier.APPLIED", "Coupon applied"));
		}
		else {
			Director::redirect(CheckoutPage::find_link());
		}
		return;
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
