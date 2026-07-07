<?php

namespace SilverShop\Discounts\Tests;

use SilverStripe\Core\Extension;
use SilverStripe\Dev\TestOnly;

/**
 * Raises the amount used for tax-inclusive minimum-order checks (see ValueDiscountConstraint).
 */
class ValueDiscountConstraintTestOrderExtension extends Extension implements TestOnly
{
    public function updateMinimumOrderValueComparisonAmount(\stdClass $context): void
    {
        $context->amount += 100;
    }
}
