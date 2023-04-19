# Silverstripe Pagesections

**Elemental alternative for configurable page sections and elements.**

## Introduction

This module provides page sections for SilverStripe 4.x projects. Page sections are areas on a page where CMS users can add their own content in a structured way. Pages can have none, one or more page sections attached to them. Each page section is made up of various page elements, which themselves can or cannot have other page elements as children.

## Installation

```sh
composer require flxlabs/silverstripe-pagesections
```

Add the extension to the DataObject that should contain a PageSection:

```yml
Page:
  extensions:
    - PageSectionsExtension
  
```

By default the DataObject will have a PageSection called `Main`. To add additional sections, or change the name of the default section, specify them in the `page_sections` key.

```yml
Page:
  extensions:
    - PageSectionsExtension
  page_sections:
    - Main
    - Aside
```

Make sure to run `dev/build` and flush.

## Usage

Defining an element:

```php
<?php
class TextElement extends FLXLabs\PageSections\PageElement
{
  public static $singular_name = 'Text';
  public static $plural_name = 'Texts';

  private static $db = [
    'Content' => 'HTMLText',
  ];

  // Page elements can have other page elements as children.
  // Use this method to restrict allowed childre.
  public function getAllowedPageElements()
  {
    return [
      // YourElement::class
    ];
  }

  // This will be used to preview the content in the CMS editor
  public function getGridFieldPreview()
  {
      return $this->dbObject('Content')->Summary();
  }
}
```

To render an element, create a Template. To render a page section use the `RenderPageElements` method exposed by the PageSectionsExtension:

```html
<div>
  $RenderPageSection('SectionName')
</div>
```
