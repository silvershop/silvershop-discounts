<?php

namespace SilverShop\Discounts\Page;

use SilverShop\Page\Product;
use SilverStripe\Forms\OptionsetField;
use SilverStripe\Forms\TextField;
use SilverStripe\Control\Email\Email;

/**
 * Gift voucher products, when purchased will send out a voucher code to the
 * customer via email.
 */
class GiftVoucherProduct extends Product
{
    private static $db = [
        'VariableAmount' => 'Boolean',
        'MinimumAmount' => 'Currency'
    ];

    private static $singular_name = 'Gift Voucher';

    private static $plural_name = 'Gift Vouchers';

    private static $order_item = GiftVoucherProduct::class;

    private static $table_name = 'SilverShop_GiftVoucherProduct';

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        $fields->addFieldToTab(
            'Root.Pricing',
            new OptionsetField(
                'VariableAmount',
                'Price',
                [
                0 => 'Fixed',
                1 => 'Allow customer to choose'
                ]
            ),
            'BasePrice'
        );

        $fields->addFieldsToTab(
            'Root.Pricing',
            [
            //text field, because of CMS js validation issue
            $minimumamount = new TextField('MinimumAmount', 'Minimum Amount')
            ]
        );

        $fields->removeByName('CostPrice');
        $fields->removeByName('Variations');
        $fields->removeByName('Model');
        return $fields;
    }

    public function canPurchase($member = null, $quantity = 1)
    {
        if (!self::config()->get('global_allow_purchase')) {
            return false;
        }

        if (!$this->dbObject('AllowPurchase')->getValue()) {
            return false;
        }

        if (!$this->isPublished()) {
            return false;
        }

        return true;
    }
}
