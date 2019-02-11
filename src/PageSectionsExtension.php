<?php

namespace FLXLabs\PageSections;

use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Config\Config;
use SilverStripe\Forms\FieldList;
use SilverStripe\ORM\DataExtension;

class PageSectionsExtension extends DataExtension
{
	// Generate the needed relations on the class
	public static function get_extra_config($class = null, $extensionClass = null)
	{
		$has_one = [];
		$names = [];

		// Get all the sections that should be added
		$sections = Config::inst()->get($class, "page_sections", Config::EXCLUDE_EXTRA_SOURCES);
		if (!$sections) {
			$sections = ["Main"];
		}

		foreach ($sections as $section) {
			$name = "PageSection".$section;
			$has_one[$name] = PageSection::class;

			$names[] = $name;
		}

		// Create the relations for our sections
		return [
			"has_one" => $has_one,
			"owns" => $names,
			"cascade_duplicates" => $names,
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

	public function onAfterWrite()
	{
		parent::onAfterWrite();

		if ($this->owner->ID) {
			$sections = $this->getPageSectionNames();

			foreach ($sections as $sectionName) {
				$name = "PageSection".$sectionName;

				if (!$this->owner->{$name . "ID"}) {
					// Create a new page section if we don't have one yet.
					$section = PageSection::create();
					$section->__Name = $sectionName;
					$section->__ParentID = $this->owner->ID;
					$section->__ParentClass = $this->owner->ClassName;
					$section->write();

					$this->owner->{$name . "ID"} = $section->ID;
					$this->owner->write();
				}
			}
		}
	}

	public function updateCMSFields(FieldList $fields) {
		$sections = $this->getPageSectionNames();

		$fields->removeByName("__PageSectionCounter");

		foreach ($sections as $sectionName) {
			$name = "PageSection".$sectionName;

			$fields->removeByName($name);
			$fields->removeByName($name . "ID");

			if ($this->owner->ID && $this->owner->$name->ID) {
				$tv = new TreeView($name, $sectionName, $this->owner->$name);
				$fields->addFieldToTab("Root.PageSections.{$sectionName}", $tv);
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
}
