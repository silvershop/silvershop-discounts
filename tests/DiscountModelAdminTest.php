<?php

namespace SilverShop\Discounts\Tests;

use SilverStripe\Dev\SapphireTest;
use SilverStripe\Control\HTTPRequest;
use SilverShop\Discounts\Admin\DiscountModelAdmin;
use SilverShop\Discounts\Model\OrderDiscount;

class DiscountModelAdminTest extends SapphireTest
{
    private function countForFilter(array $q): int
    {
        $admin = DiscountModelAdmin::create();
        $admin->setRequest(new HTTPRequest('GET', 'admin/discounts', ['q' => $q]));
        $admin->modelClass = OrderDiscount::class;

        // Executing the query runs the custom search-filter joins. Before the fix these referenced
        // pre-SS6 unprefixed tables/columns, which don't exist under SS6's namespaced schema, so
        // the query threw a DatabaseException. After the fix each filter runs cleanly.
        return $admin->getList()->count();
    }

    public function testHasBeenUsedFilterProducesValidSql(): void
    {
        $this->assertIsInt($this->countForFilter(['HasBeenUsed' => 1]));
    }

    public function testProductsFilterProducesValidSql(): void
    {
        $this->assertIsInt($this->countForFilter(['Products' => [1]]));
    }

    public function testCategoriesFilterProducesValidSql(): void
    {
        $this->assertIsInt($this->countForFilter(['Categories' => [1]]));
    }
}
