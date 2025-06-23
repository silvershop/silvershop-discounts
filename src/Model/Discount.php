<?php

namespace SilverShop\Discounts\Model;

use SilverStripe\ORM\ManyManyList;
use SilverShop\Discounts\Extensions\Constraints\CategoriesDiscountConstraint;
use SilverShop\Discounts\Extensions\Constraints\ProductsDiscountConstraint;
use SilverShop\Discounts\Extensions\Constraints\GroupDiscountConstraint;
use SilverShop\Discounts\Extensions\Constraints\MembershipDiscountConstraint;
use SilverShop\Discounts\Extensions\Constraints\DatetimeDiscountConstraint;
use SilverShop\Discounts\Extensions\Constraints\ValueDiscountConstraint;
use SilverShop\Discounts\Extensions\Constraints\UseLimitDiscountConstraint;
use SilverShop\Discounts\Extensions\Constraints\CodeDiscountConstraint;
use SilverStripe\ORM\DataList;
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
use SilverStripe\ORM\Search\SearchContext;

/**
 * @property string $Title
 * @property ?string $Type
 * @property mixed $Amount
 * @property float $Percent
 * @property bool $Active
 * @property bool $ForItems
 * @property bool $ForCart
 * @property bool $ForShipping
 * @property float $MaxAmount
 * @method ManyManyList<OrderItem> OrderItems()
 * @method ManyManyList<OrderDiscountModifier> DiscountModifiers()
 * @mixin CategoriesDiscountConstraint
 * @mixin ProductsDiscountConstraint
 * @mixin GroupDiscountConstraint
 * @mixin MembershipDiscountConstraint
 * @mixin DatetimeDiscountConstraint
 * @mixin ValueDiscountConstraint
 * @mixin UseLimitDiscountConstraint
 * @mixin CodeDiscountConstraint
 */
class Discount extends DataObject implements PermissionProvider
{
    private static array $db = [
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

    private static array $belongs_many_many = [
        'OrderItems' => OrderItem::class,
        'DiscountModifiers' => OrderDiscountModifier::class
    ];

    private static array $defaults = [
        'Type' => 'Percent',
        'Active' => true,
        'ForItems' => 1
    ];

    private static array $field_labels = [
        'DiscountNice' => 'Discount'
    ];

    private static array $summary_fields = [
        'Title',
        'DiscountNice' => 'Discount',
        'StartDate',
        'EndDate'
    ];

    private static array $searchable_fields = [
        'Title'
    ];

    private static string $singular_name = 'Discount';

    private static string $plural_name = 'Discounts';

    private static string $default_sort = 'EndDate DESC, StartDate DESC';

    private static string $table_name = 'SilverShop_Discount';

    protected string $message = '';

    protected string $messagetype = '';

    /**
     * Number of minutes ago to include for carts with paymetn start
     * in the {@link getAppliedOrders()} function
     */
    private static int $unpaid_use_timeout = 10;

    public function getConstraints(): array
    {
        $extensions = $this->getExtensionInstances();
        $output = [];

        foreach ($extensions as $extension) {
            if ($extension instanceof DiscountConstraint) {
                $output[] = $extension::class;
            }
        }

        return $output;
    }

    /**
     * Get the smallest possible list of discounts that can apply
     * to a given order.
     */
    public static function get_matching(Order $order, $context = []): ArrayList
    {
        $discounts = self::get()
            ->filter('Active', true)
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
        $arrayList = ArrayList::create();

        foreach ($discounts as $discount) {
            if ($discount->validateOrder($order, $context)) {
                $arrayList->push($discount);
            }
        }

        return $arrayList;
    }

    public function getCMSFields($params = null)
    {
        //fields that shouldn't be changed once coupon is used
        $fieldList = FieldList::create([
            TabSet::create('Root', Tab::create('Main', TextField::create('Title'), CheckboxField::create('Active', 'Active')
                ->setDescription('Enable/disable all use of this discount.'), HeaderField::create('ActionTitle', 'Action', 3), $typefield = SelectionGroup::create(
                'Type',
                [
                    SelectionGroup_Item::create('Percent', $percentgroup = FieldGroup::create(
                        $percentfield = NumericField::create('Percent', 'Percentage', '0.00')
                            ->setScale(null)
                            ->setDescription('e.g. 0.05 = 5%, 0.5 = 50%, and 5 = 500%'),
                        $maxamountfield = CurrencyField::create(
                            'MaxAmount',
                            _t('MaxAmount', 'Maximum Amount')
                        )->setDescription(
                            'The total allowable discount. 0 means unlimited.'
                        )
                    ), 'Discount by percentage'),
                    SelectionGroup_Item::create('Amount', $amountfield = CurrencyField::create('Amount', 'Amount', '$0.00'), 'Discount by fixed amount')
                ]
            )->setTitle('Type'), OptionSetField::create(
                'For',
                'Applies to',
                [
                    'Order' => 'Entire order',
                    'Cart' => 'Cart subtotal',
                    'Shipping' => 'Shipping subtotal',
                    'Items' => 'Each individual item'
                ]
            )), Tab::create('Constraints', TabSet::create('ConstraintsTabs', $general = Tab::create('General', 'General'))))
        ]);

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

        if (($count = $this->getUseCount()) !== 0) {
            $useHeader = _t('Discount.USEHEADER', 'Use Count: {count}', ['count' => $count]);

            $fieldList->addFieldsToTab(
                'Root.Usage',
                [
                    HeaderField::create('UseCount', $useHeader),
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
            $fieldList->insertAfter('Type', $valuefield);
            $fieldList->makeFieldReadonly('Type');
        } elseif ($this->Type && (float)$this->{$this->Type}) {
            $valuefield = $this->Type === 'Percent' ? $percentfield : $amountfield;

            $fieldList->makeFieldReadonly('Type');
            $fieldList->insertAfter('ActionTitle', $valuefield);

            $fieldList->replaceField(
                $this->Type,
                $valuefield->performReadonlyTransformation()
            );

            if ($this->Type === 'Percent') {
                $fieldList->insertAfter('Percent', $maxamountfield);
            }
        }

        $this->extend('updateCMSFields', $fieldList, $params);

        return $fieldList;
    }

    public function getDefaultSearchContext(): SearchContext
    {
        $searchContext = parent::getDefaultSearchContext();

        $fieldList = $searchContext->getFields();
        $fieldList->push(CheckboxField::create('HasBeenUsed'));

        $fieldList->push(
            ToggleCompositeField::create(
                'StartDate',
                'Start Date',
                [
                    DateField::create('q[StartDateFrom]', 'From'),
                    DateField::create('q[StartDateTo]', 'To')
                ]
            )
        );
        $fieldList->push(
            ToggleCompositeField::create(
                'EndDate',
                'End Date',
                [
                    DateField::create('q[EndDateFrom]', 'From'),
                    DateField::create('q[EndDateTo]', 'To')
                ]
            )
        );

        // must be enabled in config, because some sites may have many products = slow load time, or memory maxes out
        // future solution is using an ajaxified field
        if (self::config()->filter_by_product) {
            $fieldList->push(
                ListboxField::create('Products', 'Products', Product::get()->map()->toArray())
            );
        }

        if (self::config()->filter_by_category) {
            $fieldList->push(
                ListboxField::create('Categories', 'Categories', ProductCategory::get()->map()->toArray())
            );
        }

        if ($field = $fieldList->fieldByName('Code')) {
            $field->setDescription('This can be a partial match.');
        }

        $filters = $searchContext->getFilters();
        $filters['StartDateFrom'] = GreaterThanOrEqualFilter::create('StartDate');
        $filters['StartDateTo'] = LessThanOrEqualFilter::create('StartDate');
        $filters['EndDateFrom'] = GreaterThanOrEqualFilter::create('EndDate');
        $filters['EndDateTo'] = LessThanOrEqualFilter::create('EndDate');
        $searchContext->setFilters($filters);

        return $searchContext;
    }

    /**
     * Check if this coupon can be used with a given order
     * $context provides addional data to be checked in constraints.
     */
    public function validateOrder(Order $order, array $context = []): bool
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
     */
    public function getDiscountValue($value)
    {
        $discount = 0;

        if ($this->Type === 'Amount') {
            $discount += $this->getAmount();
        } elseif ($this->Percent) {
            $discount += $value * $this->Percent;
        }

        // prevent discounting more than the discountable amount
        if ($discount > $value) {
            $discount = $value;
        }

        $this->extend('updateDiscountValue', $discount);

        return $discount;
    }

    public function getDiscountNice(): float|string
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
     */
    public function getUseCount(?int $orderID = null): int
    {
        $used = $this->getAppliedOrders(true);

        if ($orderID !== null && $orderID !== 0) {
            $used = $used->exclude('ID', $orderID);
        }

        return $used->count();
    }

    /**
     * Returns whether this coupon is used.
     */
    public function isUsed(?int $orderID = null): bool
    {
        return $this->getUseCount($orderID) > 0;
    }

    public function setPercent($value): void
    {
        $value = $value > 100 ? 100 : $value;

        $this->setField('Percent', $value);
    }

    /**
     * Map the single 'For' to the For"X" boolean fields
     *
     * @param string $val
     */
    public function setFor($val): void
    {
        if (!$val) {
            return;
        }

        $map = [
            'Items' => [1, 0, 0],
            'Cart' => [0, 1, 0],
            'Shipping' => [0, 0, 1],
            'Order' => [0, 1, 1]
        ];

        $mapping = $map[$val];
        $this->ForItems = $mapping[0];
        $this->ForCart = $mapping[1];
        $this->ForShipping = $mapping[2];
    }

    /**
     * @return string
     */
    public function getFor(): ?string
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

        return null;
    }

    /**
     * Get the orders that this discount has been used on.
     * $includeunpaid include orders where the payment process has
     * started less than 'unpaid_use_timeout' minutes ago.
     */
    public function getAppliedOrders(bool $includeunpaid = false):DataList
    {
        $orders =  Order::get()
            ->innerJoin('SilverShop_OrderAttribute', '"SilverShop_OrderAttribute"."OrderID" = "SilverShop_Order"."ID"')
            ->leftJoin(
                'SilverShop_OrderItem_Discounts',
                '"SilverShop_OrderItem_Discounts"."SilverShop_OrderItemID" = "SilverShop_OrderAttribute"."ID"'
            )
            ->leftJoin(
                'SilverShop_OrderDiscountModifier_Discounts',
                '"SilverShop_OrderDiscountModifier_Discounts"."SilverShop_OrderDiscountModifierID" = "SilverShop_OrderAttribute"."ID"'
            )
            ->where(
                "SilverShop_OrderItem_Discounts.SilverShop_DiscountID = $this->ID OR SilverShop_OrderDiscountModifier_Discounts.SilverShop_DiscountID = $this->ID
            "
            );

        if ($includeunpaid) {
            $minutes = self::config()->unpaid_use_timeout;
            $timeouttime = date('Y-m-d H:i:s', strtotime(sprintf('-%s minutes', $minutes)));
            $orders = $orders->leftJoin('Omnipay_Payment', '"Omnipay_Payment"."OrderID" = "SilverShop_Order"."ID"')
                ->where(
                    '("SilverShop_Order"."Paid" IS NOT NULL) OR ' .
                        sprintf("(\"Omnipay_Payment\".\"Created\" > '%s' AND \"Omnipay_Payment\".\"Status\" NOT IN('Refunded', 'Void'))", $timeouttime)
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
     */
    public function getSavingsTotal(): float|int|array
    {
        $itemsavings = $this->OrderItems()
            ->innerJoin(
                'SilverShop_Order',
                '"SilverShop_OrderAttribute"."OrderID" = "SilverShop_Order"."ID"'
            )
            ->where('"SilverShop_Order"."Paid" IS NOT NULL')
            ->sum('DiscountAmount');
        $modifiersavings = $this->DiscountModifiers()
            ->innerJoin(
                'SilverShop_Order',
                '"SilverShop_OrderAttribute"."OrderID" = "SilverShop_Order"."ID"'
            )
            ->where('"SilverShop_Order"."Paid" IS NOT NULL')
            ->sum('DiscountAmount');

        return $itemsavings + $modifiersavings;
    }

    /**
     * Get the amount saved on the given order with this discount.
     */
    public function getSavingsForOrder(Order $order): float|int|array
    {
        $itemsavings = OrderAttribute::get()
            ->innerJoin(
                'SilverShop_OrderItem_Discounts',
                '"SilverShop_OrderAttribute"."ID" = "SilverShop_OrderItem_Discounts"."SilverShop_OrderItemID"'
            )
            ->filter('SilverShop_OrderItem_Discounts.DiscountID', $this->ID)
            ->filter('OrderAttribute.OrderID', $order->ID)
            ->sum('DiscountAmount');

        $modifiersavings = OrderAttribute::get()
            ->innerJoin(
                'SilverShop_OrderDiscountModifier_Discounts',
                '"SilverShop_OrderAttribute"."ID" = "SilverShop_OrderDiscountModifier_Discounts"."SilverShop_OrderDiscountModifierID"'
            )
            ->filter('SilverShop_OrderDiscountModifier_Discounts.DiscountID', $this->ID)
            ->filter('OrderAttribute.OrderID', $order->ID)
            ->sum('DiscountAmount');

        return $itemsavings + $modifiersavings;
    }


    public function canView($member = null): bool
    {
        return true;
    }

    public function canCreate($member = null, $context = []): bool
    {
        return Permission::checkMember($member, 'MANAGE_DISCOUNTS');
    }

    public function canDelete($member = null): bool
    {
        return !$this->isUsed();
    }

    public function canEdit($member = null): bool
    {
        return Permission::checkMember($member, 'MANAGE_DISCOUNTS');
    }

    protected function message(string $message, string $type = 'good'): void
    {
        $this->message = $message;
        $this->messagetype = $type;
    }

    protected function error(string $message): void
    {
        $this->message($message, 'bad');
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getMessageType(): string
    {
        return $this->messagetype;
    }

    public function providePermissions(): array
    {
        return [
            'MANAGE_DISCOUNTS' => 'Manage discounts',
        ];
    }

    /**
     * @deprecated
     * @param      $order
     * @param      array $context
     */
    public function valid(Order $order, $context = []): bool
    {
        Deprecation::notice('1.2', 'use validateOrder instead');
        return $this->validateOrder($order, $context);
    }
}
