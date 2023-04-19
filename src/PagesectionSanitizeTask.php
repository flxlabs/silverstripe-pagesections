<?php

use FLXLabs\PageSections\PageElement;
use FLXLabs\PageSections\PageElementSelfRel;
use FLXLabs\PageSections\PageSectionPageElementRel;
use SilverStripe\Dev\BuildTask;
use SilverStripe\Security\Permission;
use SilverStripe\Security\Security;
use SilverStripe\Versioned\Versioned;

/**
 * Cleans up leftover relations
 */
class PagesectionSanitizeTask extends BuildTask
{

    private static $segment = 'PagesectionSanitizeTask';

    protected $title = 'Sanitizes the db from broken relations caused by a bug in the PageSections module';

    protected $description = 'Affected version: dev-feature/ss4-treeview#0c33dfe98b17e3d88127ff1b3f70fc7c443e040f. Note: you will have to unpublish and publish the affected pages again';

    public function run($request)
    {
        if (!Permission::check('ADMIN') && !Director::is_cli()) {
            $response = Security::permissionFailure();
            if ($response) {
                $response->output();
            }
            die;
        }

        $stages = [Versioned::DRAFT];
        foreach ($stages as $stage) {
            Versioned::set_stage($stage);
            foreach (Page::get() as $page) {
                echo "Page: " . $page->URLSegment . "<br>";
                foreach ($page->getPageSectionNames() as $sectionName) {
                    $section = $page->__get("PageSection{$sectionName}");
                    echo "Section: " . $section->ID . "<br>";
                    $trustedElementIds = $section->Elements()->Column("ID");
                    $untrustedElementIds = PageSectionPageElementRel::get()->Filter("PageSectionID", $section->ID)->Column("ElementID");
                    $toDelete = array_diff($untrustedElementIds, $trustedElementIds);
                    echo "trusted:<br>";
                    echo implode($trustedElementIds, ", ");
                    echo "<br>untrusted:<br>";
                    echo implode($untrustedElementIds, ", ");
                    if (count($toDelete)) {
                        echo "<br>to delete:<br>";
                        echo implode($toDelete, ", ");
                        $section->Elements()->removeMany($toDelete);
                    }
                }
                echo "<hr>";
            }

            foreach (PageElement::get() as $element) {
                echo "Element: " . $element->ID . "<br>";
                $trustedElementIds = $element->Children()->Column("ID");
                $untrustedElementIds = PageElementSelfRel::get()->Filter("ParentID", $element->ID)->Column("ChildID");
                $toDelete = array_diff($untrustedElementIds, $trustedElementIds);
                echo "trusted:<br>";
                echo implode($trustedElementIds, ", ");
                echo "<br>untrusted:<br>";
                echo implode($untrustedElementIds, ", ");
                if (count($toDelete)) {
                    echo "<br>to delete:<br>";
                    echo implode($toDelete, ", ");
                    $element->Children()->removeMany($toDelete);
                }
                echo "<hr>";
            }

        }
    }
}
