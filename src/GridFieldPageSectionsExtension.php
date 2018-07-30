<?php

namespace FLXLabs\PageSections;

use SilverStripe\Control\Controller;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Forms\GridField\GridField_ColumnProvider;
use SilverStripe\Forms\GridField\GridField_ActionProvider;
use SilverStripe\Forms\GridField\GridField_DataManipulator;
use SilverStripe\Forms\GridField\GridField_HTMLProvider;
use SilverStripe\Forms\GridField\GridField_URLHandler;
use SilverStripe\Forms\GridField\GridField_FormAction;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\ORM\SS_List;
use SilverStripe\ORM\ArrayList;
use SilverStripe\View\ArrayData;
use SilverStripe\ORM\DataObject;
use SilverStripe\View\Requirements;
use SilverStripe\View\ViewableData;

class GridFieldPageSectionsExtension implements
	GridField_ColumnProvider,
	GridField_ActionProvider,
	GridField_DataManipulator,
	GridField_HTMLProvider,
	GridField_URLHandler
{

	// Parent is either a Page or a PageElement
	protected $parent;
	protected $sortField;

	protected static $allowed_actions = [
		"handleAdd",
		"handleRemove",
		"handleDelete",
		"handleReorder"
	];


	public function __construct($parent, $sortField = "SortOrder") {
		$this->parent = $parent;
		$this->sortField = $sortField;
	}

	public function getParent() {
		return $this->parent;
	}
	public function getSortField() {
		return $this->sortField;
	}

	public function getURLHandlers($grid) {
		return [
			"POST add"        => "handleAdd",
			"POST remove"     => "handleRemove",
			"POST delete"     => "handleDelete",
			"POST reorder"    => "handleReorder"
		];
	}

	public static function getModuleDir() {
		return basename(dirname(__DIR__));
	}

	public function getHTMLFragments($field) {
		$moduleDir = self::getModuleDir();
		Requirements::css($moduleDir . "/css/GridFieldPageSectionsExtension.css");
		Requirements::javascript($moduleDir . "/javascript/GridFieldPageSectionsExtension.js");
		Requirements::add_i18n_javascript($moduleDir . '/javascript/lang', false, true);

		$id = rand(1000000, 9999999);
		$field->addExtraClass("ss-gridfield-pagesections pagesection-" . $id);
		$field->setAttribute("data-id", $id);
		$field->setAttribute("data-url-add", $field->Link("add"));
		$field->setAttribute("data-url-remove", $field->Link("remove"));
		$field->setAttribute("data-url-delete", $field->Link("delete"));
		$field->setAttribute("data-url-reorder", $field->Link("reorder"));

		return [];
	}

	public function augmentColumns($gridField, &$columns) {
		if (!in_array("Reorder", $columns)) {
			array_splice($columns, 0, 0, "Reorder");
		}

		if (!in_array("Actions", $columns)) {
			array_splice($columns, 1, 0, "Actions");
		}

		if (!in_array("TreeNav", $columns)) {
			array_splice($columns, 2, 0, "TreeNav");
		}

		// Insert grid state initial data
		$state = $gridField->getState();
		if (!isset($state->open)) {
			$state->open = [];

			// Open all elements by default if they have children
			// and the method ->isOpenByDefault() returns true
			$list = [];
			$newList = $gridField->getManipulatedList();
			while (count($list) < count($newList)) {
				foreach ($newList as $item) {
					if ($item->isOpenByDefault() && $item->Children()->Count()) {
						$this->openElement($state, $item);
					}
				}
				$list = $newList;
				$newList = $gridField->getManipulatedList();
			}
		}
	}

	public function getColumnsHandled($gridField) {
		return [
			"Reorder",
			"Actions",
			"TreeNav",
		];
	}

	public function getColumnMetadata($gridField, $columnName) {
		return ["title" => ""];
	}

	public function getColumnAttributes($gridField, $record, $columnName) {
		// Handle reorder column
		if ($columnName == "Reorder") {
			return [
				"class" => "col-reorder",
				"data-sort" => $record->getField($this->getSortField()),
			];
		}

		// Handle tree nav column
		else if ($columnName == "TreeNav") {
			// Construct the array of all allowed child elements
			$classes = $record->getAllowedPageElements();
			$elems = [];
			foreach ($classes as $class) {
				$elems[$class] = $class::getSingularName();
			}

			// Find out if this record is allowed as a root record
			// There are two cases, either this GridField is on a page,
			// or it is on a PageElement and we're looking that the children
			$parentClasses = $this->parent->getAllowedPageElements();
			$isAllowedRoot = in_array($record->ClassName, $parentClasses);

			return [
				"class"                 => "col-treenav",
				"data-class"            => $record->ClassName,
				"data-level"            => strval($record->_Level),
				"data-parent"           => $record->_Parent ? strval($record->_Parent->ID) : "",
				"data-allowed-root"     => $isAllowedRoot,
				"data-allowed-elements" => json_encode($elems, JSON_UNESCAPED_UNICODE),
			];
		}

		// Handle the actions column
		else if ($columnName == "Actions") {
			return [
				"class" => "col-actions",
			];
		}
	}

	public function getColumnContent($gridField, $record, $columnName) {
		// Handle reorder column
		if ($columnName == "Reorder") {
			return ViewableData::create()->renderWith("GridFieldDragHandle");
		}

		// Handle treenav column
		else if ($columnName == "TreeNav") {
			if (!$record->canView()) return;

			$id = $record->ID;
			$open = $record->_Open;
			$level = $record->_Level;
			$field = null;

			// Create the tree icon
			$icon = '';
			if ($record->Children() && $record->Children()->Count() > 0) {
				$icon = ($open === true ? 'font-icon-down-open' : 'font-icon-right-open');
			}

			// Create the tree field
			$field = GridField_FormAction::create(
				$gridField,
				"TreeNavAction".$record->ID,
				null,
				"dotreenav",
				["element" => $record]
			);
			$field->addExtraClass("level".$level . ($open ? " is-open" : " is-closed"));
			if (!$record->Children()->Count()) {
				$field->addExtraClass(" is-end");
				$field->setDisabled(true);
			}
			$field->addExtraClass($icon);
			$field->setButtonContent(' ');
			$field->setForm($gridField->getForm());

			return ViewableData::create()->customise([
				"ButtonField" => $field,
				"ID"          => $record->ID,
				"UsedCount"   => $record->Parents()->Count() + $record->getAllSectionParents()->Count(),
				"ClassName"   => $record->i18n_singular_name(),
				"Title"       => $record->Title,
			])->renderWith("GridFieldPageElement");
		}

		else if ($columnName == "Actions") {
			// Create a direct link to edit the item
			$link = Controller::join_links("item", $record->ID, "edit");
			$temp = $record;
			// We need to traverse through all the parents to build the link
			while ($temp->_Parent) {
				$temp = $temp->_Parent;
				$link = Controller::join_links("item", $temp->ID, "ItemEditForm", "field", "Child", $link);
			}
			$link = Controller::join_links($gridField->link(), $link);
			$data = new ArrayData([
				'Link' => $link
			]);
			$editButton = $data->renderWith('SilverStripe\Forms\GridField\GridFieldEditButton');

			// Create a button to add a new child element
			// and save the allowed child classes on the button
			$classes = $record->getAllowedPageElements();
			$elems = [];
			foreach ($classes as $class) {
				$elems[$class] = singleton($class)->singular_name();
			}
			$addButton = GridField_FormAction::create(
				$gridField,
				"AddAction".$record->ID,
				null,
				null,
				null
			);
			$addButton->setAttribute("data-allowed-elements", json_encode($elems, JSON_UNESCAPED_UNICODE));
			$addButton->addExtraClass("col-actions__button add-button font-icon-plus");
			if (!count($elems)) {
				$addButton->setDisabled(true);
			}
			$addButton->setButtonContent('Add');

			// Create a button to delete and/or remove the element from the parent
			$deleteButton = GridField_FormAction::create(
				$gridField,
				"DeleteAction".$record->ID,
				null,
				null,
				null
			);
			$deleteButton->setAttribute(
				"data-used-count",
				$record->Parents()->Count() + $record->getAllSectionParents()->Count()
			);
			$deleteButton->setAttribute(
				"data-parent-id",
				$record->_Parent ? $record->_Parent->ID : $this->parent->ID
			);
			$deleteButton->setAttribute(
				"data-parent-type",
				$record->_Parent ? "element" : "section"
			);

			$deleteButton->addExtraClass("col-actions__button delete-button font-icon-trash-bin");

			$deleteButton->setButtonContent('Delete');

			return ViewableData::create()->customise([
				"EditButton"   => $editButton,
				"AddButton"    => $addButton,
				"DeleteButton" => $deleteButton,
			])->renderWith("GridFieldPageSectionsActionColumn");
		}
	}

	public function getActions($gridField) {
		return ["dotreenav"];
	}

	public function handleAction(GridField $gridField, $actionName, $arguments, $data) {
		if ($actionName == "dotreenav") {
			$elem = $arguments["element"];

			// Check if we have children to show
			if ($elem->Children()->Count() == 0) {
				Controller::curr()->getResponse()->setStatusCode(
					200,
					"This element has no children"
				);
				return;
			}

			// Change the internal GridField state to show the children
			$state = $gridField->getState(true);
			if ($this->isOpen($state, $elem)) {
				$this->closeElement($state, $elem);
			} else {
				$this->openElement($state, $elem);
			}
		}
	}

	// Check if an element is currently opened
	private function isOpen($state, $element) {
		$list = [];
		$base = $element;
		while ($base) {
			$list[] = $base;
			$base = $base->_Parent;
		}
		$list = array_reverse($list);

		$opens = $state->open;
		foreach ($list as $item) {
			if (!isset($opens->{$item->ID})) {
				return false;
			}

			$opens = $opens->{$item->ID};
		}

		return true;
	}
	// Open a specific element in the grid
	private function openElement($state, $element) {
		$list = [];
		$base = $element;
		while ($base) {
			$list[] = $base;
			$base = $base->_Parent;
		}
		$list = array_reverse($list);

		$i = 0;
		$opens = $state->open;
		foreach ($list as $item) {
			if (!isset($opens->{$item->ID})) {
				$opens->{$item->ID} = [];
			}

			$opens = $opens->{$item->ID};
		}
	}
	// Close a specific element in the grid
	private function closeElement($state, $element) {
		$list = [];
		$base = $element->_Parent;
		while ($base) {
			$list[] = $base;
			$base = $base->_Parent;
		}
		$list = array_reverse($list);

		$opens = $state->open;
		foreach ($list as $item) {
			$opens = $opens->{$item->ID};
		}

		unset($opens->{$element->ID});
	}

	// Return the data list of the GridField with all opened elements filled in
	public function getManipulatedData(GridField $gridField, SS_List $dataList) {
		$list = $dataList->sort($this->getSortField())->toArray();

		$state = $gridField->getState(true);
		$opens = $state->open;

		$arr = [];
		foreach ($list as $item) {
			$item->_Level = 0;
			$item->_Open = false;

			$this->getManipulatedItem($arr, $opens, $item, 1);
		}

		return ArrayList::create($arr);
	}

	private function getManipulatedItem(&$arr, $opens, $item, $level) {
		$arr[] = $item;

		// We're done here if the item isn't open
		if (!isset($opens->{$item->ID})) return;

		$sort = $this->getSortField();

		// Get all children and insert them into the list
		$children = $item->Children()->Sort($sort);
		foreach ($children as $child) {
			$child->_Level = $level;
			$child->_Parent = $item;
			$child->_Open = false;

			$this->getManipulatedItem($arr, $opens->{$item->ID}, $child, $level + 1);
		}

		$item->_Open = true;
	}

	public function handleReorder(GridField $gridField, HTTPRequest $request) {
		$vars = $request->postVars();

		$type = $vars["type"];
		$sort = intval($vars["sort"]);

		$item = PageElement::get()->byID($vars["id"]);
		$parent = PageElement::get()->byID($vars["parent"]);
		$newParent = PageElement::get()->byID($vars["newParent"]);

		// First check if the parent accepts the PageElement we're trying to move
		$parentClass = "";
		if ($newParent) {
			$allowed = in_array($item->ClassName, $newParent->getAllowedPageElements());
			$parentClass = $newParent->ClassName;
		} else {
			$allowed = in_array($item->ClassName, $this->parent->getAllowedPageElements());
			$parentClass = $this->parent->ClassName;
		}
		if (!$allowed) {
			Controller::curr()->getResponse()->setStatusCode(
				400,
				"The type " . $item->ClassName . " is not allowed as a child of " . $parentClass
			);
			return $gridField->FieldHolder();
		}

		if ($parent) {
			$parent->Children()->Remove($item);
		} else {
			$gridField->getList()->Remove($item);
		}

		$num = $type == "before" ? -1 : 1;
		$sortBy = $this->getSortField();
		$sortArr = [$sortBy => $sort + $num];

		if ($type == "child") {
			if ($newParent) {
				$newParent->Children()->Add($item, $sortArr);
				$newParent->write();
			} else {
				$gridField->getList()->Add($item, $sortArr);
			}
		} else {
			if ($newParent) {
				$newParent->Children()->Add($item, $sortArr);
				$newParent->write();
			} else {
				$gridField->getList()->Add($item, $sortArr);
			}
		}

		return $gridField->FieldHolder();
	}

	public function handleAdd(GridField $gridField, HTTPRequest $request) {
		$id = intval($request->postVar("id"));
		$type = $request->postVar("type");

		$obj = $gridField->getManipulatedList()->filter("ID", $id)->first();
		if (!$obj) {
			Controller::curr()->getResponse()->setStatusCode(
				400,
				"Could not find element in the current list"
			);
			return $gridField->FieldHolder();
		}
		if (!in_array($type, $obj->getAllowedPageElements())) {
			Controller::curr()->getResponse()->setStatusCode(
				400,
				"The type " . $type . " is not allowed as a child of " . $obj->ClassName
			);
			return $gridField->FieldHolder();
		}

		$child = $type::create();
		$child->Name = "New " . $type;
		$child->write();

		$obj->Children()->Add($child);

		$state = $gridField->getState(true);
		$this->openElement($state, $obj);

		return $gridField->FieldHolder();
	}

	public function handleRemove(GridField $gridField, HTTPRequest $request) {
		$id = intval($request->postVar("id"));
		$obj = DataObject::get_by_id(PageElement::class, $id);

		$parentId = intval($request->postVar("parentId"));
		$parentType = $request->postVar("parentType");

		// Detach it from this parent (from the page section if we're top level)
		if ($parentType == "section") {
			$gridField->getList()->Remove($obj);
		} else {
			$parentObj = DataObject::get_by_id(PageElement::class, $parentId);
			$parentObj->Children()->Remove($obj);
		}

		return $gridField->FieldHolder();
	}

	public function handleDelete(GridField $gridField, HTTPRequest $request) {
		$id = intval($request->postVar("id"));
		$obj = DataObject::get_by_id(PageElement::class, $id);

		// Close the element in case it's open to avoid errors
		$state = $gridField->getState(true);
		if ($this->isOpen($state, $obj)) {
			$this->closeElement($state, $obj);
		}

		// Delete the element
		$obj->delete();

		return $gridField->FieldHolder();
	}
}
