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
        $this->assertEquals(45, $product->sellingPrice());
        $this->assertTrue($product->IsReduced());
        $this->assertEquals(5, $product->getTotalReduction());
    }

    public function testProductVariationPrice(): void
    {
        $variation = $this->objFromFixture(Variation::class, 'robot_30gb');
        $this->assertEquals(90, $variation->sellingPrice());
        $this->assertTrue($variation->IsReduced());
        $this->assertEquals(10, $variation->getTotalReduction());
    }

    public function testProductPricePercentage(): void
    {
        $discount = $this->objFromFixture(SpecificPrice::class, 'raspberrypi_dateconstrained');
        $discount->DiscountPercent = 0.5;
        $discount->Price = 0;
        $discount->write();
        $product = $this->objFromFixture(Product::class, 'raspberrypi');
        $this->assertEquals(25, $product->sellingPrice());
        $this->assertTrue($product->IsReduced());
        $this->assertEquals(25, $product->getTotalReduction());
    }

    public function testProductVariationPricePercentage(): void
    {
        $discount = $this->objFromFixture(SpecificPrice::class, 'robot_30gb_specific');
        $discount->DiscountPercent = 0.5;
        $discount->Price = 0;
        $discount->write();
        $variation = $this->objFromFixture(Variation::class, 'robot_30gb');
        $this->assertEquals(50, $variation->sellingPrice());
        $this->assertTrue($variation->IsReduced());
        $this->assertEquals(50, $variation->getTotalReduction());
    }
}
