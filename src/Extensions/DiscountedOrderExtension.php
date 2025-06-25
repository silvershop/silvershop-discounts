<?php

namespace SilverShop\Discounts\Extensions;

use SilverStripe\Core\Extension;
use SilverShop\Model\Order;
use SilverStripe\Core\Config\Config;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldConfig_RecordViewer;
use SilverStripe\ORM\ArrayList;
use SilverShop\Discounts\Model\Discount;
use SilverShop\Discounts\Model\PartialUseDiscount;
use SilverShop\Discounts\Model\Modifiers\OrderDiscountModifier;

/**
 * @extends Extension<Order&static>
 */
class DiscountedOrderExtension extends Extension
{
    public function updateCMSFields(FieldList $fieldList): void
    {
        $fieldList->addFieldToTab(
            'Root.Discounts',
            $grid = GridField::create('Discounts', Config::inst()->get(Discount::class, 'plural_name'), $this->Discounts(), GridFieldConfig_RecordViewer::create())
        );

        $grid->setModelClass(Discount::class);
    }

    /**
     * Get all discounts that have been applied to an order.
     */
    public function Discounts(): ArrayList
    {
        $arrayList = ArrayList::create();

        foreach ($this->owner->Modifiers() as $hasManyList) {
            if ($hasManyList instanceof OrderDiscountModifier) {
                foreach ($hasManyList->Discounts() as $discount) {
                    $arrayList->push($discount);
                }
            }
        }

        foreach ($this->owner->Items() as $item) {
            foreach ($item->Discounts() as $discount) {
                $arrayList->push($discount);
            }
        }

        $arrayList->removeDuplicates();

        return $arrayList;
    }

    /**
     * Remove any partial discounts
     */
    public function onPlaceOrder(): void
    {
        $arrayList = $this->owner->Discounts()->filter('ClassName', PartialUseDiscount::class);

        foreach ($arrayList as $discount) {
            //only bother creating a remainder discount, if savings have been made
            if ($savings = $discount->getSavingsForOrder($this->owner)) {
                $discount->createRemainder($savings);
                //deactivate discounts
                $discount->Active = false;
                $discount->write();
            }
        }
    }

    /**
     * Remove discounts
     */
    public function removeDiscounts(): void
    {
        foreach ($this->owner->Items() as $hasManyList) {
            $hasManyList->Discounts()->removeAll();
        }

        foreach ($this->owner->Modifiers() as $modifier) {
            if ($modifier instanceof OrderDiscountModifier) {
                $modifier->Discounts()->removeAll();
            }
        }
    }
}
