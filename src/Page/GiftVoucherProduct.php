<?php

namespace SilverShop\Discounts\Page;

use SilverStripe\Forms\FieldList;
use SilverShop\Discounts\Model\GiftVoucherOrderItem;
use SilverShop\Page\Product;
use SilverStripe\Forms\OptionsetField;
use SilverStripe\Forms\TextField;

/**
 * Gift voucher products, when purchased will send out a voucher code to the
 * customer via email.
 * @property bool $VariableAmount
 * @property float $MinimumAmount
 */
class GiftVoucherProduct extends Product
{
    private static array $db = [
        'VariableAmount' => 'Boolean',
        'MinimumAmount' => 'Currency'
    ];

    private static string $singular_name = 'Gift Voucher';

    private static string $plural_name = 'Gift Vouchers';

    private static string $order_item = GiftVoucherOrderItem::class;

    private static string $table_name = 'SilverShop_GiftVoucherProduct';

    public function getCMSFields(): FieldList
    {
        $fieldList = parent::getCMSFields();
        $fieldList->addFieldToTab(
            'Root.Pricing',
            OptionsetField::create('VariableAmount', 'Price', [
                0 => 'Fixed',
                1 => 'Allow customer to choose'
            ]),
            'BasePrice'
        );

        $fieldList->addFieldsToTab(
            'Root.Pricing',
            [
                //text field, because of CMS js validation issue
                TextField::create('MinimumAmount', 'Minimum Amount')
            ]
        );

        $fieldList->removeByName('CostPrice');
        $fieldList->removeByName('Variations');
        $fieldList->removeByName('Model');
        return $fieldList;
    }

    public function canPurchase($member = null, $quantity = 1): bool
    {
        if (!self::config()->get('global_allow_purchase')) {
            return false;
        }

        if (!$this->dbObject('AllowPurchase')->getValue()) {
            return false;
        }

        return (bool) $this->isPublished();
    }
}
