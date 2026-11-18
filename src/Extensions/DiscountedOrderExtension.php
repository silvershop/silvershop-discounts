<?php

namespace SilverShop\Discounts\Extensions;

use SilverStripe\Core\Extension;
use SilverShop\Model\Order;
use SilverStripe\Core\Config\Config;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldConfig_RecordViewer;
use SilverStripe\Model\List\ArrayList;
use SilverShop\Discounts\Model\Discount;
use SilverShop\Discounts\Model\DiscountCode;
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
    /** @return ArrayList<Discount> */
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
            if (!$discount instanceof PartialUseDiscount) {
                continue;
            }

            if ($savings = $discount->getSavingsForOrder($this->owner)) {
                $discount->createRemainder((float) $savings);
                //deactivate discounts
                $discount->Active = false;
                $discount->write();
            }
        }
    }

    /**
     * When the order is paid, keep the stored per-discount / per-code redemption counters
     * ({@see Discount::$TimesUsed}) in step. Only the discounts and the specific codes used on this
     * order are reconciled; enforcement uses the derived counts, so this is for display/reporting.
     */
    public function onPaid(): void
    {
        $discountIDs = [];
        $codeIDs = [];

        $lists = [];

        foreach ($this->owner->Items() as $item) {
            $lists[] = $item->Discounts();
        }

        foreach ($this->owner->Modifiers() as $modifier) {
            if ($modifier instanceof OrderDiscountModifier) {
                $lists[] = $modifier->Discounts();
            }
        }

        foreach ($lists as $list) {
            foreach ($list as $discount) {
                $discountIDs[(int) $discount->ID] = true;
                $codeID = (int) $discount->DiscountCodeID;

                if ($codeID !== 0) {
                    $codeIDs[$codeID] = true;
                }
            }
        }

        foreach (array_keys($discountIDs) as $id) {
            Discount::get()->byID($id)?->reconcileTimesUsed();
        }

        foreach (array_keys($codeIDs) as $id) {
            DiscountCode::get()->byID($id)?->reconcileTimesUsed();
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
