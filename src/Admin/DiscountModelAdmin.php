<?php

namespace SilverShop\Discounts\Admin;

use SilverStripe\Admin\ModelAdmin;

use SilverStripe\Forms\NumericField;
use SilverStripe\Forms\FieldGroup;
use SilverStripe\Forms\TextField;
use SilverStripe\Forms\DropdownField;

use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\FormAction;
use SilverStripe\Forms\RequiredFields;
use SilverStripe\Forms\Form;
use SilverShop\Discounts\Model\OrderDiscount;
use SilverShop\Discounts\Model\OrderCoupon;
use SilverShop\Discounts\Model\PartialUseDiscount;
use SilverShop\Discounts\Form\GridField_LinkComponent;

class DiscountModelAdmin extends ModelAdmin
{
    private static $url_segment = 'discounts';

    private static $menu_title = 'Discounts';

    private static $menu_icon = 'silvershop/discounts:images/icon-coupons.png';

    private static $menu_priority = 2;

    private static $managed_models = [
        OrderDiscount::class,
        OrderCoupon::class,
        PartialUseDiscount::class
    ];

    private static $allowed_actions = [
        "generatecoupons",
        "GenerateCouponsForm"
    ];

    private static $model_descriptions = [
        "OrderDiscount" => "Discounts are applied at the checkout, based on defined constraints. If not constraints are given, then the discount will always be applied.",
        "OrderCoupon" => "Coupons are like discounts, but have an associated code.",
        "PartialUseDiscount" => "Partial use discounts are 'amount only' discounts that allow remainder amounts to be used."
    ];

    public function getEditForm($id = null, $fields = null)
    {
        $form = parent::getEditForm($id, $fields);

        if ($grid = $form->Fields()->fieldByName(OrderCoupon::class)) {
            $grid->getConfig()
                ->addComponent(
                    $link = new GridField_LinkComponent("Generate Multiple Coupons", $this->Link()."/generatecoupons"),
                    "GridFieldExportButton"
                );
            $link->addExtraClass("ss-ui-action-constructive");
        }

        $descriptions = self::config()->model_descriptions;

        if (isset($descriptions[$this->modelClass])) {
            $form->Fields()->fieldByName($this->modelClass)
                ->setDescription($descriptions[$this->modelClass]);
        }

        return $form;
    }

    /**
     * Update results list, to include custom search filters
     */
    public function getList()
    {
        $context = $this->getSearchContext();
        $params = $this->request->requestVar('q');
        $list = $context->getResults($params);

        if (isset($params['HasBeenUsed'])) {
            $list = $list
                ->leftJoin("SilverShop_OrderItem_Discounts", "\"SilverShop_OrderItem_Discounts\".\"DiscountID\" = \"Discount\".\"ID\"")
                ->leftJoin("SilverShop_OrderDiscountModifier_Discounts", "\"SilverShop_OrderDiscountModifier_Discounts\".\"DiscountID\" = \"Discount\".\"ID\"")
                ->innerJoin("OrderAttribute", implode(" OR ", [
                    "\"SilverShop_OrderAttribute\".\"ID\" = \"SilverShop_OrderItem_Discounts\".\"Product_OrderItemID\"",
                    "\"SilverShop_OrderAttribute\".\"ID\" = \"SilverShop_OrderDiscountModifier_Discounts\".\"SilverShop_OrderDiscountModifierID\""
                ]));
        }

        if (isset($params['Products'])) {
            $list = $list
                ->innerJoin("Discount_Products", "Discount_Products.DiscountID = Discount.ID")
                ->filter("Discount_Products.ProductID", $params['Products']);
        }

        if (isset($params['Categories'])) {
            $list = $list
                ->innerJoin("Discount_Categories", "Discount_Categories.DiscountID = Discount.ID")
                ->filter("Discount_Categories.ProductCategoryID", $params['Categories']);
        }

        $this->extend('updateList', $list);

        return $list;
    }

    public function GenerateCouponsForm()
    {
        $fields = Object::create(OrderCoupon::class)->getCMSFields();
        $fields->removeByName('Code');
        $fields->removeByName('GiftVoucherID');
        $fields->removeByName('SaveNote');

        $fields->addFieldsToTab("Root.Main", [
            NumericField::create('Number', 'Number of Coupons'),
            FieldGroup::create("Code",
                TextField::create("Prefix", "Code Prefix")
                    ->setMaxLength(5),
                DropdownField::create("Length", "Code Characters Length",
                    array_combine(range(5, 20), range(5, 20)),
                    OrderCoupon::config()->generated_code_length
                )->setDescription("This is in addition to the length of the prefix.")
            )
        ], "Title");

        $actions = new FieldList(
            new FormAction('generate', 'Generate')
        );
        $validator = new RequiredFields([
            'Title',
            'Number',
            'Type'
        ]);
        $form = new Form($this, "GenerateCouponsForm", $fields, $actions, $validator);
        $form->addExtraClass("cms-edit-form cms-panel-padded center ui-tabs-panel ui-widget-content ui-corner-bottom");
        $form->setAttribute('data-pjax-fragment', 'CurrentForm');
        $form->setHTMLID('Form_EditForm');
        $form->loadDataFrom([
            'Number' => 1,
            'Active' => 1,
            'ForCart' => 1,
            'UseLimit' => 1
        ]);
        return $form;
    }

    public function generate($data, $form)
    {
        $count = 1;

        if (isset($data['Number']) && is_numeric($data['Number'])) {
            $count = (int) $data['Number'];
        }

        $prefix = isset($data['Prefix']) ? $data['Prefix'] : "";
        $length = isset($data['Length']) ? (int) $data['Length'] : OrderCoupon::config()->generated_code_length;

        for ($i = 0; $i < $count; $i++) {
            $coupon = new OrderCoupon();
            $form->saveInto($coupon);

            $coupon->Code = OrderCoupon::generate_code(
                $length,
                $prefix
            );

            $coupon->write();
        }
        $this->redirect($this->Link());
    }

    public function generatecoupons()
    {
        return [
            'Title' => 'Generate Coupons',
            'EditForm' => $this->GenerateCouponsForm(),
            'SearchForm' => '',
            'ImportForm' => ''
        ];
    }
}
