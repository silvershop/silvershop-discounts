<?php

namespace SilverShop\Discounts\Form;

use SilverStripe\Forms\GridField\GridField_HTMLProvider;

class GridField_LinkComponent implements GridField_HTMLProvider
{
    protected string $title = '';
    protected string $url = '';
    protected string $extraclasses = '';

    public function __construct(string $title, string $url)
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

    public function addExtraClass(string $classes): void
    {
        $this->extraclasses = $classes;
    }
}
