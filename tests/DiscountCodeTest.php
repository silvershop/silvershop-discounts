<?php

namespace SilverShop\Discounts\Tests;

use SilverShop\Discounts\Calculator;
use SilverShop\Discounts\Form\GridFieldGenerateCodesButton;
use SilverShop\Discounts\Model\DiscountCode;
use SilverShop\Discounts\Model\OrderCoupon;
use SilverShop\Discounts\Model\OrderDiscount;
use SilverShop\Model\Order;
use SilverShop\Page\Product;
use SilverShop\Tests\ShopTestBootstrap;
use SilverStripe\Core\Config\Config;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\GridField\GridField;

/**
 * Multi-code coupons (issue #31): a coupon can own many {@link DiscountCode} children; entering any
 * of them resolves to, and applies, the one discount. Codes are unique across coupons and codes.
 */
class DiscountCodeTest extends SapphireTest
{
    protected static $fixture_file = [
        'shop.yml'
    ];

    protected Order $unpaid;

    protected Order $cart;

    protected OrderCoupon $coupon;

    protected function setUp(): void
    {
        parent::setUp();
        ShopTestBootstrap::setConfiguration();

        Config::modify()->set(OrderCoupon::class, 'minimum_code_length', null);

        foreach (['socks', 'tshirt', 'mp3player'] as $handle) {
            $this->objFromFixture(Product::class, $handle)->publishRecursive();
        }

        $this->unpaid = $this->objFromFixture(Order::class, 'unpaid');
        $this->cart = $this->objFromFixture(Order::class, 'cart');

        // one coupon, a legacy "primary" code plus two child codes
        $this->coupon = OrderCoupon::create([
            'Title' => 'Campaign',
            'Code' => 'PRIMARY10',
            'Type' => 'Percent',
            'Percent' => 0.5,
            'Active' => 1
        ]);
        $this->coupon->write();

        foreach (['CHILDA', 'CHILDB'] as $code) {
            $child = DiscountCode::create(['Code' => $code, 'Active' => 1]);
            $child->DiscountID = $this->coupon->ID;
            $child->write();
        }
    }

    public function testGetByCodeResolvesLegacyAndChildCodes(): void
    {
        $this->assertSame($this->coupon->ID, OrderCoupon::get_by_code('PRIMARY10')?->ID, 'legacy code resolves');
        $this->assertSame($this->coupon->ID, OrderCoupon::get_by_code('CHILDA')?->ID, 'child code resolves to owner');
        $this->assertSame($this->coupon->ID, OrderCoupon::get_by_code('childa')?->ID, 'child match is case-insensitive');
        $this->assertNull(OrderCoupon::get_by_code('NOPE'), 'unknown code resolves to nothing');
    }

    public function testChildCodeMatchesAndApplies(): void
    {
        $this->assertTrue(
            $this->coupon->validateOrder($this->cart, ['CouponCodes' => ['CHILDA']]),
            (string) $this->coupon->getMessage()
        );
        $this->assertFalse(
            $this->coupon->validateOrder($this->cart, ['CouponCodes' => ['WRONG']]),
            'a code that is neither the legacy code nor a child must not match'
        );

        // the calculator discounts the order when a child code is applied
        $viachild = Calculator::create($this->unpaid, ['CouponCodes' => ['CHILDA']])->calculate();
        $vialegacy = Calculator::create($this->unpaid, ['CouponCodes' => ['PRIMARY10']])->calculate();
        $this->assertGreaterThan(0, $viachild, 'child code produces a discount');
        $this->assertSame((int) $vialegacy, (int) $viachild, 'child and legacy code apply the same discount');
    }

    public function testInactiveChildCodeDoesNotMatch(): void
    {
        $inactive = DiscountCode::create(['Code' => 'SLEEPING', 'Active' => 0]);
        $inactive->DiscountID = $this->coupon->ID;
        $inactive->write();

        $this->assertFalse(
            $this->coupon->validateOrder($this->cart, ['CouponCodes' => ['SLEEPING']]),
            'an inactive child code must not match'
        );
    }

    public function testCodesAreUniqueAcrossCouponsAndCodes(): void
    {
        // clashes with the owning coupon's legacy code
        $clashLegacy = DiscountCode::create(['Code' => 'PRIMARY10']);
        $clashLegacy->DiscountID = $this->coupon->ID;
        $this->assertSame('CODETAKEN', key($clashLegacy->validate()->getMessages()));

        // clashes with an existing child code
        $clashChild = DiscountCode::create(['Code' => 'CHILDA']);
        $clashChild->DiscountID = $this->coupon->ID;
        $this->assertSame('CODETAKEN', key($clashChild->validate()->getMessages()));

        // a fresh code validates cleanly
        $fresh = DiscountCode::create(['Code' => 'FRESHCODE']);
        $fresh->DiscountID = $this->coupon->ID;
        $this->assertTrue($fresh->validate()->isValid());

        // and a new coupon can't reuse a child code either
        $this->assertTrue(DiscountCode::code_exists('CHILDB'));
        $this->assertFalse(DiscountCode::code_exists('NEVERSEEN'));
    }

    public function testGeneratedCodeIsUniqueAcrossBothTables(): void
    {
        $code = DiscountCode::generate_code(10);
        $this->assertMatchesRegularExpression('/^[0-9A-Z]{10}$/', $code);
        $this->assertFalse(DiscountCode::code_exists($code), 'a freshly generated code is not already taken');
    }

    public function testGenerateCodesCreatesUniqueChildren(): void
    {
        $created = $this->coupon->generateCodes(5, 0, 0, 8, 'XM');
        $this->assertSame(5, $created);

        $generated = $this->coupon->Codes()->filter('Code:StartsWith', 'XM');
        $this->assertSame(5, $generated->count());

        foreach ($generated as $code) {
            $this->assertMatchesRegularExpression('/^XM[0-9A-Z]{8}$/', (string) $code->Code);
        }

        $all = $this->coupon->Codes()->column('Code');
        $this->assertSame(count($all), count(array_unique($all)), 'generated codes are unique');
    }

    public function testGenerateCodesAppliesLimits(): void
    {
        $this->coupon->generateCodes(3, 1, 2, 8, 'LT');

        $generated = $this->coupon->Codes()->filter('Code:StartsWith', 'LT');
        $this->assertSame(3, $generated->count());

        foreach ($generated as $code) {
            $this->assertSame(1, (int) $code->UseLimit, 'per-code use limit applied');
            $this->assertSame(2, (int) $code->PerCustomerUseLimit, 'per-customer limit applied');
        }
    }

    public function testGenerateCodesFormHandleActionUsesSubmittedValues(): void
    {
        $grid = GridField::create('Codes', 'Codes', $this->coupon->Codes());
        $form = Form::create(null, 'TestForm', FieldList::create($grid), FieldList::create());
        $form->loadDataFrom($this->coupon);

        $before = $this->coupon->Codes()->count();
        GridFieldGenerateCodesButton::create()->handleAction($grid, 'generatecodes', [], [
            'GenerateCodesCount' => 3,
            'GenerateCodesUseLimit' => 1,
            'GenerateCodesPerCustomer' => 2,
            'GenerateCodesPrefix' => 'BT',
            'GenerateCodesLength' => 8
        ]);

        $generated = $this->coupon->Codes()->filter('Code:StartsWith', 'BT');
        $this->assertSame($before + 3, $this->coupon->Codes()->count(), 'the form generates the requested count');
        $this->assertSame(3, $generated->count());

        $code = $generated->first();
        $this->assertSame(1, (int) $code->UseLimit, 'submitted use limit applied');
        $this->assertSame(2, (int) $code->PerCustomerUseLimit, 'submitted per-customer limit applied');

        // count of 0 is a no-op
        $none = $this->coupon->Codes()->count();
        GridFieldGenerateCodesButton::create()->handleAction($grid, 'generatecodes', [], ['GenerateCodesCount' => 0]);
        $this->assertSame($none, $this->coupon->Codes()->count(), 'count 0 generates nothing');
    }

    public function testCodesGridOnlyOnCoupons(): void
    {
        // a coupon surfaces the Codes grid
        $this->assertNotNull(
            $this->coupon->getCMSFields()->dataFieldByName('Codes'),
            'a coupon shows the Codes grid'
        );

        // a plain (automatic) discount does not — its codes could never be entered at checkout
        $discount = OrderDiscount::create(['Title' => 'Auto', 'Type' => 'Percent', 'Percent' => 0.1]);
        $discount->write();
        $this->assertNull(
            $discount->getCMSFields()->dataFieldByName('Codes'),
            'a plain discount has no Codes grid'
        );
    }

    public function testCascadeDeleteRemovesChildCodes(): void
    {
        $id = $this->coupon->ID;
        $this->assertSame(2, DiscountCode::get()->filter('DiscountID', $id)->count());

        $this->coupon->delete();

        $this->assertSame(0, DiscountCode::get()->filter('DiscountID', $id)->count(), 'child codes are cascade-deleted');
    }
}
