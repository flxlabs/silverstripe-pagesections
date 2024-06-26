<?php

namespace FLXLabs\PageSections;

use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Config\Config;
use SilverStripe\Forms\FieldList;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DataExtension;
use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\Versioned\Versioned;

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
			$name = "PageSection" . $section;
			$has_one[$name] = PageSection::class;

			$names[] = $name;

			// Add the inverse relation to the PageElement class
			/*Config::inst()->update(PageElement::class, "versioned_belongs_many_many", array(
				$class . "_" . $name => $class . "." . $name
			));*/
		}

		// Create the relations for our sections
		return [
			"db" => ["__PageSectionCounter" => "Int"],
			"has_one" => $has_one,
			"owns" => $names,
			"cascade_deletes" => $names,
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
		foreach ($classes as $class) {
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
				$name = "PageSection" . $sectionName;

				if (!$this->owner->{$name . "ID"}) {
					// Restore or create a page section if we don't have one yet
					$this->restoreOrCreate($sectionName);
				} else if ($this->owner->$name()->__ParentClass !== $this->owner->ClassName) {
					$this->owner->$name()->__ParentClass = $this->owner->ClassName;
					$this->owner->$name()->write();
				}
			}
		}
	}

	public function onBeforeArchive()
	{
		$sections = $this->getPageSectionNames();

		foreach ($sections as $sectionName) {
			$name = "PageSection" . $sectionName;
			$this->owner->{$name . "ID"} = 0;
		}
	}

	public function updateCMSFields(FieldList $fields)
	{
		$sections = $this->getPageSectionNames();

		$fields->removeByName("__PageSectionCounter");

		foreach ($sections as $sectionName) {
			$name = "PageSection" . $sectionName;

			$fields->removeByName($name);
			$fields->removeByName($name . "ID");

			if ($this->owner->ID && $this->owner->$name->ID) {
				$tv = new TreeView($name, $sectionName, $this->owner->$name);
				$fields->addFieldToTab("Root.PageSections.{$sectionName}", $tv);
			}
		}
	}

	// Add a get method for each page section to the owner
	public function allMethodNames($custom = false)
	{
		$arr = [
			"getAllowedPageElements",
			"getPageSectionNames",
			"onAfterWrite",
			"updateCMSFields",
			"allMethodNames",
			"RenderPageSection",
			"getPublishState"
		];

		$sections = $this->getPageSectionNames();
		foreach ($sections as $section) {
			$arr[] = "PageSection" . $section;
			$arr[] = "getPageSection" . $section;
		}

		return $arr;
	}

	public function __call($method, $arguments)
	{
		//var_dump($method);

		// Check if we're trying to get a page section
		if (mb_strpos($method, "getPageSection") === 0) {
			$name = mb_substr($method, 3);
			$sectionName = mb_substr($name, 11);
			$section = $this->owner->$name();

			// If we have a page section we're good
			if ($section && $section->ID) {
				if ($section->__ParentClass != $this->owner->ClassName || $section->__ParentID != $this->owner->ID) {
					$section->__ParentClass = $this->owner->ClassName;
					$section->__ParentID = $this->owner->ID;
					$section->write();
				}
				return $section;
			}

			// Fix for archived errors
			if ($this->owner->isArchived()) {
				return new PageSection();
			}

			return $this->restoreOrCreate($sectionName);
		} else {
			throw new \Error("Unknown method $method on PageSectionsExtension");
		}
	}

	private function restoreOrCreate($sectionName)
	{
		if (mb_strpos($sectionName, "PageSection") !== false) {
			throw new \Error("PageSection name should not contain 'PageSection' when restoring or creating!");
		}

		$name = "PageSection" . $sectionName;

		// Try restoring the section from the history
		$id = $this->owner->{$name . "ID"};
		$archived = $id ? Versioned::get_latest_version(PageSection::class, $id) : null;
		if ($archived && $archived->ID) {
			// Update the back references
			$archived->ID = $this->owner->{$name . "ID"};
			$archived->__ParentClass = $this->owner->ClassName;
			$archived->__ParentID = $this->owner->ID;
			// Save a copy in draft
			$id = $archived->writeToStage(Versioned::DRAFT, true);

			$this->owner->flushCache(true);

			return DataObject::get_by_id(PageSection::class, $id, false);
		} else {
			$section = PageSection::create();
			$section->__Name = $sectionName;
			$section->__ParentID = $this->owner->ID;
			$section->__ParentClass = $this->owner->ClassName;
			$section->__isNew = true;
			$section->write();

			$this->owner->{$name . "ID"} = $section->ID;
			$this->owner->write();

			return $section;
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
		$stage = "Draft";
		if ($this->owner->isPublished()) {
			$stage = "Published";
		} else if ($this->owner->isArchived()) {
			$stage = "Archived";
		}
		return DBField::create_field("HTMLText", $stage);
	}
}
