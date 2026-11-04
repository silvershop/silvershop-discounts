<?php

namespace SilverShop\Discounts\Tasks;

use SilverShop\Discounts\Model\Discount;
use SilverShop\Discounts\Model\DiscountCode;
use SilverStripe\Dev\BuildTask;
use SilverStripe\PolyExecution\PolyOutput;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;

/**
 * Recompute the stored {@see Discount::$TimesUsed} counters on every Discount and DiscountCode from
 * paid orders. Run once after deploying multi-code coupons (issue #31) to backfill existing data;
 * safe to re-run, and useful to heal any drift.
 */
class ReconcileDiscountUsageTask extends BuildTask
{
    protected static string $commandName = 'discounts-reconcile-usage';

    protected string $title = 'Discounts: reconcile stored usage counters';

    protected static string $description =
        'Recompute Discount/DiscountCode TimesUsed from paid orders (backfill / heal drift).';

    protected function execute(InputInterface $input, PolyOutput $output): int
    {
        $discounts = 0;

        foreach (Discount::get() as $discount) {
            $discount->reconcileTimesUsed();
            $discounts++;
        }

        $codes = 0;

        foreach (DiscountCode::get() as $code) {
            $code->reconcileTimesUsed();
            $codes++;
        }

        $output->writeln(sprintf('<info>Reconciled %d discount(s) and %d code(s).</info>', $discounts, $codes));

        return Command::SUCCESS;
    }
}
