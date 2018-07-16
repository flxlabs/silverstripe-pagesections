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
use SilverStripe\Versioned\Versioned;

use Symbiote\GridFieldExtensions\GridFieldAddNewMultiClass;
use Symbiote\GridFieldExtensions\GridFieldAddExistingSearchButton;
use UncleCheese\BetterButtons\Actions\PrevNext;
use UncleCheese\BetterButtons\Actions\CustomAction;
use UncleCheese\BetterButtons\Buttons\Save;
use UncleCheese\BetterButtons\Buttons\SaveAndClose;
use SilverStripe\Forms\Tab;

class PageElement extends DataObject
{

	private static $table_name = "FLXLabs_PageSections_PageElement";

	protected static $singularName = "Element";
	protected static $pluralName = "Elements";
	protected static $defaultIsOpen = true;

	public static function getSingularName()
	{
		return static::$singularName;
	}
	public static function getPluralName()
	{
		return static::$pluralName;
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
		return true;
	}
	public function canCreate($member = null, $context = [])
	{
		return true;
	}

	private static $can_be_root = true;

	private static $db = [
		"Name" => "Varchar(255)",
		"__Counter" => "Int"
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

	private static $many_many_extraFields = [
		"Children" => [
			"SortOrder" => 'Int',
		],
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

	private static $better_buttons_actions = array (
		'publishOnAllPages',
	);

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

		$elems = $this->Children()->Sort("SortOrder")->Column("ID");
		$count = count($elems);
		for ($i = 0; $i < $count; $i++) {
			$this->Children()->Add($elems[$i], [ "SortOrder" => ($i + 1) * 2, "__NewOrder" => true ]);
		}
	}

	public function onAfterWrite()
	{
		parent::onAfterWrite();

		if (Versioned::get_stage() == Versioned::DRAFT) {
			foreach ($this->PageSections() as $section) {
				$section->__Counter++;
				$section->write();
			}
		}
	}

	public function onAfterDelete()
	{
		parent::onAfterDelete();

		if (Versioned::get_stage() == Versioned::DRAFT) {
			foreach ($this->PageSections() as $section) {
				$section->__Counter++;
				$section->write();
			}
		}
	}

	/**
	 * Gets the TreeView for the children of this PageElement
	 * @return \FLXLabs\PageSections\TreeView
	 */
	public function getChildrenTreeView()
	{
		return new TreeView("Child", "Children", $this->Children());
	}

	/**
	 * Gets the preview of this PageElement in the GridField.
	 * @return string
	 */
	public function getGridFieldPreview()
	{
		return $this->Name;
	}

	/**
	 * Gets all pages that this PageElement is rendered on.
	 *
	 * Adds the following properties to each page:
	 *   __PageSection: The PageSection on the page that the PageElement is from.
	 *   __PageElementVersion: The version of the PageElement being used.
	 *   __PageElementPublishedVersion: The published version of the PageElement being used.
	 * @return \Page[]
	 */
	public function getAllPages()
	{
		$pages = ArrayList::create();

		foreach ($this->PageSections() as $section) {
			$page = $section->Page();
			$stage = Versioned::get_stage();
			Versioned::set_stage(Versioned::LIVE);
			$pubSection = DataObject::get_by_id($section->ClassName, $section->ID);
			$pubElem = $pubSection ? $pubSection->Elements()->filter("ID", $this->ID)->First() : null;

			// Add extra info to pages
			$page->__PageSection = $section;
			$page->__PageElementVersion = $section->Elements()->filter("ID", $this->ID)->First()->Version;
			$page->__PageElementPublishedVersion = $pubElem ? $pubElem->Version : "Not published";
			
			Versioned::set_stage($stage);
			$pages->add($page);
		}

		return $pages;
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
		if ($this->ID && count($this->getAllowedPageElements()) > 0) {
			$fields->insertAfter(
				"Main",
				Tab::create("Child", "Children", $this->getChildrenTreeView())
			);
		}

		// Add our newest version as a readonly field
		$fields->addFieldsToTab(
			"Root.Main",
			ReadonlyField::create("Version", "Version", $this->Version),
			"Title"
		);

		// Create an array of all the pages this element is on
		$pages = $this->getAllPages();

		if ($pages->Count() > 0) {
			$config = GridFieldConfig_Base::create()
				->removeComponentsByType(GridFieldDataColumns::class)
				->addComponent($dataColumns = new GridFieldDataColumns())
				->addComponent(new GridFieldDetailForm())
				->addComponent(new GridFieldEditButton());
			$dataColumns->setDisplayFields([
				"ID" => "ID",
				"ClassName" => "Type",
				"Title" => "Title",
				"__PageSection.Name" => "PageSection",
				"__PageElementVersion" => "Element version",
				"__PageElementPublishedVersion" => "Published element version",
				"getPublishState" => "Page state",
			]);
			$gridField = GridField::create("Pages", "Pages", $pages, $config);
			$fields->addFieldToTab("Root.Pages", $gridField);
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

	public function getBetterButtonsUtils()
	{
		$fieldList = FieldList::create([
			PrevNext::create(),
		]);
		return $fieldList;
	}

	public function getBetterButtonsActions()
	{
		$actions = FieldList::create([
			SaveAndClose::create(),
			CustomAction::create('publishOnAllPages', 'Publish on all pages')
				->setRedirectType(CustomAction::REFRESH)
		]);
		return $actions;
	}

	/**
	 * Publishes all pages that this PageElement is on.
	 */
	public function publishOnAllPages()
	{
		foreach ($this->getAllPages() as $page) {
			$page->publish(Versioned::get_stage(), Versioned::LIVE);
		}
		return 'Published on all pages';
	}
}
