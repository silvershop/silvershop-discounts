<?php

namespace SilverShop\Discounts\Extensions\Constraints;


use SilverShop\Discounts\Model\Discount;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\NumericField;


/**
 * @package silvershop-discounts
 */
class UseLimitDiscountConstraint extends DiscountConstraint
{
    private static $db = [
        "UseLimit" => "Int"
    ];

    private static $field_labels = [
        "UseLimit" => "Maximum number of uses"
    ];

    public function updateCMSFields(FieldList $fields)
    {
        $fields->addFieldToTab("Root.Constraints",
            NumericField::create("UseLimit", "Limit number of uses", 0)
                ->setDescription("Note: 0 = unlimited")
        );
    }

    public function check(Discount $discount)
    {
        if ($discount->UseLimit) {
            if ($discount->getUseCount($this->order->ID) >= $discount->UseLimit) {
                $this->error(_t('DiscountConstraint.USELIMITREACHED', "This discount has reached its maximum number of uses."));

                return false;
            }
        }

        return true;
    }
}
