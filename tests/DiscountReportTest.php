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
        $this->assertEqualsWithDelta(44, (int) $sqlQueryList->find('Title', 'Limited Discount')->getSavingsTotal(), PHP_FLOAT_EPSILON);
        $this->assertSame(22, (int) $sqlQueryList->find('Title', 'Limited Coupon')->getSavingsTotal());
    }
}
