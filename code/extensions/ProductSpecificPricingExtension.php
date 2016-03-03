<?php

/**
 * @package silvershop-discounts
 */
class SpecificPricingExtension extends DataExtension {

    private static $has_many = array(
        "SpecificPrices" => "SpecificPrice"
    );

    function updateCMSFields(FieldList $fields) {
        if($tab = $fields->fieldByName("Root.Pricing")) {
            $fields = $tab->Fields();
        }
        if($this->owner->isInDB() && ($fields->fieldByName("BasePrice") || $fields->fieldByName("Price"))){
            $fields->push(
                GridField::create("SpecificPrices","Specific Prices", $this->owner->SpecificPrices(),
                    GridFieldConfig_RecordEditor::create()
                )
            );
        }
    }

    function updateSellingPrice(&$price){
        if($specificprice = SpecificPrice::filter(
            $this->owner->SpecificPrices()
                ->filter("Price:LessThan", $price), Member::currentUser()
        )->first()){
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
