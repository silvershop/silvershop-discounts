<?php

namespace SilverShop\Discounts\Extensions\Constraints;

use SilverShop\Discounts\Model\Discount;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\NumericField;

class UseLimitDiscountConstraint extends DiscountConstraint
{
    private static $db = [
        'UseLimit' => 'Int'
    ];

    private static $field_labels = [
        'UseLimit' => 'Maximum number of uses'
    ];

    public function updateCMSFields(FieldList $fields)
    {
        $fields->addFieldToTab(
            'Root.Constraints.ConstraintsTabs.General',
            NumericField::create(
                'UseLimit',
                _t(__CLASS__.'.USELIMIT', $this->owner->fieldLabel('UseLimit')),
                0
            )
            ->setDescription('Note: 0 = unlimited')
        );
    }

    public function check(Discount $discount)
    {
        if ($discount->UseLimit) {
            if ($discount->getUseCount($this->order->ID) >= $discount->UseLimit) {
                $this->error(_t('DiscountConstraint.USELIMITREACHED',
                    'This discount has reached its maximum number of uses.'));

                return false;
            }
        }

        return true;
    }
}
