<?php

namespace FLXLabs\PageSections;

use SilverStripe\Core\ClassInfo;
use SilverStripe\ORM\DataObject;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\GridField\GridFieldAddExistingAutocompleter;
use SilverStripe\Forms\GridField\GridFieldConfig;
use SilverStripe\Forms\GridField\GridFieldButtonRow;
use SilverStripe\Forms\GridField\GridFieldToolbarHeader;
use SilverStripe\Forms\GridField\GridFieldDataColumns;
use SilverStripe\Forms\GridField\GridFieldDetailForm;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Versioned\Versioned;

use Symbiote\GridFieldExtensions\GridFieldAddNewMultiClass;

class PageSection extends DataObject
{
	private static $table_name = "FLXLabs_PageSections_PageSection";

	private static $db = [
		"__Name" => "Varchar",
		"__ParentID" => "Int",
		"__ParentClass" => "Varchar",
		"__Counter" => "Int",
	];

	private static $owns = [
		"Elements",
	];

	private static $cascade_deletes = [
		"Elements",
	];

	private static $many_many = [
		"Elements" => [
			"through" => PageSectionPageElementRel::class,
			"from" => "PageSection",
			"to" => "Element"
		]
	];

	public function Parent() {
		$parent = DataObject::get_by_id($this->__ParentClass, $this->__ParentID);
		if ($parent == null) {
			$parent = Versioned::get_latest_version($this->__ParentClass, $this->__ParentID);
		}
		return $parent;
	}

	public function onBeforeWrite() {
		parent::onBeforeWrite();

		$elems = $this->Elements()->Sort("SortOrder")->Column("ID");
		$count = count($elems);
		for ($i = 0; $i < $count; $i++) {
			$this->Elements()->Add($elems[$i], [ "SortOrder" => ($i + 1) * 2, "__NewOrder" => true ]);
		}
	}

	public function onAfterWrite()
	{
		parent::onAfterWrite();

		if (!$this->__isNew && Versioned::get_stage() == Versioned::DRAFT && $this->isChanged("__Counter", DataObject::CHANGE_VALUE)) {
			$this->Parent()->__PageSectionCounter++;
			$this->Parent()->write();
		}
	}

	public function forTemplate()
	{
		$elements = $this->Elements();
		return $this->renderWith(
			"RenderChildren",
			array("Elements" => $elements, "ParentList" => strval($this->ID))
		);
	}

	/**
	 * Gets the name of this PageSection
	 * @return string
	 */
	public function getName() {
		return $this->__Name;
	}

	/**
	 * The classes of allowed child elements
	 *
	 * Gets a list of classnames which are valid child elements of this PageSection.
	 * @param string $section The section for which to get the allowed child classes.
	 * @return string[]
	 */
	public function getAllowedPageElements() {
		return $this->Parent()->getAllowedPageElements($this->__Name);
	}
}
