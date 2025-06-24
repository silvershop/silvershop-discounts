<?php

namespace SilverShop\Discounts\Tests;

use SilverStripe\Dev\SapphireTest;
use SilverShop\Discounts\Extensions\SpecificPricingExtension;
use SilverShop\Page\Product;
use SilverShop\Model\Variation\Variation;
use SilverShop\Discounts\Model\SpecificPrice;

class SpecificPriceTest extends SapphireTest
{
    protected static $fixture_file = [
        'SpecificPrices.yml'
    ];

    protected function setUp(): void
    {
        parent::setUp();

        Product::add_extension(SpecificPricingExtension::class);
        Variation::add_extension(SpecificPricingExtension::class);
    }

    public function testProductPrice(): void
    {
        $product = $this->objFromFixture(Product::class, 'raspberrypi');
        $this->assertSame(45, (int) $product->sellingPrice());
        $this->assertTrue($product->IsReduced());
        $this->assertSame(5, (int) $product->getTotalReduction());
    }

    public function testProductVariationPrice(): void
    {
        $variation = $this->objFromFixture(Variation::class, 'robot_30gb');
        $this->assertSame(90, (int) $variation->sellingPrice());
        $this->assertTrue($variation->IsReduced());
        $this->assertSame(10, (int) $variation->getTotalReduction());
    }

    public function testProductPricePercentage(): void
    {
        $specificPrice = $this->objFromFixture(SpecificPrice::class, 'raspberrypi_dateconstrained');
        $specificPrice->DiscountPercent = 0.5;
        $specificPrice->Price = 0;
        $specificPrice->write();
        $product = $this->objFromFixture(Product::class, 'raspberrypi');
        $this->assertSame(25, (int) $product->sellingPrice());
        $this->assertTrue($product->IsReduced());
        $this->assertSame(25, (int) $product->getTotalReduction());
    }

    public function testProductVariationPricePercentage(): void
    {
        $specificPrice = $this->objFromFixture(SpecificPrice::class, 'robot_30gb_specific');
        $specificPrice->DiscountPercent = 0.5;
        $specificPrice->Price = 0;
        $specificPrice->write();
        $variation = $this->objFromFixture(Variation::class, 'robot_30gb');
        $this->assertSame(50, (int) $variation->sellingPrice());
        $this->assertTrue($variation->IsReduced());
        $this->assertSame(50, (int) $variation->getTotalReduction());
    }
}
