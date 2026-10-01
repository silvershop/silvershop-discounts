<?php

namespace SilverShop\Discounts;

/**
 * Represent a price, along with adjustments made to it.
 */
class PriceInfo
{
    protected int|float $originalprice;

    protected int|float $currentprice; //for compounding discounts

    /** @var array<int, Adjustment> */
    protected array $adjustments = [];

    protected ?Adjustment $bestadjustment = null;

    /**
     * Adjustments from discounts that allow stacking, each calculated on the
     * price remaining after the ones before it.
     *
     * @var array<int, Adjustment>
     */
    protected array $stackedadjustments = [];

    public function __construct(int|float $price)
    {
        $this->currentprice = $price;
        $this->originalprice = $price;
    }

    public function getOriginalPrice(): int|float
    {
        return $this->originalprice;
    }

    public function getPrice(): int|float
    {
        return $this->currentprice;
    }

    public function adjustPrice(Adjustment $adjustment): void
    {
        $this->currentprice -= $adjustment->getValue();
        $this->setBestAdjustment($adjustment);
        $this->adjustments[] = $adjustment;
    }

    public function getCompoundedDiscount(): int|float
    {
        return $this->originalprice - $this->currentprice;
    }

    public function getBestDiscount(): int|float
    {
        if ($this->bestadjustment instanceof Adjustment) {
            return $this->bestadjustment->getValue();
        }

        return 0;
    }

    public function getBestAdjustment(): ?Adjustment
    {
        return $this->bestadjustment;
    }

    /** @return array<int, Adjustment> */
    public function getAdjustments(): array
    {
        return $this->adjustments;
    }

    public function addStackedAdjustment(Adjustment $adjustment): void
    {
        $this->stackedadjustments[] = $adjustment;
    }

    /** @return array<int, Adjustment> */
    public function getStackedAdjustments(): array
    {
        return $this->stackedadjustments;
    }

    public function getStackedDiscount(): int|float
    {
        return array_sum(array_map(
            fn (Adjustment $adjustment): int|float => $adjustment->getValue(),
            $this->stackedadjustments
        ));
    }

    /**
     * The price remaining after stacked adjustments, which the next stacked
     * discount is calculated on.
     */
    public function getStackedPrice(): int|float
    {
        return max(0, $this->originalprice - $this->getStackedDiscount());
    }

    /**
     * Sets the best adjustment, if the passed adjustment is better. When two
     * adjustments are equal the first one wins, so discounts that are
     * processed first (higher priority) are preferred.
     *
     * @param Adjustment $adjustment for better adjustment
     */
    protected function setBestAdjustment(Adjustment $adjustment): void
    {
        if (!$this->bestadjustment instanceof Adjustment || $adjustment->compareTo($this->bestadjustment) > 0) {
            $this->bestadjustment = $adjustment;
        }
    }
}
