<?php

namespace SilverShop\Discounts\Model;

use SilverStripe\ORM\DataObject;
use SilverShop\Model\Order;
use SilverStripe\ORM\ArrayList;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\TabSet;
use SilverStripe\Forms\Tab;
use SilverStripe\Forms\TextField;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\HeaderField;
use SilverStripe\Forms\SelectionGroup;
use SilverStripe\Forms\SelectionGroup_Item;
use SilverStripe\Forms\FieldGroup;
use SilverStripe\Forms\NumericField;
use SilverStripe\Forms\CurrencyField;
use SilverStripe\Forms\OptionsetField;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldConfig_RecordViewer;
use SilverStripe\Forms\ToggleCompositeField;
use SilverStripe\Forms\DateField;
use SilverStripe\Forms\ListboxField;
use SilverShop\Page\Product;
use SilverShop\Page\ProductCategory;
use SilverStripe\Security\PermissionProvider;
use SilverStripe\Security\Permission;
use SilverStripe\ORM\Filters\GreaterThanOrEqualFilter;
use SilverStripe\ORM\Filters\LessThanOrEqualFilter;
use SilverShop\Model\OrderAttribute;
use SilverShop\Model\OrderItem;
use SilverStripe\Dev\Deprecation;
use SilverShop\Discounts\Model\Modifiers\OrderDiscountModifier;
use SilverStripe\Core\Injector\Injector;
use SilverShop\Discounts\Extensions\Constraints\DiscountConstraint;
use SilverStripe\ORM\FieldType\DBCurrency;

class Discount extends DataObject implements PermissionProvider
{
    private static $db = [
        'Title' => 'Varchar(255)', //store the promotion name, or whatever you like
        'Type' => "Enum('Percent,Amount','Percent')",
        'Amount' => 'Currency',
        'Percent' => 'Percentage',
        'Active' => 'Boolean',
        'ForItems' => 'Boolean',
        'ForCart' => 'Boolean',
        'ForShipping' => 'Boolean',
        'MaxAmount' => 'Currency'
    ];

    private static $belongs_many_many = [
        'OrderItems' => OrderItem::class,
        'DiscountModifiers' => OrderDiscountModifier::class
    ];

    private static $defaults = [
        'Type' => 'Percent',
        'Active' => true,
        'ForItems' => 1
    ];

    private static $field_labels = [
        'DiscountNice' => 'Discount'
    ];

    private static $summary_fields = [
        'Title',
        'DiscountNice' => 'Discount',
        'StartDate',
        'EndDate'
    ];

    private static $searchable_fields = [
        'Title'
    ];

    private static $singular_name = 'Discount';

    private static $plural_name = 'Discounts';

    private static $default_sort = 'EndDate DESC, StartDate DESC';

    private static $table_name = 'SilverShop_Discount';

    protected $message;

    protected $messagetype;

    /**
     * Number of minutes ago to include for carts with paymetn start
     * in the {@link getAppliedOrders()} function
     *
     * @var integer
     */
    private static $unpaid_use_timeout = 10;

    /**
     * @return array
     */
    public function getConstraints()
    {
        $extensions = $this->getExtensionInstances();
        $output = [];

        foreach ($extensions as $extension) {
            if ($extension instanceof DiscountConstraint) {
                $output[] = get_class($extension);
            }
        }

        return $output;
    }

    /**
     * Get the smallest possible list of discounts that can apply
     * to a given order.
     *
     * @param  Order $order order to check against
     * @param array $context
     * @return ArrayList matching discounts
     */
    public static function get_matching(Order $order, $context = [])
    {
        //get as many matching discounts as possible in a single query
        $discounts = self::get()
            ->filter('Active', true)
            //amount or percent > 0
            ->filterAny(
                [
                'Amount:GreaterThan' => 0,
                'Percent:GreaterThan' => 0
                ]
            );

        $constraints = Injector::inst()->create(static::class)->getConstraints();

        foreach ($constraints as $constraint) {
            $discounts = singleton($constraint)
                ->setOrder($order)
                ->setContext($context)
                ->filter($discounts);
        }

        // cull remaining invalid discounts problematically
        $validdiscounts = new ArrayList();

        foreach ($discounts as $discount) {
            if ($discount->validateOrder($order, $context)) {
                $validdiscounts->push($discount);
            }
        }

        return $validdiscounts;
    }

    public function getCMSFields($params = null)
    {
        //fields that shouldn't be changed once coupon is used
        $fields = new FieldList(
            [
            new TabSet(
                'Root',
                new Tab(
                    'Main',
                    TextField::create('Title'),
                    CheckboxField::create('Active', 'Active')
                        ->setDescription('Enable/disable all use of this discount.'),
                    HeaderField::create('ActionTitle', 'Action', 3),
                    $typefield = SelectionGroup::create(
                        'Type',
                        [
                        new SelectionGroup_Item(
                            'Percent',
                            $percentgroup = FieldGroup::create(
                                $percentfield = NumericField::create('Percent', 'Percentage', '0.00')
                                    ->setScale(null)
                                    ->setDescription('e.g. 0.05 = 5%, 0.5 = 50%, and 5 = 500%'),
                                $maxamountfield = CurrencyField::create(
                                    'MaxAmount',
                                    _t('MaxAmount', 'Maximum Amount')
                                )->setDescription(
                                    'The total allowable discount. 0 means unlimited.'
                                )
                            ),
                            'Discount by percentage'
                        ),
                        new SelectionGroup_Item(
                            'Amount',
                            $amountfield = CurrencyField::create('Amount', 'Amount', '$0.00'),
                            'Discount by fixed amount'
                        )
                        ]
                    )->setTitle('Type'),
                    OptionSetField::create(
                        'For',
                        'Applies to',
                        [
                        'Order' => 'Entire order',
                        'Cart' => 'Cart subtotal',
                        'Shipping' => 'Shipping subtotal',
                        'Items' => 'Each individual item'
                        ]
                    )
                ),
                new Tab(
                    'Constraints',
                    TabSet::create('ConstraintsTabs', $general = new Tab('General', 'General'))
                )
            )
            ]
        );

        if (!$this->isInDB()) {
            $general->push(
                LiteralField::create(
                    'SaveNote',
                    sprintf(
                        '<p class="message good">%s</p>',
                        _t(__CLASS__ . 'SaveNote', 'More constraints will show up after you save for the first time.')
                    )
                )
            );
        }

        if ($count = $this->getUseCount()) {
            $fields->addFieldsToTab(
                'Root.Usage',
                [
                HeaderField::create('UseCount', sprintf("This discount has been used $count time%s.", $count > 1 ? 's' : '')),
                GridField::create(
                    'Orders',
                    'Orders',
                    $this->getAppliedOrders(),
                    GridFieldConfig_RecordViewer::create()
                        ->removeComponentsByType('GridFieldViewButton')
                )
                ]
            );
        }

        if ($params && isset($params['forcetype'])) {
            $valuefield = $params['forcetype'] === 'Percent' ? $percentfield : $amountfield;
            $fields->insertAfter($valuefield, 'Type');
            $fields->makeFieldReadonly('Type');
        } elseif ($this->Type && (double)$this->{$this->Type}) {
            $valuefield = $this->Type === 'Percent' ? $percentfield : $amountfield;

            $fields->makeFieldReadonly('Type');
            $fields->insertAfter($valuefield, 'ActionTitle');
            $fields->replaceField(
                $this->Type,
                $valuefield->performReadonlyTransformation()
            );

            if ($this->Type === 'Percent') {
                $fields->insertAfter($maxamountfield, 'Percent');
            }
        }

        $this->extend('updateCMSFields', $fields, $params);

        return $fields;
    }

    public function getDefaultSearchContext()
    {
        $context = parent::getDefaultSearchContext();
        $fields = $context->getFields();
        $fields->push(CheckboxField::create('HasBeenUsed'));
        //add date range filtering
        $fields->push(
            ToggleCompositeField::create(
                'StartDate',
                'Start Date',
                [
                DateField::create('q[StartDateFrom]', 'From'),
                DateField::create('q[StartDateTo]', 'To')
                ]
            )
        );
        $fields->push(
            ToggleCompositeField::create(
                'EndDate',
                'End Date',
                [
                DateField::create('q[EndDateFrom]', 'From'),
                DateField::create('q[EndDateTo]', 'To')
                ]
            )
        );
        //must be enabled in config, because some sites may have many products = slow load time, or memory maxes out
        //future solution is using an ajaxified field
        if (self::config()->filter_by_product) {
            $fields->push(
                ListboxField::create('Products', 'Products', Product::get()->map()->toArray())
            );
        }
        if (self::config()->filter_by_category) {
            $fields->push(
                ListboxField::create('Categories', 'Categories', ProductCategory::get()->map()->toArray())
            );
        }
        if ($field = $fields->fieldByName('Code')) {
            $field->setDescription('This can be a partial match.');
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
     * @param  Order $order
     * @param  array $context addional data to be checked in constraints.
     * @return boolean
     */
    public function validateOrder($order, $context = [])
    {
        if (empty($order)) {
            $this->error(_t('Discount.NOORDER', 'Order has not been started.'));

            return false;
        }

        // active discount.
        if (!$this->Active) {
            $this->error(
                sprintf(_t('Discount.INACTIVE', 'This %s is not active.'), $this->i18n_singular_name())
            );

            return false;
        }

        $constraints = $this->getConstraints();

        foreach ($constraints as $constraint) {
            $constraint = singleton($constraint)
                ->setOrder($order)
                ->setContext($context);

            if (!$constraint->check($this)) {
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
     * @param  string $fieldName Name of the field
     * @param  mixed  $value     New field value
     * @return DataObject $this
     */
    public function setCastedField($fieldName, $value)
    {
        if ($fieldName === 'Percent' && $value > 1) {
            $value /= 100.0;
        }

        return parent::setCastedField($fieldName, $value);
    }

    /**
     * Works out the discount on a given value.
     *
     * @param $value
     * @return calculated discount
     */
    public function getDiscountValue($value)
    {
        $discount = 0;
        if ($this->Type === 'Amount') {
            $discount += $this->getAmount();
        } elseif ($this->Percent) {
            $discount += $value * $this->Percent;
        }
        //prevent discounting more than the discountable amount
        if ($discount > $value) {
            $discount = $value;
        }

        $this->extend('updateDiscountValue', $discount);

        return $discount;
    }

    public function getDiscountNice()
    {
        if ($this->Type === 'Percent') {
            return $this->dbObject('Percent')->Nice();
        }

        return DBCurrency::create_field(DBCurrency::class, $this->getAmount())->Nice();
    }

    /**
     * Get discounting amount
     */
    public function getAmount()
    {
        $amount = $this->getField('Amount');

        $this->extend('updateAmount', $amount);

        return $amount;
    }

    /**
     * Get the number of times a discount has been used.
     *
     * @param int $orderID - ignore this order when counting uses
     *
     * @return int count
     */
    public function getUseCount($orderID = null)
    {
        $used = $this->getAppliedOrders(true);

        if ($orderID) {
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
    public function isUsed($orderID = null)
    {
        return $this->getUseCount($orderID) > 0;
    }

    public function setPercent($value)
    {
        $value = $value > 100 ? 100 : $value;

        $this->setField('Percent', $value);
    }

    /**
     * Map the single 'For' to the For"X" boolean fields
     *
     * @param string $val
     */
    public function setFor($val)
    {
        if (!$val) {
            return;
        }

        $map = [
            'Items' => [1,0,0],
            'Cart' => [0,1,0],
            'Shipping' => [0,0,1],
            'Order' => [0,1,1]
        ];

        $mapping = $map[$val];
        $this->ForItems = $mapping[0];
        $this->ForCart = $mapping[1];
        $this->ForShipping = $mapping[2];
    }

    /**
     * @return string
     */
    public function getFor()
    {
        if ($this->ForShipping && $this->ForCart) {
            return 'Order';
        }

        if ($this->ForShipping) {
            return 'Shipping';
        }

        if ($this->ForItems) {
            return 'Items';
        }

        if ($this->ForCart) {
            return 'Cart';
        }
    }

    /**
     * Get the orders that this discount has been used on.
     *
     * @param bool $includeunpaid include orders where the payment process has started
     * less than 'unpaid_use_timeout' minutes ago.
     *
     * @return \SilverStripe\ORM\DataList list of orders
     */
    public function getAppliedOrders($includeunpaid = false)
    {
        $orders =  Order::get()
            ->innerJoin('SilverShop_OrderAttribute', '"SilverShop_OrderAttribute"."OrderID" = "SilverShop_Order"."ID"')
            ->leftJoin('SilverShop_Product_OrderItem_Discounts',
                '"SilverShop_Product_OrderItem_Discounts"."SilverShop_Product_OrderItemID" = "SilverShop_OrderAttribute"."ID"')
            ->leftJoin('SilverShop_OrderDiscountModifier_Discounts',
                '"SilverShop_OrderDiscountModifier_Discounts"."SilverShop_OrderDiscountModifierID" = "SilverShop_OrderAttribute"."ID"')
            ->where(
                "SilverShop_Product_OrderItem_Discounts.SilverShop_DiscountID = $this->ID OR SilverShop_OrderDiscountModifier_Discounts.SilverShop_DiscountID = $this->ID
            "
            );

        if ($includeunpaid) {
            $minutes = self::config()->unpaid_use_timeout;
            $timeouttime = date('Y-m-d H:i:s', strtotime("-{$minutes} minutes"));
            $orders = $orders->leftJoin('Omnipay_Payment', '"Omnipay_Payment"."OrderID" = "SilverShop_Order"."ID"')
                ->where(
                    '("SilverShop_Order"."Paid" IS NOT NULL) OR ' .
                    "(\"Omnipay_Payment\".\"Created\" > '$timeouttime' AND \"Omnipay_Payment\".\"Status\" NOT IN('Refunded', 'Void'))"
                );
        } else {
            $orders = $orders->where('"SilverShop_Order"."Paid" IS NOT NULL');
        }

        $this->extend('updateAppliedOrders', $orders, $includeunpaid);

        return $orders;
    }

    /**
     * Get the total amount saved through the use of this discount,
     * accross all paid orders.
     *
     * @return float amount saved
     */
    public function getSavingsTotal()
    {
        $itemsavings = $this->OrderItems()
            ->innerJoin('SilverShop_Order',
                            '"SilverShop_OrderAttribute"."OrderID" = "SilverShop_Order"."ID"')
            ->where('"SilverShop_Order"."Paid" IS NOT NULL')
            ->sum('DiscountAmount');
        $modifiersavings = $this->DiscountModifiers()
            ->innerJoin('SilverShop_Order',
                            '"SilverShop_OrderAttribute"."OrderID" = "SilverShop_Order"."ID"')
            ->where('"SilverShop_Order"."Paid" IS NOT NULL')
            ->sum('DiscountAmount');

        return $itemsavings + $modifiersavings;
    }

    /**
     * Get the amount saved on the given order with this discount.
     *
     * @param  Order $order order to match against
     * @return double  savings amount
     */
    public function getSavingsForOrder(Order $order)
    {
        $itemsavings = OrderAttribute::get()
            ->innerJoin('SilverShop_Product_OrderItem_Discounts',
                '"SilverShop_OrderAttribute"."ID" = "SilverShop_Product_OrderItem_Discounts"."SilverShop_Product_OrderItemID"')
            ->filter('SilverShop_Product_OrderItem_Discounts.DiscountID', $this->ID)
            ->filter('OrderAttribute.OrderID', $order->ID)
            ->sum('DiscountAmount');

        $modifiersavings = OrderAttribute::get()
            ->innerJoin('SilverShop_OrderDiscountModifier_Discounts',
                '"SilverShop_OrderAttribute"."ID" = "SilverShop_OrderDiscountModifier_Discounts"."SilverShop_OrderDiscountModifierID"')
            ->filter('SilverShop_OrderDiscountModifier_Discounts.DiscountID', $this->ID)
            ->filter('OrderAttribute.OrderID', $order->ID)
            ->sum('DiscountAmount');

        return $itemsavings + $modifiersavings;
    }


    public function canView($member = null)
    {
        return true;
    }

    public function canCreate($member = null, $context = [])
    {
        return Permission::checkMember($member, 'MANAGE_DISCOUNTS');
    }

    public function canDelete($member = null)
    {
        return !$this->isUsed();
    }

    public function canEdit($member = null)
    {
        return Permission::checkMember($member, 'MANAGE_DISCOUNTS');
    }

    protected function message($message, $type = 'good')
    {
        $this->message = $message;
        $this->messagetype = $type;
    }

    protected function error($message)
    {
        $this->message($message, 'bad');
    }

    public function getMessage()
    {
        return $this->message;
    }

    public function getMessageType()
    {
        return $this->messagetype;
    }

    public function providePermissions()
    {
        return [
            'MANAGE_DISCOUNTS' => 'Manage discounts',
        ];
    }

    /**
     * @deprecated
     * @param $order
     * @param array $context
     * @return bool
     */
    public function valid($order, $context = [])
    {
        Deprecation::notice('1.2', 'use validateOrder instead');
        return $this->validateOrder($order, $context);
    }
}
