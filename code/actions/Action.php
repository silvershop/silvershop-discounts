<?php

/**
 * @package silvershop-discounts
 */
abstract class Action
{
	abstract function perform();

	abstract function isForItems();

}
