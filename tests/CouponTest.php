<?php
/**
 * Tests coupons
 * @package shop-discount
 */
class CouponTest extends FunctionalTest{
	
	static $fixture_file = 'shop_discount/tests/discounttest.yml';
	
	function testValidCoupon(){
		$validcoupon = $this->objFromFixture('OrderCoupon', 'validcoupon');
		$this->assertTrue($validcoupon->valid());
		$this->assertEquals($validcoupon->getDiscountValue(10),7);
	}
	
	function testExpiredCoupon(){
		$expiredcoupon = $this->objFromFixture('OrderCoupon', 'expiredcoupon');
		$this->assertFalse($expiredcoupon->valid());
		#TODO: check error
	}
	
	function testUnreleasedCoupon(){
		$expiredcoupon = $this->objFromFixture('OrderCoupon', 'unreleasedcoupon');
		$this->assertFalse($expiredcoupon->valid());
		#TODO: check error
	}
	
	function testInactiveCoupon(){
		$inactivecoupon = $this->objFromFixture('OrderCoupon', 'inactivecoupon');
		$this->assertFalse($inactivecoupon->valid());
		#TODO: check error
	}
	
	
	//TODO: has discount been applied to a completed order
	
}