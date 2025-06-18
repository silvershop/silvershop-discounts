<?php

namespace SilverShop\Discounts\Extensions;

use SilverStripe\ORM\DataExtension;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldConfig_RecordEditor;
use SilverShop\Discounts\Model\SpecificPrice;
use SilverStripe\Security\Security;

class SpecificPricingExtension extends DataExtension
{
    private static array $has_many = [
        'SpecificPrices' => SpecificPrice::class
    ];

    public function updateCMSFields(FieldList $fields): void
    {
        if ($tab = $fields->fieldByName('Root.Pricing')) {
            $fields = $tab->Fields();
        }

        if ($this->owner->isInDB() && ($fields->fieldByName('BasePrice') || $fields->fieldByName('Price'))) {
            $fields->push(
                GridField::create(
                    'SpecificPrices',
                    'Specific Prices',
                    $this->owner->SpecificPrices(),
                    GridFieldConfig_RecordEditor::create()
                )
            );
        }
    }

    public function updateSellingPrice(&$price): void
    {
        $list = $this->owner->SpecificPrices()->filter( array('Price:LessThan' => $price ));

        if ($list->exists() && $specificprice = SpecificPrice::filter(
            $list,
                Security::getCurrentUser()
        )->first()
        ) {
            if ($specificprice->Price > 0) {
                $price = $specificprice->Price;
            } elseif ($specificprice->DiscountPercent > 0) {
                $price *= 1.0 - $specificprice->DiscountPercent;
            } else {
                // this would mean both discount and price were 0
                $price = 0;
            }
        }
    }
}
