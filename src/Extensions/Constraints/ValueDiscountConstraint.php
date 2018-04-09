<?php

namespace SilverShop\Discounts\Extensions\Constraints;


use SilverShop\Discounts\Model\Discount;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\CurrencyField;
use SilverStripe\ORM\DataList;


class ValueDiscountConstraint extends DiscountConstraint
{
    private static $db = [
        "MinOrderValue" => "Currency"
    ];

    private static $field_labels = [
        "MinOrderValue" => "Minimum subtotal of order"
    ];

    public function updateCMSFields(FieldList $fields)
    {
        $fields->addFieldToTab("Root.Constraints.ConstraintsTabs.General",
            CurrencyField::create(
                "MinOrderValue",
                _t(__CLASS__.'.MINORDERVALUE', $this->owner->fieldLabel('MinOrderValue'))
            )
        );
    }

    public function filter(DataList $list)
    {
        return $list->filterAny([
            "MinOrderValue" => 0,
            "MinOrderValue:LessThanOrEqual" => $this->order->SubTotal()
        ]);
    }

    public function check(Discount $discount)
    {
        if ($discount->MinOrderValue > 0 && $this->order->SubTotal() < $discount->MinOrderValue) {
            $this->error(
                sprintf(
                    _t("Discount.MINORDERVALUE",
                        "Your cart subtotal must be at least %s to use this discount"),
                    $discount->dbObject("MinOrderValue")->Nice()
                )
            );
            return false;
        }

        return true;
    }
}
