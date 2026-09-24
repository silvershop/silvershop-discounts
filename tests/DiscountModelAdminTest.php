<?php

namespace SilverShop\Discounts\Tests;

use SilverStripe\Dev\FunctionalTest;

class DiscountModelAdminTest extends FunctionalTest
{
    protected $usesDatabase = true;

    protected function setUp(): void
    {
        parent::setUp();
        $this->logInWithPermission('ADMIN');
    }

    /**
     * Loading the grid with a filter runs the custom search-filter joins in getList(). Before the fix
     * these referenced pre-SS6 unprefixed tables/columns, which don't exist under SS6's namespaced
     * schema, so the query threw a DatabaseException.
     *
     * @param array<string, mixed> $q
     */
    private function assertFilterLoads(array $q): void
    {
        $response = $this->get(
            'admin/discounts/SilverShop-Discounts-Model-OrderDiscount?' . http_build_query(['q' => $q])
        );

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testHasBeenUsedFilterProducesValidSql(): void
    {
        $this->assertFilterLoads(['HasBeenUsed' => 1]);
    }

    public function testProductsFilterProducesValidSql(): void
    {
        $this->assertFilterLoads(['Products' => [1]]);
        $this->assertFilterLoads(['Products' => 1]);
    }

    public function testCategoriesFilterProducesValidSql(): void
    {
        $this->assertFilterLoads(['Categories' => [1]]);
        $this->assertFilterLoads(['Categories' => 1]);
    }
}
