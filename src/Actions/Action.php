<?php

namespace SilverShop\Discounts\Actions;

abstract class Action
{
    abstract public function perform();

    abstract public function isForItems();
}
