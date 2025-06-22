<?php

namespace SilverShop\Discounts;

/**
 * Represent a price, along with adjustments made to it.
 */
class PriceInfo
{
    protected int|float $originalprice;

    protected int|float $currentprice; //for compounding discounts

    protected array $adjustments = [];

    protected ?Adjustment $bestadjustment = null;

    public function __construct(int|float $price)
    {
        $this->currentprice = $this->originalprice = $price;
    }

    public function getOriginalPrice(): int|float
    {
        return $this->originalprice;
    }

    public function getPrice(): int|float
    {
        return $this->currentprice;
    }

    public function adjustPrice(Adjustment $a): void
    {
        $this->currentprice -= $a->getValue();
        $this->setBestAdjustment($a);
        $this->adjustments[] = $a;
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

    public function getAdjustments(): array
    {
        return $this->adjustments;
    }

    /**
     * Sets the best adjustment, if the passed adjustment
     * is indeed better.
     *
     * @param Adjustment $candidate for better adjustment
     */
    protected function setBestAdjustment(Adjustment $candidate): void
    {
        $this->bestadjustment = $this->bestadjustment ?
            Adjustment::better_of($this->bestadjustment, $candidate) : $candidate;
    }
}
