<?php

class Discount extends DataObject{
	
	private static $db = array(
		"Title" => "Varchar(255)", //store the promotion name, or whatever you like
		"Type" => "Enum('Percent,Amount','Percent')",
		"Amount" => "Currency",
		"Percent" => "Percentage",
		"Active" => "Boolean",
		"ForItems" => "Boolean",
		"ForCart" => "Boolean",
		"ForShipping" => "Boolean",
		"MaxAmount" => "Currency"
	);

	private static $belongs_many_many = array(
		'OrderItems' => 'Product_OrderItem',
		'DiscountModifiers' => 'OrderDiscountModifier'
	);

	private static $defaults = array(
		"Type" => "Percent",
		"Active" => true,
		"ForItems" => 1
	);

	private static $field_labels = array(
		"DiscountNice" => "Discount"
	);

	private static $summary_fields = array(
		"Title",
		"DiscountNice",
		"StartDate",
		"EndDate"
	);

	private static $searchable_fields = array(
		"Title"
	);

	private static $singular_name = "Discount";
	private static $plural_name = "Discounts";

	private static $default_sort = "EndDate DESC, StartDate DESC";

	/**
	 * Number of minutes ago to include for carts with paymetn start
	 * in the {@link getAppliedOrders()} function
	 * @var integer
	 */
	private static $unpaid_use_timeout = 10;

	/**
	 * Get the smallest possible list of discounts that can apply
	 * to a given order.
	 * @param  Order  $order order to check against
	 * @return DataList matching discounts
	 */
	public static function get_matching(Order $order, $context = array()) {
		//get as many matching discounts as possible in a single query
		$discounts = self::get()
			->filter("Active", true)
			//amount or percent > 0
			->filterAny(array(
				"Amount:GreaterThan" => 0,
				"Percent:GreaterThan" => 0
			));
		$constraints = self::config()->constraints;
		foreach($constraints as $constraint){
			$discounts = singleton($constraint)
							->setOrder($order)
							->setContext($context)
							->filter($discounts);
		}
		//cull remaining invalid discounts programatically
		$validdiscounts = new ArrayList();
		foreach ($discounts as $discount) {
			if($discount->valid($order, $context)){
				$validdiscounts->push($discount);
			}
		}

		return $validdiscounts;
	}

	public function getCMSFields($params = null) {
		//fields that shouldn't be changed once coupon is used
		$fields = new FieldList(array(
			$tabset = new TabSet("Root",
				$maintab = new Tab("Main",
					TextField::create("Title"),
					CheckboxField::create("Active", "Active")
						->setDescription("Enable/disable all use of this discount."),
					$typefield = SelectionGroup::create("Type",array(
						new SelectionGroup_Item("Percent",
							$percentgroup = FieldGroup::create(
								$percentfield = NumericField::create("Percent", "Discount", "0.00")
									->setDescription("e.g. 0.05 = 5%, 0.5 = 50%, and 5 = 500%"),
								$maxamountfield = CurrencyField::create("MaxAmount",
									_t("MaxAmount", "Maximum Amount")
								)->setDescription(
									"Don't allow the total discount amount to be more than this amount. '0' means the maximum discoun isn't limited."
								)
							),
							"Percent"
						),
						new SelectionGroup_Item("Amount",
							$amountfield = CurrencyField::create("Amount", "Discount", "$0.00"),
							"Amount"
						)
					))->setTitle("Type"),
					new FieldGroup("This discount applies to:",
						CheckboxField::create("ForItems", "Individual item values"),
						CheckboxField::create("ForCart", "Cart subtotal"),
						CheckboxField::create("ForShipping", "Shipping subtotal")
					),
					new Tab("Main",
						HeaderField::create("ConstraintsTitle", "Constraints", 3),
						LabelField::create(
							"CriteriaDescription",
							"Configure the requirements an order must meet for this discount to be valid:"
						)
					),
					new TabSet("Constraints")
				)
			)
		));
		if(!$this->isInDB()){
			$fields->addFieldToTab("Root.Main",
				LiteralField::create("SaveNote",
					"<p class=\"message good\">More constraints will show up after you save for the first time.</p>"
				), "Constraints"
			);
		}
		$this->extend("updateCMSFields", $fields, $params);
		if($count = $this->getUseCount()){
			$fields->addFieldsToTab("Root.Usage", array(
				HeaderField::create("UseCount", sprintf("This discount has been used $count time%s.", $count > 1 ? "s" : "")),
				HeaderField::create("TotalSavings", sprintf("A total of %s has been saved by customers using this discount.", $this->SavingsTotal),"3"),
				GridField::create(
					"Orders",
					"Orders",
					$this->getAppliedOrders(),
					GridFieldConfig_RecordViewer::create()
						->removeComponentsByType("GridFieldViewButton")
				)
			));
		}

		if($this->Type && $this->{$this->Type}){
			$valuefield = $this->Type == "Percent" ? $percentfield : $amountfield;
			$fields->removeByName("Type");
			$fields->insertAfter($valuefield, "Active");
			$fields->replaceField($this->Type,
				$valuefield->performReadonlyTransformation()
			);
			if($this->Type == "Percent"){
				$fields->insertAfter($maxamountfield, "Percent");
			}
		}

		return $fields;
	}

	public function getDefaultSearchContext() {
		$context = parent::getDefaultSearchContext();
		$fields = $context->getFields();
		//add date range filtering
		$fields->push(ToggleCompositeField::create("StartDate", "Start Date",array(
			DateField::create("StartDateFrom", "From")
						->setConfig('showcalendar', true),
			DateField::create("StartDateTo", "To")
						->setConfig('showcalendar', true)
		)));
		$fields->push(ToggleCompositeField::create("EndDate", "End Date",array(
			DateField::create("EndDateFrom", "From")
						->setConfig('showcalendar', true),
			DateField::create("EndDateTo", "To")
						->setConfig('showcalendar', true)
		)));

		$fields->push(CheckboxField::create("HasBeenUsed"));
		if($field = $fields->fieldByName("Code")){
			$field->setDescription("This can be a partial match.");
		}
		//get the array, to maniplulate name, and fullname seperately
		$filters = $context->getFilters();
		$filters['StartDateFrom'] = GreaterThanOrEqualFilter::create('StartDate');
		$filters['StartDateTo'] = LessThanOrEqualFilter::create('StartDate');
		$filters['EndDateFrom'] = GreaterThanOrEqualFilter::create('EndDate');
		$filters['EndDateTo'] = LessThanOrEqualFilter::create('EndDate');
		$context->setFilters($filters);

		return $context;
	}

	/**
	 * Check if this coupon can be used with a given order
	 * @param Order $order
	 * @return boolean
	 */
	public function valid($order, $context = array()) {
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
		$constraints = self::config()->constraints;
		foreach($constraints as $constraint){
			$dc = singleton($constraint)
				->setOrder($order)
				->setContext($context);
			if(!$dc->check($this)){
				$this->error($dc->getMessage());
				return false;
			}
		}

		return true;
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
	 * Works out the discount on a given value.
	 * @param float $subTotal
	 * @return calculated discount
	 */
	public function getDiscountValue($value) {
		$discount = 0;
		if($this->Amount) {
			$discount += $this->Amount;
		}
		if($this->Percent) {
			$discount += $value * $this->Percent;
		}
		//prevent discounting more than the discountable amount
		if($discount > $value){
			$discount = $value;
		}

		return $discount;
	}

	public function getDiscountNice() {
		if($this->Type == "Percent"){

			return $this->dbObject("Percent")->Nice();
		}

		return $this->dbObject("Amount")->Nice();
	}

	/**
	* Get the number of times a discount has been used
	* @param string $order - ignore this order when counting uses
	* @return int count
	*/
	public function getUseCount() {
		return $this->getAppliedOrders(true)->count();
	}

	public function isUsed(){
		return (boolean)$this->getUseCount();
	}

	public function setPercent($value){
		$value = $value > 100 ? 100 : $value;
		$this->setField("Percent", $value);
	}

	/**
	 * Get the orders that this discount has been used on.
	 * @return DataList list of orders
	 */
	public function getAppliedOrders($includeunpaid = false) {
		$orders =  Order::get()
			->innerJoin("OrderAttribute", "\"OrderAttribute\".\"OrderID\" = \"Order\".\"ID\"")
			->leftJoin("Product_OrderItem_Discounts", "\"Product_OrderItem_Discounts\".\"Product_OrderItemID\" = \"OrderAttribute\".\"ID\"")
			->leftJoin("OrderDiscountModifier_Discounts", "\"OrderDiscountModifier_Discounts\".\"OrderDiscountModifierID\" = \"OrderAttribute\".\"ID\"")
			->filterAny(array(
				"Product_OrderItem_Discounts.DiscountID" => $this->ID,
				"OrderDiscountModifier_Discounts.DiscountID" => $this->ID
			));
		if($includeunpaid){
			$minutes = self::config()->unpaid_use_timeout;
			$timeouttime = date('Y-m-d H:i:s', strtotime("-{$minutes} minutes"));
			$orders = $orders->leftJoin("Payment", "\"Payment\".\"OrderID\" = \"Order\".\"ID\"")
				->where(
					"(\"Order\".\"Paid\" IS NOT NULL) OR ".
					"(\"Payment\".\"Created\" > '$timeouttime')"
				);
		}else{
			$orders = $orders->where("\"Order\".\"Paid\" IS NOT NULL");
		}

		return $orders;
	}

	/**
	 * Get the total amount saved through the use of this discount,
	 * accross all paid orders.
	 * @return float amount saved
	 */
	public function getSavingsTotal() {
		$itemsavings = $this->OrderItems()
						->innerJoin("Order", "\"OrderAttribute\".\"OrderID\" = \"Order\".\"ID\"")
						->where("\"Order\".\"Paid\" IS NOT NULL")
						->sum("DiscountAmount");
		$modifiersavings = $this->DiscountModifiers()
						->innerJoin("Order", "\"OrderAttribute\".\"OrderID\" = \"Order\".\"ID\"")
						->where("\"Order\".\"Paid\" IS NOT NULL")
						->sum("DiscountAmount");
		
		return $itemsavings + $modifiersavings;
	}


	public function canView($member = null) {
		return true;
	}

	public function canCreate($member = null) {
		return true;
	}

	public function canDelete($member = null) {
		return !$this->isUsed();
	}

	public function canEdit($member = null) {
		return true;
	}

	//validation messaging functions
	protected $message;
	protected $messagetype;

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
