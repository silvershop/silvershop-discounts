<?php

namespace SilverShop\Discounts\Extensions;

use SilverStripe\ORM\DataExtension;
use SilverStripe\Core\Config\Config;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldConfig_RecordViewer;
use SilverStripe\ORM\ArrayList;
use SilverShop\Discounts\Model\Discount;
use SilverShop\Discounts\Model\PartialUseDiscount;
use SilverShop\Discounts\Model\Modifiers\OrderDiscountModifier;

class DiscountedOrderExtension extends DataExtension
{
    public function updateCMSFields(FieldList $fields): void
    {
        $fields->addFieldsToTab(
            'Root.Discounts',
            $grid = new GridField(
                'Discounts',
                Config::inst()->get(Discount::class, 'plural_name'),
                $this->Discounts(),
                new GridFieldConfig_RecordViewer()
            )
        );

        $grid->setModelClass(Discount::class);
    }

    /**
     * Get all discounts that have been applied to an order.
     */
    public function Discounts(): ArrayList
    {
        $finalDiscounts = new ArrayList();

        foreach ($this->owner->Modifiers() as $modifier) {
            if ($modifier instanceof OrderDiscountModifier) {
                foreach ($modifier->Discounts() as $discount) {
                    $finalDiscounts->push($discount);
                }
            }
        }

        foreach ($this->owner->Items() as $item) {
            foreach ($item->Discounts() as $discount) {
                $finalDiscounts->push($discount);
            }
        }

        $finalDiscounts->removeDuplicates();

        return $finalDiscounts;
    }

    /**
     * Remove any partial discounts
     */
    public function onPlaceOrder(): void
    {
        $partials = $this->owner->Discounts()->filter('ClassName', PartialUseDiscount::class);

        foreach ($partials as $discount) {
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
        foreach ($this->owner->Items() as $item) {
            $item->Discounts()->removeAll();
        }
        foreach ($this->owner->Modifiers() as $modifier) {
            if ($modifier instanceof OrderDiscountModifier) {
                $modifier->Discounts()->removeAll();
            }
        }
    }
}
