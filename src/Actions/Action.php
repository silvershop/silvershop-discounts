<?php

namespace SilverShop\Discounts\Actions;

abstract class Action
{
    /** @return mixed */
    abstract public function perform();

    abstract public function isForItems(): bool;
}
