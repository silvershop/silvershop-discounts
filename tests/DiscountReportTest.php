<?php

namespace SilverShop\Discounts\Tests;

use SilverStripe\Dev\SapphireTest;

use SilverShop\Discounts\Model\OrderDiscount;
use SilverShop\Discounts\Admin\DiscountReport;

class DiscountReportTest extends SapphireTest
{

    protected static $fixture_file = 'Discounts.yml';

    public function testDiscountReport(): void
    {
        $this->objFromFixture(OrderDiscount::class, 'used');
        $discountReport = DiscountReport::create();
        $sqlQueryList = $discountReport->sourceRecords([]);
        $this->assertEquals(44, $sqlQueryList->find('Title', 'Limited Discount')->getSavingsTotal());
        $this->assertEquals(22, $sqlQueryList->find('Title', 'Limited Coupon')->getSavingsTotal());
    }
}
