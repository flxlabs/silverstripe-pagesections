# Page Sections module

**Adds configurable page sections and elements to your SilverStripe project.**


## Introduction

This module provides page sections for SilverStripe 3.+ projects.  
Page sections are areas on a page where CMS users can add their own content
in a structured way.  
Pages can have none, one or more page sections attached to them. Each page section
is made up of various page elements, which themselves can or cannot have other 
page elements as children. The page elements can be edited in a tree-like gridview 
on the page or the page elements.


## Setup

You can either add this module to your composer file using
```
composer require flxlabs/silverstripe-pagesections
```
or download the git repository and add a folder called `pagesections` to the top level 
of your project and drop the code in there.

> By default the module is configured to do nothing, and also doesn't add any relations.
Please refer to the **Configure** section for more information.


## Configure

> By default this module does **NOT** add any relations or data to any of your pages.
Follow the steps below to start working with page sections.

### Pages & Page sections

Your pages can have none (default), one or more page sections, which are areas on
your page where CMS users can add various page elements to customize the look and feel
of the page in a structered/limited way.

1. Add the following extensions to your `mysite/_config/config.yaml` on any pages 
where you wish to have page sections:
   ```
   Page:
     extensions:
       - PageSectionsExtension
       - VersionedRelationsExtension
   ```
   This will by default add **one** page section called `Main` to your page(s)
   > **Make sure that the *VersionedRelationsExtension* comes after the *PageSectionsExtension***

1. If you want more than one page section add the following to your `mysite/_config/config.yaml`:
   ```
   Page:
     extensions:
       - PageSectionsExtension
       - VersionedRelationsExtension
     page_sections:
  	   - Top
  	   - Middle
  	   - Bottom
   ```
   Each of the listed names under the `page_sections` key will add a page section of that name to 
   the page. This will also override the default `Main` page section.

1. Hit up `/dev/build?flush=1` in your browser to rebuild the database. You should see that new
relations are created for each of your page classes that you added page sections to.


### Page elements

Page elements are the actual elements that are added to the page sections and then displayed
on the page. By default this package only contains the base class `PageElement` and not any
actually implemented page elements, but you can take a look at the `examples` folder to see
how some common page elements would look.

Page elements by default have `Children`, which are other page elements, and a `Title`, which
is a `Varchar(255)`.


#### PHP

Below we will demonstrate how to create a simple page element.

1. Create a new file in your `mysite/code` folder called `TextElement.php`

1. Add the following code to that file:
   ```php
   <?php
   class TextElement extends PageElement  {
     public static $singular_name = 'Text';
     public static $plural_name = 'Texts';

     private static $db = array(
         'Content' => 'HTMLText',
     );

     public function getCMSFields() {
         $fields = parent::getCMSFields();
         $fields->removeByName('Children');
        
         return $fields;
     }

     public static function getAllowedPageElements() {
         return array();
     }

     public function getGridFieldPreview() {
         return $this->Content;
     }
   }
   ```

1. Go to `/dev/build?flush=1` in your browser to load the new class and create database entries.

The class we just created adds the `TextElement` page element, which has a `Content` field that
allows adding html content, and it also removes the `Children` field from it's own CMS fields,
which means that we cannot add any children to this element. This is also enforced in the 
`getAllowedPageElements()` function, which returns a list of all the class names of allowed
child elements.

The `getGridFieldPreview()` function returns the content that should be displayed in the gridfield
when editing the page elements.


#### Template

Since this module comes with a default template for the `PageElement` class the `TextElement` 
we just created will already render on your page, but most likely not the way you want it to.  

There are several ways to customize the look of page elements, analog to how SilverStripe
page templates work.

You can use the following variables/function in any of your page element template files:  

| Variable/Function | Description |
|---|---|
| $ClassName | This is the classname of the current page element. |
| $Page | This is the current page that the page element is being displayed on. |
| $Parents | This is an array of the parents of this page element, traveling up the hierarchy. |
| $Layout | This will render the content of the actual page element type, analog to the `$Layout` variable used in the main `Page.ss` file. |
| $RenderChildren($ParentList) | This will loop through all the children of this page element and render them |

Following is an example building on the `TextElement` which was added above.

1. Add a file called `PageElement.ss` to your `/themes/{name}/templates` folder.
   **This is the main template file for all your page sections.**  
   As an example let's use the following content:
   ```
    <div className="$ClassName $Page.ClassName" style="margin-left: {$Parents.Count}em">
      <h1>$Title</h1>
      $Layout
      <div>
        $RenderChildren($ParentList)
      </div>
    </div>
   ```

1. Add a file called `TextElement.ss` to your `/themes/{name}/templates/Layout` folder.
   Now let's add the following content to that file:
   ```
   $Content
   ```

Following the above two steps will add a template for all page elements, as well as a specific
layout template for the `TextElement` page element.
