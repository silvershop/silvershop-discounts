<?php

/**
 * @package silvershop-discounts
 */
class Discount extends DataObject {

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
        if ($order->Placed) {
            $discounts = self::get()->filter(array(
                'StartDate:LessThan' => $order->Created,
                'EndDate:GreaterThan' => $order->Created,
            ))
            ->filterAny(array(
                "Amount:GreaterThan" => 0,
                "Percent:GreaterThan" => 0
            ));
        } else {
            //get as many matching discounts as possible in a single query
            $discounts = self::get()
                ->filter("Active", true)
                //amount or percent > 0
                ->filterAny(array(
                    "Amount:GreaterThan" => 0,
                    "Percent:GreaterThan" => 0
            ));
        }

        $constraints = self::config()->constraints;

        foreach($constraints as $constraint){
            $discounts = singleton($constraint)
                            ->setOrder($order)
                            ->setContext($context)
                            ->filter($discounts);
        }

        // cull remaining invalid discounts problematically
        $validdiscounts = new ArrayList();

        foreach ($discounts as $discount) {
            if($discount->validateOrder($order, $context)){
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
                    HeaderField::create("ActionTitle", "Action", 3),
                    $typefield = SelectionGroup::create("Type",array(
                        new SelectionGroup_Item("Percent",
                            $percentgroup = FieldGroup::create(
                                $percentfield = NumericField::create("Percent", "Percentage", "0.00")
                                    ->setDescription("e.g. 0.05 = 5%, 0.5 = 50%, and 5 = 500%"),
                                $maxamountfield = CurrencyField::create("MaxAmount",
                                    _t("MaxAmount", "Maximum Amount")
                                )->setDescription(
                                    "The total allowable discount. 0 means unlimited."
                                )
                            ),
                            "Discount by percentage"
                        ),
                        new SelectionGroup_Item("Amount",
                            $amountfield = CurrencyField::create("Amount", "Amount", "$0.00"),
                            "Discount by fixed amount"
                        )
                    ))->setTitle("Type"),
                    OptionSetField::create("For", "Applies to", array(
                        "Order" => "Entire Order",
                        "Cart" => "Cart Subtotal",
                        "Shipping" => "Shipping Subtotal",
                        "Items" => "Each Individual Item"
                    )),
                    new Tab("Main",
                        HeaderField::create("ConstraintsTitle", "Constraints", 3),
                        LabelField::create(
                            "ConstraintsDescription",
                            "Configure the requirements an order must meet for this discount to be valid:"
                        )
                    ),
                    new TabSet("Constraints")
                )
            )
        ));

        if(!$this->isInDB()) {
            $fields->addFieldToTab("Root.Main",
                LiteralField::create("SaveNote",
                    "<p class=\"message good\">More constraints will show up after you save for the first time.</p>"
                ), "Constraints"
            );
        }

        if($count = $this->getUseCount()) {
            $fields->addFieldsToTab("Root.Usage", array(
                HeaderField::create("UseCount", sprintf("This discount has been used $count time%s.", $count > 1 ? "s" : "")),
                GridField::create(
                    "Orders",
                    "Orders",
                    $this->getAppliedOrders(),
                    GridFieldConfig_RecordViewer::create()
                        ->removeComponentsByType("GridFieldViewButton")
                )
            ));
        }

        if($params && isset($params['forcetype'])) {
            $valuefield = $params['forcetype'] == "Percent" ? $percentfield : $amountfield;
            $fields->insertAfter($valuefield,"Type");
            $fields->makeFieldReadonly("Type");
        } else if($this->Type && (double)$this->{$this->Type}) {
            $valuefield = $this->Type == "Percent" ? $percentfield : $amountfield;

            $fields->makeFieldReadonly("Type");
            $fields->insertAfter($valuefield, "ActionTitle");
            $fields->replaceField($this->Type,
                $valuefield->performReadonlyTransformation()
            );

            if($this->Type == "Percent") {
                $fields->insertAfter($maxamountfield, "Percent");
            }
        }

        $this->extend("updateCMSFields", $fields, $params);

        return $fields;
    }

    public function getDefaultSearchContext() {
        $context = parent::getDefaultSearchContext();
        $fields = $context->getFields();
        $fields->push(CheckboxField::create("HasBeenUsed"));
        //add date range filtering
        $fields->push(ToggleCompositeField::create("StartDate", "Start Date",array(
            DateField::create("q[StartDateFrom]", "From")
                        ->setConfig('showcalendar', true),
            DateField::create("q[StartDateTo]", "To")
                        ->setConfig('showcalendar', true)
        )));
        $fields->push(ToggleCompositeField::create("EndDate", "End Date",array(
            DateField::create("q[EndDateFrom]", "From")
                        ->setConfig('showcalendar', true),
            DateField::create("q[EndDateTo]", "To")
                        ->setConfig('showcalendar', true)
        )));
        //must be enabled in config, because some sites may have many products = slow load time, or memory maxes out
        //future solution is using an ajaxified field
        if(self::config()->filter_by_product){
            $fields->push(
                ListboxField::create("Products", "Products", Product::get()->map()->toArray())
                    ->setMultiple(true)
            );
        }
        if(self::config()->filter_by_category){
            $fields->push(
                ListboxField::create("Categories", "Categories", ProductCategory::get()->map()->toArray())
                    ->setMultiple(true)
            );
        }
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
     *
     * @param Order $order
     * @param array $context addional data to be checked in constraints.
     * @return boolean
     */
    public function validateOrder($order, $context = array()) {
        if(empty($order)) {
            $this->error(_t("Discount.NOORDER", "Order has not been started."));

            return false;
        }

        // active discount.
        if(!$this->Active) {
            $this->error(
                sprintf(_t("Discount.INACTIVE", "This %s is not active."), $this->i18n_singular_name())
            );

            return false;
        }

        $constraints = self::config()->constraints;

        foreach($constraints as $constraint) {
            $constraint = singleton($constraint)
                ->setOrder($order)
                ->setContext($context);

            if(!$constraint->check($this)) {
                $this->error($constraint->getMessage());

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
        if($this->Type === "Amount") {
            $discount += $this->Amount;
        }elseif($this->Percent) {
            $discount += $value * $this->Percent;
        }
        //prevent discounting more than the discountable amount
        if($discount > $value){
            $discount = $value;
        }

        return $discount;
    }

    public function getDiscountNice() {
        if($this->Type == "Percent") {
            return $this->dbObject("Percent")->Nice();
        }

        return $this->dbObject("Amount")->Nice();
    }

    /**
     * Get the number of times a discount has been used.
     *
     * @param int $orderID - ignore this order when counting uses
     *
     * @return int count
     */
    public function getUseCount($orderID = null) {
        $used = $this->getAppliedOrders(true);

        if($orderID) {
            $used = $used->exclude('ID', $orderID);
        }

        return $used->count();
    }

    /**
     * Returns whether this coupon is used.
     *
     * @param int $orderID
     *
     * @return boolean
     */
    public function isUsed($orderID = null) {
        return $this->getUseCount($orderID) > 0;
    }

    public function setPercent($value){
        $value = $value > 100 ? 100 : $value;

        $this->setField("Percent", $value);
    }

    /**
     * Map the single 'For' to the For"X" boolean fields
     * @param string $val
     */
    public function setFor($val) {
        if(!$val) return;

        $map = array(
            "Items" => array(1,0,0),
            "Cart" => array(0,1,0),
            "Shipping" => array(0,0,1),
            "Order" => array(0,1,1)
        );

        $mapping = $map[$val];
        $this->ForItems = $mapping[0];
        $this->ForCart = $mapping[1];
        $this->ForShipping = $mapping[2];
    }

    /**
     * @return string
     */
    public function getFor() {
        if($this->ForShipping && $this->ForCart) {
            return "Order";
        }

        if($this->ForShipping) {
            return "Shipping";
        }

        if($this->ForItems) {
            return "Items";
        }

        if($this->ForCart) {
            return "Cart";
        }
    }

    /**
     * Get the orders that this discount has been used on.
     *
     * @param $includeunpaid include orders where the payment process has started
     * less than 'unpaid_use_timeout' minutes ago.
     *
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

        if($includeunpaid) {
            $minutes = self::config()->unpaid_use_timeout;
            $timeouttime = date('Y-m-d H:i:s', strtotime("-{$minutes} minutes"));
            $orders = $orders->leftJoin("Payment", "\"Payment\".\"OrderID\" = \"Order\".\"ID\"")
                ->where(
                    "(\"Order\".\"Paid\" IS NOT NULL) OR ".
                    "(\"Payment\".\"Created\" > '$timeouttime' AND \"Payment\".\"Status\" NOT IN('Refunded', 'Void'))"
                );
        } else {
            $orders = $orders->where("\"Order\".\"Paid\" IS NOT NULL");
        }

        $this->extend('updateAppliedOrders', $orders, $includeunpaid);

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

    /**
     * Get the amount saved on the given order with this discount.
     *
     * @param  Order  $order order to match against
     * @return double  savings amount
     */
    public function getSavingsForOrder(Order $order) {
        $itemsavings = OrderAttribute::get()
            ->innerJoin("Product_OrderItem_Discounts", "\"OrderAttribute\".\"ID\" = \"Product_OrderItem_Discounts\".\"Product_OrderItemID\"")
            ->filter("Product_OrderItem_Discounts.DiscountID", $this->ID)
            ->filter("OrderAttribute.OrderID", $order->ID)
            ->sum("DiscountAmount");

        $modifiersavings = OrderAttribute::get()
            ->innerJoin("OrderDiscountModifier_Discounts", "\"OrderAttribute\".\"ID\" = \"OrderDiscountModifier_Discounts\".\"OrderDiscountModifierID\"")
            ->filter("OrderDiscountModifier_Discounts.DiscountID", $this->ID)
            ->filter("OrderAttribute.OrderID", $order->ID)
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


    /**
     * @deprecated
     */
    public function valid($order, $context = array()){
        Deprecation::notice("1.2", "use validateOrder instead");
        return $this->validateOrder($order, $context);
    }

}
