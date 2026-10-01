<?php

namespace SilverShop\Discounts\Actions;

use SilverShop\Discounts\Adjustment;
use SilverShop\Discounts\ItemPriceInfo;
use SilverShop\Discounts\Model\Discount;
use SilverShop\Discounts\Extensions\Constraints\ItemDiscountConstraint;

abstract class ItemDiscountAction extends DiscountAction
{
    /**
     * @var array<int, ItemPriceInfo>
     */
    protected array $infoitems;

    /**
     * When stacking, the discount is calculated on the price remaining after
     * earlier stacked discounts, and recorded as a stacked adjustment.
     */
    protected bool $stacking = false;

    /**
     * @param array<int, ItemPriceInfo> $infoitems
     */
    public function __construct(array $infoitems, Discount $discount)
    {
        parent::__construct($discount);

        $this->infoitems = $infoitems;
    }

    public function isForItems(): bool
    {
        return true;
    }

    public function setStacking(bool $stacking): static
    {
        $this->stacking = $stacking;

        return $this;
    }

    public function perform(): void
    {
        foreach ($this->infoitems as $infoitem) {
            if (!$this->itemQualifies($infoitem)) {
                continue;
            }

            $price = $this->stacking ? $infoitem->getStackedPrice() : $infoitem->getOriginalPrice();
            $amount = $this->discount->getDiscountValue($price);
            $amount *= $infoitem->getQuantity();
            $amount = $this->limit($amount);
            $adjustment = new Adjustment($amount, $this->discount);

            if ($this->stacking) {
                if ($amount > 0) {
                    $infoitem->addStackedAdjustment($adjustment);
                }
            } else {
                $infoitem->adjustPrice($adjustment);
            }

            //break the loop if there is no discountable amount left
            if (!$this->hasRemainingDiscount()) {
                break;
            }
        }
    }

    /**
     * Checks if the given item qualifies for a discount.
     */
    protected function itemQualifies(ItemPriceInfo $itemPriceInfo): bool
    {
        return ItemDiscountConstraint::match($itemPriceInfo->getItem(), $this->discount);
    }
}
