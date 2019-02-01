<?php

namespace FLXLabs\PageSections;

use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\ReadonlyField;
use SilverStripe\Forms\GridField\GridFieldConfig;
use SilverStripe\Forms\GridField\GridFieldConfig_Base;
use SilverStripe\Forms\GridField\GridFieldButtonRow;
use SilverStripe\Forms\GridField\GridFieldToolbarHeader;
use SilverStripe\Forms\GridField\GridFieldDataColumns;
use SilverStripe\Forms\GridField\GridFieldDetailForm;
use SilverStripe\Forms\GridField\GridFieldEditButton;
use SilverStripe\Forms\GridField\GridFieldFooter;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Member;

use Symbiote\GridFieldExtensions\GridFieldAddNewMultiClass;
use Symbiote\GridFieldExtensions\GridFieldAddExistingSearchButton;
use SilverStripe\Forms\Tab;

use SilverStripe\Versioned\Versioned;

class PageElement extends DataObject
{

	private static $table_name = "FLXLabs_PageSections_PageElement";

	protected static $defaultIsOpen = true;
	public static $canBeRoot = true;

	public static function getSingularName()
	{
		return static::$singular_name;
	}
	public static function getPluralName()
	{
		return static::$plural_name;
	}
	public static function isOpenByDefault()
	{
		return static::$defaultIsOpen;
	}

	public function canView($member = null)
	{
		return true;
	}
	public function canEdit($member = null)
	{
		return true;
	}
	public function canDelete($member = null)
	{
		return false;
	}
	public function canCreate($member = null, $context = [])
	{
		return true;
	}

	private static $can_be_root = true;

	private static $db = [
		"Name" => "Varchar(255)",
	];

	private static $many_many = [
		"Children" => [
			"through" => PageElementSelfRel::class,
			"from" => "Parent",
			"to" => "Child",
		]
	];

	private static $belongs_many_many = [
		"Parents" => PageElement::class . ".Children",
		"PageSections" => PageSection::class . ".Elements",
	];

	private static $owns = [
		"Children",
	];

	private static $cascade_deletes = [
		"Children",
	];

	private static $summary_fields = [
		"GridFieldPreview",
	];

	private static $searchable_fields = [
		"ClassName",
		"Name",
		"ID",
	];

	// Returns all page element classes, without the base class
	public static function getAllPageElementClasses()
	{
		$classes = array_values(ClassInfo::subclassesFor(PageElement::class));
		$classes = array_diff($classes, [PageElement::class]);
		return $classes;
	}

	/**
	 * The classes of allowed child elements
	 *
	 * Gets a list of classnames which are valid child elements of this PageElement.
	 * @return string[]
	 */
	public function getAllowedPageElements()
	{
		return self::getAllPageElementClasses();
	}

	public function onBeforeWrite()
	{
		parent::onBeforeWrite();

		// If a  field changed then update the counter, unless it's the counter that changed
		$changed = $this->getChangedFields(true, DataObject::CHANGE_VALUE);
		if (count($changed) > 0 && (!isset($changed["__Counter"]) || $changed["__Counter"]["level"] <= 1)) {
			$this->__Counter++;
		}

		$elems = $this->Children()->Sort("SortOrder")->Column("ID");
		$count = count($elems);
		for ($i = 0; $i < $count; $i++) {
			$this->Children()->Add($elems[$i], [ "SortOrder" => ($i + 1) * 2, "__NewOrder" => true ]);
		}
	}

	public function onAfterWrite()
	{
		parent::onAfterWrite();
	}

	public function onAfterDelete()
	{
		parent::onAfterDelete();
	}

	/**
	 * Gets the preview of this PageElement in the TreeView.
	 * @return string
	 */
	public function getTreeViewPreview()
	{
		return $this->GridFieldPreview;
	}

	/** 
	 * Gets all parents that this PageElement is rendered on. 
	 * 
	 * Adds the following properties to each parent: 
	 *   __PageSection: The PageSection on the parent that the PageElement is from.
	 * @return \DataObject[] 
	 */
	public function getAllSectionParents() {
		$parents = ArrayList::create();

		foreach ($this->PageSections() as $section) {
			$p = $section->Parent();
			if (!$p || !$p->ID) {
				// If our parent doesn't have an ID it's probably deleted/archived.
				$p = $archived = Versioned::get_latest_version($section->__ParentClass, $section->__ParentID);
			}

			$p->__PageSection = $section;
			$parents->add($p);
		}

		return $parents;
	}

	/**
	 * Recursively gets all the parents of this element, in no particular order.
	 * @return \FLXLabs\PageSections\PageElement[]
	 */
	public function getAllParents()
	{
		$parents = ArrayList::create($this->Parents()->toList());
		foreach ($this->Parents() as $parent) {
			$parents->merge($parent->getAllParents());
		}
		return $parents;
	}

	public function getCMSFields()
	{
		$fields = parent::getCMSFields();
		$fields->removeByName('Pages');
		$fields->removeByName('Parents');
		$fields->removeByName("PageSections");
		$fields->removeByName('__Counter');

		$fields->removeByName("Children");

		// Add our newest version as a readonly field
		$fields->addFieldsToTab(
			"Root.Main",
			ReadonlyField::create("Version", "Version", $this->Version),
			"Title"
		);

		// Create an array of all the sections this element is on
		$parents = $this->getAllSectionParents();

		if ($parents->Count() > 0) {
			$config = GridFieldConfig_Base::create()
				->removeComponentsByType(GridFieldDataColumns::class)
				->addComponent($dataColumns = new GridFieldDataColumns());
			$dataColumns->setDisplayFields([
				"ID" => "ID",
				"ClassName" => "Type",
				"Title" => "Title",
				"__PageSection.__Name" => "PageSection",
			]);
			$gridField = GridField::create("Pages", "Section Parents", $parents, $config);
			$fields->addFieldToTab("Root.SectionParents", $gridField);
		}

		return $fields;
	}

	/**
	 * Gets the list of all parents of this PageElement.
	 * @return string[]
	 */
	public function getParentIDs()
	{
		$IDArr = [$this->ID];
		foreach ($this->Parents() as $parent) {
			$IDArr = array_merge($IDArr, $parent->getParentIDs());
		}
		return $IDArr;
	}

	/**
	 * Renders the children of this PageElement
	 * @param string[] $parents The list of parent IDs of this PageElement
	 * @return string
	 */
	public function renderChildren($parents = null)
	{
		return $this->renderWith(
			"RenderChildren",
			[
				"Elements" => $this->Children(),
				"ParentList" => strval($this->ID) . "," . $parents,
			]
		);
	}

	public function forTemplate($parentList = "")
	{
		$parents = ArrayList::create();
		$splits = explode(",", $parentList);
		$num = count($splits);
		for ($i = 0; $i < $num - 1; $i++) {
			$parents->add(PageElement::get()->byID($splits[$i]));
		}
		$page = SiteTree::get()->byID($splits[$num - 1]);

		return $this->renderWith(
			array_reverse($this->getClassAncestry()),
			[
				"ParentList" => $parentList,
				"Parents" => $parents,
				"Page" => $page,
			]
		);
	}

	public function replaceDefaultButtons()
	{
		return true;
	}
}
