<?php

class DiscountReportTest extends SapphireTest{

    protected static $fixture_file = 'silvershop-discounts/tests/fixtures/Discounts.yml';

    function testDiscountReport() {
        $discount = $this->objFromFixture("OrderDiscount", "used");
        $report = new DiscountReport();
        $records = $report->sourceRecords(array());
        $this->assertEquals(44, $records->find("Title", "Limited Discount")->getSavingsTotal());
        $this->assertEquals(22, $records->find("Title", "Limited Coupon")->getSavingsTotal());
    }

}