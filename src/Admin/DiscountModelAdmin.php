<?php

namespace SilverShop\Discounts\Admin;

use SilverStripe\Admin\ModelAdmin;
use SilverStripe\Control\Controller;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Forms\NumericField;
use SilverStripe\Forms\FieldGroup;
use SilverStripe\Forms\TextField;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\FormAction;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldExportButton;
use SilverStripe\Forms\Validation\RequiredFieldsValidator;
use SilverStripe\Forms\Form;
use SilverShop\Discounts\Model\OrderDiscount;
use SilverShop\Discounts\Model\OrderCoupon;
use SilverShop\Discounts\Model\PartialUseDiscount;
use SilverShop\Discounts\Form\GridField_LinkComponent;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\DataObject;

class DiscountModelAdmin extends ModelAdmin
{
    private static string $url_segment = 'discounts';

    private static string $menu_title = 'Discounts';

    private static string $menu_icon_class = 'font-icon-tags';

    private static int $menu_priority = 2;

    private static array $managed_models = [
        OrderDiscount::class,
        OrderCoupon::class,
        PartialUseDiscount::class
    ];

    private static array $allowed_actions = [
        'generatecoupons',
        'GenerateCouponsForm'
    ];

    private static array $model_descriptions = [
        'OrderDiscount' => 'Discounts are applied at the checkout, based on defined constraints. If not constraints are given, then the discount will always be applied.',
        'OrderCoupon' => 'Coupons are like discounts, but have an associated code.',
        'PartialUseDiscount' => "Partial use discounts are 'amount only' discounts that allow remainder amounts to be used."
    ];

    public function getEditForm($id = null, $fields = null): Form
    {
        $form = parent::getEditForm($id, $fields);

        // ModelAdmin names the grid after the sanitised class name (e.g. SilverShop-Discounts-Model-OrderCoupon)
        $grid = $form->Fields()->fieldByName($this->sanitiseClassName(OrderCoupon::class));
        if ($grid instanceof GridField) {
            $gridFieldLinkComponent = GridField_LinkComponent::create(
                _t(__CLASS__ . '.GenerateMultipleCoupons', 'Generate Multiple Coupons'),
                Controller::join_links($this->Link(), 'generatecoupons')
            );
            $gridFieldLinkComponent->addExtraClass('btn-primary font-icon-plus-circled');
            $grid->getConfig()->addComponent($gridFieldLinkComponent, GridFieldExportButton::class);
        }

        // descriptions may be keyed by either the fully qualified or short class name
        $descriptions = self::config()->get('model_descriptions');
        $description = $descriptions[$this->modelClass] ?? $descriptions[ClassInfo::shortName($this->modelClass)] ?? null;

        if ($description) {
            $modelField = $form->Fields()->fieldByName($this->sanitiseClassName($this->modelClass));
            if ($modelField) {
                $modelField->setDescription($description);
            }
        }

        return $form;
    }

    /**
     * Update results list, to include custom search filters
     */
    /** @return DataList<DataObject> */
    public function getList(): DataList
    {
        $params = $this->request->requestVar('q');
        $list = parent::getList();

        if (isset($params['HasBeenUsed'])) {
            $list = $list
                ->leftJoin("SilverShop_OrderItem_Discounts", '"SilverShop_OrderItem_Discounts"."SilverShop_DiscountID" = "SilverShop_Discount"."ID"')
                ->leftJoin("SilverShop_OrderDiscountModifier_Discounts", '"SilverShop_OrderDiscountModifier_Discounts"."SilverShop_DiscountID" = "SilverShop_Discount"."ID"')
                ->innerJoin(
                    "SilverShop_OrderAttribute",
                    implode(
                        " OR ",
                        [
                            '"SilverShop_OrderAttribute"."ID" = "SilverShop_OrderItem_Discounts"."SilverShop_OrderItemID"',
                            '"SilverShop_OrderAttribute"."ID" = "SilverShop_OrderDiscountModifier_Discounts"."SilverShop_OrderDiscountModifierID"'
                        ]
                    )
                );
        }

        if (isset($params['Products'])) {
            $products = array_filter((array) $params['Products'], static fn($value): bool => $value !== '' && $value !== null);
            $list = $list->innerJoin(
                "SilverShop_Discount_Products",
                '"SilverShop_Discount_Products"."SilverShop_DiscountID" = "SilverShop_Discount"."ID"'
            );
            if ($products !== []) {
                $list = $list->where([
                    '"SilverShop_Discount_Products"."SilverShop_ProductID" IN (' . implode(',', array_fill(0, count($products), '?')) . ')' => array_values($products),
                ]);
            }
        }

        if (isset($params['Categories'])) {
            $categories = array_filter((array) $params['Categories'], static fn($value): bool => $value !== '' && $value !== null);
            $list = $list->innerJoin(
                "SilverShop_Discount_Categories",
                '"SilverShop_Discount_Categories"."SilverShop_DiscountID" = "SilverShop_Discount"."ID"'
            );
            if ($categories !== []) {
                $list = $list->where([
                    '"SilverShop_Discount_Categories"."SilverShop_ProductCategoryID" IN (' . implode(',', array_fill(0, count($categories), '?')) . ')' => array_values($categories),
                ]);
            }
        }

        return $list;
    }

    public function GenerateCouponsForm(): Form
    {
        $fieldList = OrderCoupon::create()->getCMSFields();
        $fieldList->removeByName('Code');
        $fieldList->removeByName('GiftVoucherID');
        $fieldList->removeByName('SaveNote');

        $fieldList->addFieldsToTab(
            'Root.Main',
            [
            NumericField::create('Number', 'Number of Coupons'),
            FieldGroup::create(
                'Code',
                TextField::create('Prefix', 'Code Prefix')
                    ->setMaxLength(5),
                DropdownField::create(
                    'Length',
                    'Code Characters Length',
                    array_combine(range(5, 20), range(5, 20)),
                    (int) OrderCoupon::config()->get('generated_code_length')
                )->setDescription('This is in addition to the length of the prefix.')
            )
            ],
            'Title'
        );

        $actions = FieldList::create(FormAction::create('generate', 'Generate'));
        $requiredFields = RequiredFieldsValidator::create(
            [
                'Title',
                'Number',
                'Type'
            ]
        );
        $form = Form::create($this, 'GenerateCouponsForm', $fieldList, $actions, $requiredFields);
        $form->addExtraClass('cms-edit-form cms-panel-padded center ui-tabs-panel ui-widget-content ui-corner-bottom');
        $form->setAttribute('data-pjax-fragment', 'CurrentForm');
        $form->setHTMLID('Form_EditForm');
        $form->loadDataFrom(
            [
                'Number' => 1,
                'Active' => 1,
                'For' => 'Cart',
                'UseLimit' => 1
            ]
        );
        return $form;
    }

    /** @param array<string, mixed> $data */
    public function generate(array $data, Form $form): void
    {
        $count = 1;

        if (isset($data['Number']) && is_numeric($data['Number'])) {
            $count = (int) $data['Number'];
        }

        $prefix = $data['Prefix'] ?? '';
        $length = isset($data['Length']) ? (int) $data['Length'] : (int) OrderCoupon::config()->get('generated_code_length');

        for ($i = 0; $i < $count; $i++) {
            $coupon = OrderCoupon::create();
            $form->saveInto($coupon);

            $coupon->Code = OrderCoupon::generate_code(
                $length,
                $prefix
            );

            $coupon->write();
        }

        $this->redirect($this->Link() ?? '');
    }

    /** @return array<string, string|Form> */
    public function generatecoupons(): array
    {
        return [
            'Title' => 'Generate Coupons',
            'EditForm' => $this->GenerateCouponsForm(),
            'SearchForm' => '',
            'ImportForm' => ''
        ];
    }
}
