<?php

namespace FLXLabs\PageSections;

use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Config\Config;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\GridField\GridFieldConfig;
use SilverStripe\Forms\GridField\GridFieldButtonRow;
use SilverStripe\Forms\GridField\GridFieldToolbarHeader;
use SilverStripe\Forms\GridField\GridFieldDataColumns;
use SilverStripe\Forms\GridField\GridFieldDetailForm;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\ORM\SiteTree;
use SilverStripe\ORM\DataExtension;
use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\Versioned\Versioned;

use Symbiote\GridFieldExtensions\GridFieldAddNewMultiClass;
use Symbiote\GridFieldExtensions\GridFieldAddExistingSearchButton;

class PageSectionsExtension extends DataExtension
{
	// Generate the needed relations on the class
	public static function get_extra_config($class = null, $extensionClass = null)
	{
		$has_one = [];
		$owns = [];
		$cascade_deletes = [];

		// Get all the sections that should be added
		$sections = Config::inst()->get($class, "page_sections", Config::EXCLUDE_EXTRA_SOURCES);
		if (!$sections) {
			$sections = ["Main"];
		}

		foreach ($sections as $section) {
			$name = "PageSection".$section;
			$has_one[$name] = PageSection::class;

			$owns[] = $name;
			$cascade_deletes[] = $name;

			// Add the inverse relation to the PageElement class
			/*Config::inst()->update(PageElement::class, "versioned_belongs_many_many", array(
				$class . "_" . $name => $class . "." . $name
			));*/
		}

		// Create the relations for our sections
		return [
			"db" => ["__PageSectionCounter" => "Int"],
			"has_one" => $has_one,
			"owns" => $owns,
			"cascade_deletes" => $cascade_deletes,
		];
	}

	/**
	 * The classes of allowed child elements
	 *
	 * Gets a list of classnames which are valid child elements of this PageSection.
	 * @param string $section The section for which to get the allowed child classes.
	 * @return string[]
	 */
	public function getAllowedPageElements($section = "Main")
	{
		$classes = array_values(ClassInfo::subclassesFor(PageElement::class));
		$classes = array_diff($classes, [PageElement::class]);
		$ret = [];
		foreach($classes as $class) {
			if ($class::$canBeRoot) $ret[] = $class;
		}
		return $ret;
	}

	/**
	 * Gets a list of the PageSection names of this page.
	 * @return string[]
	 */
	public function getPageSectionNames()
	{
		$sections = Config::inst()->get($this->owner->ClassName, "page_sections", Config::EXCLUDE_EXTRA_SOURCES);
		if (!$sections) {
			$sections = ["Main"];
		}
		return $sections;
	}

	public function onBeforeWrite()
	{
		parent::onBeforeWrite();

		if ($this->owner->ID) {
			$sections = $this->getPageSectionNames();

			foreach ($sections as $sectionName) {
				$name = "PageSection".$sectionName;

				// Create a page section if we don't have one yet
				if (!$this->owner->$name()->ID) {
					$section = PageSection::create();
					$section->__Name = $sectionName;
					$section->__ParentID = $this->owner->ID;
					$section->__ParentClass = $this->owner->ClassName;
					$section->__isNew = true;
					$section->write();
					$this->owner->$name = $section;
				}
			}
		}
	}

	public function updateCMSFields(FieldList $fields) {
		$sections = $this->getPageSectionNames();

		$fields->removeByName("__PageSectionCounter");

		foreach ($sections as $section) {
			$name = "PageSection".$section;

			$fields->removeByName($name);
			$fields->removeByName($name . "ID");

			if ($this->owner->ID) {
				$tv = new TreeView($name, $section, $this->owner->$name());
				$fields->addFieldToTab("Root.PageSections.{$section}", $tv);
			}
		}
	}

	/**
	 * Renders the PageSection of this page.
	 * @param string $name The name of the PageSection to render
	 */
	public function RenderPageSection($name = "Main")
	{
		$elements = $this->owner->{"PageSection" . $name}()->Elements()->Sort("SortOrder");
		return $this->owner->renderWith(
			"RenderChildren",
			["Elements" => $elements, "ParentList" => strval($this->owner->ID)]
		);
	}

	/**
	 * Gets the published state of the page this PageSection belongs to.
	 * @return \SilverStripe\ORM\FieldType\DBField
	 */
	public function getPublishState()
	{
		return DBField::create_field("HTMLText", $this->owner->latestPublished() ? "Published" : "Draft");
	}
}
