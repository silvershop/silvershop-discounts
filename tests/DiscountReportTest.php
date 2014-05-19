<?php

class DiscountReportTest extends SapphireTest{

	protected static $fixture_file = 'shop_discount/tests/fixtures/Discounts.yml';
	
	function testDiscountReport() {
		$discount = $this->objFromFixture("OrderDiscount", "used");
		$report = new DiscountReport();
		$report->sourceRecords(array());

		$this->markTestIncomplete("Add assertions.");
	}

}