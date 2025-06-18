<?php

namespace SilverShop\Discounts\Tests;

use SilverStripe\Dev\SapphireTest;
use SilverShop\Discounts\Model\PartialUseDiscount;

class PartialUseDiscountTest extends SapphireTest
{

    protected static $fixture_file = [
        'shop.yml',
        'PartialUseDiscount.yml'
    ];

    public function testCreateRemainder(): void
    {
        //basic remainder
        $discount = $this->objFromFixture(PartialUseDiscount::class, 'partial');
        $this->assertNull($discount->createRemainder(5000));
        $this->assertNull($discount->createRemainder(90));
        $remainderdiscount = $discount->createRemainder(40);
        $this->assertEquals(50, $remainderdiscount->Amount, 'Subtract $40 from $90 discount');

        $discount->Active = false;
        $discount->write();
        $this->assertNull($discount->createRemainder(30), 'Cannot recreate remainder');

        //TODO: check basic relationships match, e.g. group

        //check constraints copying works
        $discount = $this->objFromFixture(PartialUseDiscount::class, 'constrained');
        $remainder = $discount->createRemainder(40);
        $this->assertListEquals(
            [
            ['FirstName' => 'Joe']
            ],
            $remainder->Members()
        );
        $this->assertListEquals(
            [
            ['Title' => 'ProductA'],
            ['Title' => 'ProductB']
            ],
            $remainder->Products()
        );
    }

    public function testCheckoutProcessing(): void
    {
        $this->markTestIncomplete('This should be tested');
    }
}
