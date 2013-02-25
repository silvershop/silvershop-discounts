<?php
/**
 * Applies a discount to current order, if applicable, when entered at checkout.
 * @package shop-discounts
 */
class OrderCoupon extends DataObject {

	static $db = array(
		"Title" => "Varchar(255)", //store the promotion name, or whatever you like
		"Code" => "Varchar(25)",
		"Type" => "Enum('Percent,Amount','Percent')",
		"Amount" => "Currency",
		"Percent" => "Percentage",
		"Active" => "Boolean",

		"ForItems" => "Boolean",
		"ForShipping" => "Boolean",
		
		//Item / order validity criteria
		//"Cumulative" => "Boolean",
		"MinOrderValue" => "Currency",
		"UseLimit" => "Int",
		"StartDate" => "Datetime",
		"EndDate" => "Datetime"
	);
	
	static $has_one = array(
		"GiftVoucher" => "GiftVoucher_OrderItem", //used to link to gift voucher purchase
		"Group" => "Group"
	);
	
	static $many_many = array(
		"Products" => "Product", //for restricting to product(s)
		"Categories" => "ProductCategory",
		"Zones" => "Zone"
	);

	static $searchable_fields = array(
		"Code"
	);

	static $defaults = array(
		"Type" => "Percent",
		"Active" => true,
		"UseLimit" => 0,
		//"Cumulative" => 1,
		"ForItems" => 1
	);
	
	static $field_labels = array(
		"DiscountNice" => "Discount",
		"UseLimit" => "Maximum number of uses",
		"MinOrderValue" => "Minimum subtotal of order"
	);

	static $summary_fields = array(
		"Code",
		"Title",
		"DiscountNice",
		"StartDate",
		"EndDate"
	);

	static $singular_name = "Discount";
	function i18n_singular_name() { return _t("OrderCoupon.COUPON", "Coupon");}
	static $plural_name = "Discounts";
	function i18n_plural_name() { return _t("OrderCoupon.COUPONS", "Coupons");}

	static $default_sort = "EndDate DESC, StartDate DESC";
	static $code_length = 10;

	static function get_by_code($code){
		return DataObject::get_one('OrderCoupon',"\"Code\" = UPPER('$code')");
	}
	
	/**
	* Generates a unique code.
	* @return string - new code
	*/
	static function generateCode($length = null){
		$length = ($length) ? $length : self::$code_length;
		$code = null;
		do{
			$code = strtoupper(substr(md5(microtime()),0,$length));
		}while(DataObject::get('OrderCoupon',"\"Code\" = '$code'"));
		return $code;
	}
	
	protected $message = null, $messagetype = null;
	
	function getCMSFields($params = null){
		$fields = new FieldSet(
			new TextField("Title"),
			new TextField("Code"),
			new CheckboxField("Active","Active (allow this coupon to be used)"),
			new FieldGroup("This discount applies to:",
				new CheckboxField("ForItems","Item values"),
				new CheckboxField("ForShipping","Shipping cost")
			),
			new HeaderField("Criteria","Order and Item Criteria",4),
			new LabelField("CriteriaDescription", "Configure the requirements an order must meet for this coupon to be used with it:"),
			$tabset = new TabSet("Root",
				$maintab = new Tab("Main",
					new FieldGroup("Valid date range:",
						new CouponDatetimeField("StartDate","Start Date / Time"),
						new CouponDatetimeField("EndDate","End Date / Time (you should set the end time to 23:59:59, if you want to include the entire end day)")
					),
					new CurrencyField("MinOrderValue","Minimum order subtotal"),
					new NumericField("UseLimit","Limit number of uses (0 = unlimited)")
				)
			)
		);
		if($this->isInDB()){
			if($this->ForItems){
				$tabset->push(new Tab("Products",
					new LabelField("ProductsDescription", "Select specific products that this coupon can be uesd with"),
					$products = new ManyManyComplexTableField($this, "Products", "Product")
				));
				$tabset->push(new Tab("Categories",
					new LabelField("CategoriesDescription", "Select specific product categories that this coupon can be uesd with"),
					$categories = new ManyManyComplexTableField($this, "Categories", "ProductCategory")
				));
				$products->setPermissions(array('show'));
				$categories->setPermissions(array('show'));
			}
			
			$tabset->push(new Tab("Zones",
				$zones = new ManyManyComplexTableField($this, "Zones", "Zone")
			));
			
			$maintab->Fields()->push($grps = new DropdownField("GroupID", "Member Belongs to Group", DataObject::get('Group')->map('ID','Title')));
			$grps->setHasEmptyDefault(true);
			
			if($this->Type == "Percent"){
				$fields->insertBefore($percent = new NumericField("Percent","Percentage discount"), "Active");
				$percent->setTitle("Percent discount (eg 0.05 = 5%, 0.5 = 50%, and 5 = 500%)");
			}elseif($this->Type == "Amount"){
				$fields->insertBefore($amount = new NumericField("Amount","Discount value"), "Active");
			}
		}else{
			$fields->insertBefore(  
				new OptionsetField("Type","Type of discount", 
					array(
						"Percent" => "Percentage of subtotal (eg 25%)",
						"Amount" => "Fixed amount (eg $25.00)"
					)
				),
				"Active"
			);
			$fields->insertAfter(
				new LiteralField("warning","<p class=\"message good\">More criteria options can be set after an intial save</p>"),
				"Criteria"
			);
		}
		$this->extend("updateCMSFields",$fields);
		return $fields;
	}
	
	function populateDefaults() {
		parent::populateDefaults();
		$this->Code = self::generateCode();
	}
	
	/*
	 * Assign this coupon to a OrderCouponModifier on the given order
	 */
	function applyToOrder(Order $order){
		$modifier = $order->getModifier('OrderCouponModifier',true);
		if($modifier){
			$modifier->setCoupon($this);
			$modifier->write();
			$order->calculate(); //makes sure prices are up-to-date
			$order->write();
			$this->message(_t("OrderCoupon.APPLIED","Coupon applied."),"good");
			return true;
		}
		$this->error(_t("OrderCoupon.CANTAPPLY","Could not apply"));
		return false;
	}

	/**
	 * Check if this coupon can be used with a given order
	 * @return boolean
	 */
	function valid($order){
		if(empty($order)){
			$this->error(_t("OrderCoupon.NOORDER","Order has not been started."));
			return false;
		}
		if(!$this->Active){
			$this->error(_t("OrderCoupon.INACTIVE","This coupon is not active."));
			return false;
		}
		if($this->UseLimit > 0 && $this->getUseCount($order) >= $this->UseLimit) {
			$this->error(_t("OrderCoupon.LIMITREACHED","Limit of $this->UseLimit uses for this code has been reached."));
			return false;
		}
		if($this->MinOrderValue > 0 && $order->SubTotal() < $this->MinOrderValue){
			$this->error(sprintf(_t("OrderCouponModifier.MINORDERVALUE","Your cart subtotal must be at least %s to use this coupon"),$this->dbObject("MinOrderValue")->Nice()));
			return false;
		}
		$startDate = strtotime($this->StartDate);
		$endDate = strtotime($this->EndDate);
		$today = strtotime("today");
		$yesterday = strtotime("yesterday");
		if($endDate && $endDate < $yesterday){
			$this->error(_t("OrderCoupon.EXPIRED","This coupon has already expired."));
			return false;
		}
		if($startDate && $startDate > $today){
			$this->error(_t("OrderCoupon.TOOEARLY","It is too early to use this coupon."));
			return false;
		}
		$group = $this->Group();
		$member = (Member::currentUser()) ? Member::currentUser() : $order->Member(); //get member
		if($group->exists() && (!$member || !$member->inGroup($group))){
			$this->error(_t("OrderCoupon.GROUPED","Only specific members can use this coupon."));
			return false;
		}
		$zones = $this->Zones();
		if($zones->exists()){
			$address = $order->getShippingAddress();
			if(!$address){
				$this->error(_t("OrderCouponModifier.NOTINZONE","This coupon can only be used for a specific shipping location."));
				return false;
			}
			$currentzones = Zone::get_zones_for_address($address);
			if(!$currentzones || !$currentzones->exists()){
				$this->error(_t("OrderCouponModifier.NOTINZONE","This coupon can only be used for a specific shipping location."));
				return false;
			}
			//check if any of currentzones is in zones
			$inzone = false;
			foreach($currentzones as $zone){
				if($zones->find('ID',$zone->ID)){
					$inzone = true;
					break;
				}
			}
			if(!$inzone){
				$this->error(_t("OrderCouponModifier.NOTINZONE","This coupon can only be used for a specific shipping location."));
				return false;
			}
		}
		$items = $order->Items();
		$incart = false; //note that this means an order without items will always be invalid
		foreach($items as $item){
			if($this->itemMatchesCriteria($item)){ //check at least one item in the cart meets the coupon's criteria
				$incart = true;
				break;
			}
		}
		if(!$incart){
			$this->error(_t("OrderCouponModifier.PRODUCTNOTINORDER","No items in the cart match the coupon criteria"));
			return false;
		}
		$valid = true;
		$this->extend("updateValidation",$order, $valid, $error);
		if(!$valid){
			$this->error($error);
		}
		return $valid;
	}
	
	/**
	 * Work out the discount for a given order.
	 * @return discount
	 */
	function orderDiscount(Order $order){
		$discount = 0;
		if($this->ForItems){
			$items = $order->Items();
			$discountable = 0;
			foreach($items as $item){
				if($this->itemMatchesCriteria($item)){
					$discountable += $item->Total();
				}
			}
			if($discountable){
				$discountvalue = $this->getDiscountValue($discountable); 
				$discount += ($discountvalue > $discountable) ? $discountable : $discountvalue; //prevent discount being greater than what is possible
			}
		}
		if($this->ForShipping){
			if($shipping = $order->getModifier("ShippingFrameworkModifier")){
				$discount += $this->getDiscountValue($shipping->Amount);
			}
		}
		//ensure discount never goes above Amount
		if($this->Type == "Amount" && $discount > $this->Amount){
			$discount = $this->Amount;
		}
		return $discount;
	}
	
	/**
	 * Check if order item meets criteria of this coupon
	 * @param OrderItem $item
	 * @return boolean
	 */
	function itemMatchesCriteria(OrderItem $item){
		$products = $this->Products();
		if($products->exists()){
			if(!$products->find('ID', $item->ProductID)){
				return false;
			}
		}
		$categories = $this->Categories();
		if($categories->exists()){
			$itemproduct = $item->Product(true); //true forces the current version of product to be retrieved.
			if(!$itemproduct || !$categories->find('ID', $itemproduct->ParentID)){
				return false;
			}
		}
		$match = true;
		$this->extend("updateItemCriteria",$item, $match);
		return $match;				
	}
	
	/**
	 * Works out the discount on a given value.
	 * @param float $subTotal
	 * @return calculated discount
	 */
	function getDiscountValue($value){
		$discount = 0;
		if($this->Amount) {
			$discount += abs($this->Amount);
		}
		if($this->Percent) {
			$discount += $value * $this->Percent;
		}
		return $discount;
	}
	
	function getDiscountNice(){
		if($this->Type == "Percent"){
			return $this->dbObject("Percent")->Nice();
		}
		return $this->dbObject("Amount")->Nice();
	}
	
	/**
	* How many times the coupon has been used
	* @param string $order - ignore this order when counting uses
	* @return int
	*/
	function getUseCount($order = null) {
		$filter = "\"Order\".\"Paid\" IS NOT NULL";
		if($order){
			$filter .= " AND \"OrderAttribute\".\"OrderID\" != ".$order->ID;
		}
		$join = "INNER JOIN \"Order\" ON \"OrderAttribute\".\"OrderID\" = \"Order\".\"ID\"";
		$query = new SQLQuery("COUNT(\"OrderCouponModifier\")");
		$query = singleton("OrderCouponModifier")->buildSQL("","","",$join);
		$query->where = array($filter);
		$query->select("OrderCouponModifier.ID");
		return $query->unlimitedRowCount("\"OrderCouponModifier\".\"ID\"");
	}
	
	/**
	* Forces codes to be alpha-numeric, without spaces, and uppercase
	*/
	function setCode($code){
		$code = eregi_replace("[^[:alnum:]]", " ", $code);
		$code = trim(eregi_replace(" +", "", $code)); //gets rid of any white spaces
		$this->setField("Code", strtoupper($code));
	}
	
	function canDelete($member = null) {
		if($this->getUseCount()) {
			return false;
		}
		return true;
	}

	function canEdit($member = null) {
		if($this->getUseCount() && !$this->Active) {
			return false;
		}
		return true;
	}
	
	protected function message($messsage, $type = "good"){
		$this->message = $messsage;
		$this->messagetype = $type;
	}
	
	protected function error($message){
		$this->message($message, "bad");
	}
	
	function getMessage(){
		return $this->message;
	}
	
	function getMessageType(){
		return $this->messagetype;
	}

}