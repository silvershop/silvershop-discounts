<?php

namespace SilverShop\Discounts\Form;

use SilverStripe\Core\Convert;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Forms\GridField\GridField_HTMLProvider;

class GridField_LinkComponent implements GridField_HTMLProvider
{
    use Injectable;

    protected string $title = '';

    protected string $url = '';

    protected string $extraclasses = '';

    public function __construct(string $title, string $url)
    {
        $this->title = $title;
        $this->url = $url;
    }

    /** @return array<string, string> */
    public function getHTMLFragments($gridField): array
    {
        return [
            'buttons-before-left' => sprintf(
                '<a href="%s" class="btn %s">%s</a>',
                Convert::raw2att($this->url),
                Convert::raw2att($this->extraclasses),
                Convert::raw2xml($this->title)
            )
        ];
    }

    public function addExtraClass(string $classes): void
    {
        $this->extraclasses = $classes;
    }
}
