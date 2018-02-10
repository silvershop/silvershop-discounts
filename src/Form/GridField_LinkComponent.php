<?php

namespace SilverShop\Discounts\Form;

use SilverStripe\Forms\GridField\GridField_HTMLProvider;


class GridField_LinkComponent implements GridField_HTMLProvider
{
    protected $title;
    protected $url;
    protected $extraclasses;

    public function __construct($title, $url)
    {
        $this->title = $title;
        $this->url = $url;
    }

    public function getHTMLFragments($gridField)
    {
        return [
            'before' => "<a href=\"$this->url\" class=\"ss-ui-button $this->extraclasses\">$this->title</a>"
        ];
    }

    public function addExtraClass($classes)
    {
        $this->extraclasses = $classes;
    }
}
