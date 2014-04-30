<?php

class Discount extends DataObject{
	
	private static $db = array(
		"Title" => "Varchar(255)", //store the promotion name, or whatever you like
		"Type" => "Enum('Percent,Amount','Percent')",
		"Amount" => "Currency",
		"Percent" => "Percentage",
		"Active" => "Boolean",

		"ForItems" => "Boolean",
		"ForShipping" => "Boolean"
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
					)
				)
			)
		));
		if($this->isInDB()){
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
				"UseLimit"
			);
		}
		$this->extend("updateCMSFields", $fields);

		return $fields;
	}

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
	* How many times the coupon has been used
	* @param string $order - ignore this order when counting uses
	* @return int
	*/
	public function getUseCount($order = null) {
		$filter = "\"Order\".\"Paid\" IS NOT NULL";
		if($order){
			$filter .= " AND \"OrderAttribute\".\"OrderID\" != ".$order->ID;
		}

		return OrderDiscountModifier::get()
			->where($filter)
			->innerJoin('Order', '"OrderAttribute"."OrderID" = "Order"."ID"')
			->count();
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