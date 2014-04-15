<?php

class Discount extends DataObject{
	
	private static $db = array(
		"Title" => "Varchar(255)", //store the promotion name, or whatever you like
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

	private static $has_one = array(
		"Group" => "Group"
	);

	private static $many_many = array(
		"Products" => "Product", //for restricting to product(s)
		"Categories" => "ProductCategory",
		"Zones" => "Zone"
	);

	private static $defaults = array(
		"Type" => "Percent",
		"Active" => true,
		"UseLimit" => 0,
		//"Cumulative" => 1,
		"ForItems" => 1
	);

	private static $field_labels = array(
		"DiscountNice" => "Discount",
		"UseLimit" => "Maximum number of uses",
		"MinOrderValue" => "Minimum subtotal of order"
	);

	private static $summary_fields = array(
		"Title",
		"DiscountNice",
		"StartDate",
		"EndDate"
	);

	private static $singular_name = "Discount";
	private static $plural_name = "Discounts";

	private static $default_sort = "EndDate DESC, StartDate DESC";

	public function getCMSFields($params = null) {
		$fields = new FieldList(array(
			$tabset = new TabSet("Root",
				$maintab = new Tab("Main",
					TextField::create("Title"),
					CheckboxField::create("Active", "Active")
						->setDescription("Enable/disable all use of this discount."),
					new FieldGroup("This discount applies to:",
						CheckboxField::create("ForItems", "Item values"),
						CheckboxField::create("ForShipping", "Shipping cost")
					),
					HeaderField::create("Criteria", "Order and Item Criteria", 4),
					LabelField::create(
						"CriteriaDescription",
						"Configure the requirements an order must meet for this coupon to be used with it:"
					),
					new FieldGroup("Valid date range:",
						CouponDatetimeField::create("StartDate", "Start Date / Time"),
						CouponDatetimeField::create(
							"EndDate",
							"End Date / Time (you should set the end time to 23:59:59, if you want to include the entire end day)"
						)
					),
					CurrencyField::create("MinOrderValue", "Minimum order subtotal"),
					NumericField::create("UseLimit", "Limit number of uses")
						->setDescription("Note: 0 = unlimited")
				)
			)
		));
		if($this->isInDB()){
			if($this->ForItems){
				$tabset->push(new Tab("Products",
					LabelField::create("ProductsDescription", "Select specific products that this discount applies to"),
					GridField::create("Products", "Products", $this->Products(), new GridFieldConfig_RelationEditor())
				));
				$tabset->push(new Tab("Categories",
					LabelField::create("CategoriesDescription", "Select specific product categories that this discount applies to"),
					GridField::create("Categories", "Categories", $this->Categories(), new GridFieldConfig_RelationEditor())
				));
//				$products->setPermissions(array('show'));
//				$categories->setPermissions(array('show'));
			}

			$tabset->push(new Tab("Zones",
				$zones = new GridField("Zones", "Zones", $this->Zones(), new GridFieldConfig_RelationEditor())
			));

			$maintab->Fields()->push(
				DropdownField::create("GroupID",
					"Member Belongs to Group",
					Group::get()->map('ID', 'Title')
				)->setHasEmptyDefault(true)
					->setEmptyString('-- Any Group --')
			);

			if($this->Type == "Percent"){
				$fields->insertBefore(
					NumericField::create("Percent", "Percentage discount")
						->setDescription("e.g. 0.05 = 5%, 0.5 = 50%, and 5 = 500%"), 
					"Active"
				);
			}elseif($this->Type == "Amount"){
				$fields->insertBefore(
					NumericField::create("Amount", "Discount value"),
					"Active"
				);
			}
		}else{
			$fields->insertBefore(
				new OptionsetField("Type", "Type of discount",
					array(
						"Percent" => "Percentage of subtotal (eg 25%)",
						"Amount" => "Fixed amount (eg $25.00)"
					)
				),
				"Active"
			);
			$fields->insertAfter(
				LiteralField::create(
					"warning", 
					"<p class=\"message good\">
						More criteria options can be set after an intial save
					</p>"
				),
				"Criteria"
			);
		}
		$this->extend("updateCMSFields", $fields);

		return $fields;
	}

	/**
	 * We have to tap in here to correct "50" to "0.5" for the percent
	 * field. This is a common user error and it's nice to just fix it
	 * for them.
	 *
	 * @param string $fieldName Name of the field
	 * @param mixed $value New field value
	 * @return DataObject $this
	 */
	public function setCastedField($fieldName, $value) {
		if ($fieldName == 'Percent' && $value > 1){
			$value /= 100.0;	
		}
		
		return parent::setCastedField($fieldName, $value);
	}

	/**
	 * Check if this coupon can be used with a given order
	 * @param Order $order
	 * @return boolean
	 */
	public function valid($order) {
		if(empty($order)){
			$this->error(_t("Discount.NOORDER", "Order has not been started."));
			return false;
		}
		//active
		if(!$this->Active){
			$this->error(
				sprintf(_t("Discount.INACTIVE", "This %s is not active."), $this->i18n_singular_name())
			);
			return false;
		}
		//order value
		if($this->MinOrderValue > 0 && $order->SubTotal() < $this->MinOrderValue){
			$this->error(
				sprintf(
					_t(
						"Discount.MINORDERVALUE",
						"Your cart subtotal must be at least %s to use this coupon"
					),
					$this->dbObject("MinOrderValue")->Nice()
				)
			);
			return false;
		}
		//time period
		$startDate = strtotime($this->StartDate);
		$endDate = strtotime($this->EndDate);
		$now = time();
		if($endDate && $endDate < $now){
			$this->error(_t("OrderCoupon.EXPIRED", "This coupon has already expired."));
			return false;
		}
		if($startDate && $startDate > $now){
			$this->error(_t("OrderCoupon.TOOEARLY", "It is too early to use this coupon."));
			return false;
		}
		//member group
		$group = $this->Group();
		$member = (Member::currentUser()) ? Member::currentUser() : $order->Member(); //get member
		if($group->exists() && (!$member || !$member->inGroup($group))){
			$this->error(_t("OrderCoupon.GROUPED", "Only specific members can use this coupon."));
			return false;
		}
		//zone
		$zones = $this->Zones();
		if($zones->exists()){
			$address = $order->getShippingAddress();
			if(!$address){
				$this->error(_t(
					"OrderCouponModifier.NOTINZONE",
					"This coupon can only be used for a specific shipping location."
				));
				return false;
			}
			$currentzones = Zone::get_zones_for_address($address);
			if(!$currentzones || !$currentzones->exists()){
				$this->error(_t(
					"OrderCouponModifier.NOTINZONE",
					"This coupon can only be used for a specific shipping location."
				));
				return false;
			}
			//check if any of currentzones is in zones
			$inzone = false;
			foreach($currentzones as $zone){
				if($zones->find('ID', $zone->ID)){
					$inzone = true;
					break;
				}
			}
			if(!$inzone){
				$this->error(_t(
					"OrderCouponModifier.NOTINZONE",
					"This coupon can only be used for a specific shipping location."
				));
				return false;
			}
		}
		//item qualification
		$items = $order->Items();
		$incart = false; //note that this means an order without items will always be invalid
		foreach($items as $item){
			//check at least one item in the cart meets the coupon's criteria
			if($this->itemMatchesCriteria($item)){
				$incart = true;
				break;
			}
		}
		if(!$incart){
			$this->error(_t(
				"OrderCouponModifier.PRODUCTNOTINORDER",
				"No items in the cart match the coupon criteria"
			));
			return false;
		}

		return true;
	}

	/**
	 * Work out the discount for a given order.
	 * @param Order $order
	 * @return double - discount amount
	 */
	public function orderDiscount(Order $order) {
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
				//prevent discount being greater than what is possible
				$discount += ($discountvalue > $discountable) ? $discountable : $discountvalue;
			}
		}
		if($this->ForShipping && class_exists('ShippingFrameworkModifier')){
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
	public function itemMatchesCriteria(OrderItem $item) {
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
		$this->extend("updateItemCriteria", $item, $match);

		return $match;
	}

	/**
	 * Works out the discount on a given value.
	 * @param float $subTotal
	 * @return calculated discount
	 */
	public function getDiscountValue($value) {
		$discount = 0;
		if($this->Amount) {
			$discount += abs($this->Amount);
		}
		if($this->Percent) {
			$discount += $value * $this->Percent;
		}

		return $discount;
	}

	public function getDiscountNice() {
		if($this->Type == "Percent"){

			return $this->dbObject("Percent")->Nice();
		}

		return $this->dbObject("Amount")->Nice();
	}

	protected function message($messsage, $type = "good") {
		$this->message = $messsage;
		$this->messagetype = $type;
	}

	protected function error($message) {
		$this->message($message, "bad");
	}

	public function getMessage() {
		return $this->message;
	}

	public function getMessageType() {
		return $this->messagetype;
	}

}