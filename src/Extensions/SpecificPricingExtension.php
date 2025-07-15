<?php

namespace SilverShop\Discounts\Extensions;

use SilverStripe\Core\Extension;
use SilverStripe\ORM\HasManyList;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldConfig_RecordEditor;
use SilverShop\Discounts\Model\SpecificPrice;
use SilverStripe\Security\Security;

/**
 * @method HasManyList<SpecificPrice> SpecificPrices()
 * @extends Extension<static>
 */
class SpecificPricingExtension extends Extension
{
    private static array $has_many = [
        'SpecificPrices' => SpecificPrice::class
    ];

    public function updateCMSFields(FieldList $fieldList): void
    {
        if ($tab = $fieldList->fieldByName('Root.Pricing')) {
            $fieldList = $tab->Fields();
        }

        if ($this->owner->isInDB() && ($fieldList->fieldByName('BasePrice') || $fieldList->fieldByName('Price'))) {
            $fieldList->push(
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
        $hasManyList = $this->owner->SpecificPrices()->filter(['Price:LessThan' => $price ]);

        if ($hasManyList->exists() && $specificprice = SpecificPrice::filter(
            $hasManyList,
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
