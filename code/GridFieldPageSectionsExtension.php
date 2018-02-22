<?php

class GridFieldPageSectionsExtension implements
	GridField_ColumnProvider,
	GridField_ActionProvider,
	GridField_DataManipulator,
	GridField_HTMLProvider,
	GridField_URLHandler
{

	protected $page;
	protected $sortField;

	protected static $allowed_actions = array(
		"handleAdd",
		"handleRemove",
		"handleDelete",
		"handleReorder",
		"handleMoveToPage"
	);


	public function __construct($page, $sortField = "SortOrder") {
		$this->page = $page;
		$this->sortField = $sortField;
	}

	public function getPage() {
		return $this->page;
	}
	public function getSortField() {
		return $this->sortField;
	}

	public function getURLHandlers($grid) {
		return array(
			"POST add"        => "handleAdd",
			"POST remove"     => "handleRemove",
			"POST delete"     => "handleDelete",
			"POST reorder"    => "handleReorder",
			"POST movetopage" => "handleMoveToPage"
		);
	}

	public static function getModuleDir() {
		return basename(dirname(__DIR__));
	}

	public function getHTMLFragments($field) {
		$moduleDir = self::getModuleDir();
		Requirements::css($moduleDir . "/css/GridFieldPageSectionsExtension.css");
		Requirements::javascript($moduleDir . "/javascript/GridFieldPageSectionsExtension.js");

		$id = rand(1000000, 9999999);
		$field->addExtraClass("ss-gridfield-pagesections");
		$field->setAttribute("data-id", $id);
		$field->setAttribute("data-url-add", $field->Link("add"));
		$field->setAttribute("data-url-remove", $field->Link("remove"));
		$field->setAttribute("data-url-delete", $field->Link("delete"));
		$field->setAttribute("data-url-reorder", $field->Link("reorder"));
		$field->setAttribute("data-url-movetopage", $field->Link("movetopage"));

		return array();
	}

	public function augmentColumns($gridField, &$columns) {
		if (!in_array("Reorder", $columns)) {
			array_splice($columns, 0, 0, "Reorder");
		}

		if (!in_array("TreeNav", $columns)) {
			array_splice($columns, 1, 0, "TreeNav");
		}

		if (!in_array("Actions", $columns)) {
			array_push($columns, "Actions");
		}

		// Insert grid state initial data
		$state = $gridField->getState();
		if (!isset($state->open)) {
			$state->open = array();

			// Open all elements by default
			$list = array();
			$newList = $gridField->getManipulatedList();
			while (count($list) < count($newList)) {
				foreach ($newList as $item) {
					if ($item->isOpenByDefault()) {
						$this->openElement($state, $item);
					}
				}
				$list = $newList;
				$newList = $gridField->getManipulatedList();
			}
		}
	}

	public function getColumnsHandled($gridField) {
		return array(
			"Reorder",
			"TreeNav",
			"Actions",
		);
	}

	public function getColumnMetadata($gridField, $columnName) {
		return array("title" => "");
	}

	public function getColumnAttributes($gridField, $record, $columnName) {
		// Handle reorder column
		if ($columnName == "Reorder") {
			return array(
				"class" => "col-reorder",
				"data-sort" => $record->getField($this->getSortField()),
			);
		}

		// Handle tree nav column
		else if ($columnName == "TreeNav") {
			$classes = $record->getAllowedPageElements();
			$elems = array();
			foreach ($classes as $class) {
				$elems[$class] = $class::$singular_name;
			}

			return array(
				"class" => "col-treenav",
				"data-class" => $record->ClassName,
				"data-level" => strval($record->_Level),
				"data-parent" => $record->_Parent ? strval($record->_Parent->ID) : "",
				"data-allowed-elements" => json_encode($elems, JSON_UNESCAPED_UNICODE),
			);
		}

		// Handle the actions column
		else if ($columnName == "Actions") {
			return array(
				"class" => "col-actions",
			);
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

			if ($record->Children() && $record->Children()->Count() > 0) {
				$icon = ($open === true ? '<span class="is-open">▼</span>'
					: '<span class="is-closed">▶</span>');
			} else {
				$icon = '<span class="is-end">◼</span>';
			}

			$field = GridField_FormAction::create(
				$gridField,
				"TreeNavAction".$record->ID,
				null,
				"dotreenav",
				array("element" => $record)
			);
			$field->addExtraClass("level".$level . ($open ? " is-open" : " is-closed"));
			$field->setButtonContent($icon);
			$field->setForm($gridField->getForm());

			return ViewableData::create()->customise(array(
				"ButtonField" => $field,
				"Title" => $record->i18n_singular_name(),
			))->renderWith("GridFieldPageElement");
		}

		else if ($columnName == "Actions") {
			$link = Controller::join_links("item", $record->ID, "edit");
			$temp = $record;
			while ($temp->_Parent) {
				$temp = $temp->_Parent;
				$link = Controller::join_links("item", $temp->ID,
					"ItemEditForm", "field", "Children", $link
				);
			}
			$link = Controller::join_links($gridField->link(), $link);
			return "<a href='$link'>Edit</a>";
		}
	}

	public function getActions($gridField) {
		return array("dotreenav");
	}

	public function handleAction(GridField $gridField, $actionName, $arguments, $data) {
		if ($actionName == "dotreenav") {
			$elem = $arguments["element"];

			if ($elem->Children()->Count() == 0) {
				Controller::curr()->getResponse()->setStatusCode(
					200,
					"This element has no children"
				);
				return;
			}

			$state = $gridField->getState(true);
			if ($this->isOpen($state, $elem)) {
				$this->closeElement($state, $elem);
			} else {
				$this->openElement($state, $elem);
			}
		}
	}

	private function isOpen($state, $element) {
		$list = array();
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
	private function openElement($state, $element) {
		$list = array();
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
				$opens->{$item->ID} = array();
			}

			$opens = $opens->{$item->ID};
		}
	}
	private function closeElement($state, $element) {
		$list = array();
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

	public function getManipulatedData(GridField $gridField, SS_List $dataList) {
		$list = $dataList->sort($this->getSortField())->toArray();

		$state = $gridField->getState(true);
		$opens = $state->open;

		$arr = array();
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

	public function handleReorder(GridField $gridField, SS_HTTPRequest $request) {
		$vars = $request->postVars();

		$type = $vars["type"];
		$sort = intval($vars["sort"]);

		$item = PageElement::get()->byID($vars["id"]);
		$parent = PageElement::get()->byID($vars["parent"]);
		$newParent = PageElement::get()->byID($vars["newParent"]);

		$parentClass = "";
		if ($newParent) {
			$allowed = in_array($item->ClassName, $newParent->getAllowedPageElements());
			$parentClass = $newParent->ClassName;
		} else {
			$allowed = in_array($item->ClassName, $this->getPage()->getAllowedPageElements());
			$parentClass = $this->getPage()->ClassName;
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
		$sortArr = array($sortBy => $sort + $num);

		if ($type == "child") {
			if ($newParent) {
				$newParent->Children()->Add($item, $sortArr);
			} else {
				$gridField->getList()->Add($item, $sortArr);
			}
		} else {
			if ($newParent) {
				$newParent->Children()->Add($item, $sortArr);
				$newParent->write();
			} else {
				$gridField->getList()->Add($item, $sortArr);
				$this->getPage()->write();
			}
		}

		return $gridField->FieldHolder();
	}

	public function handleAdd(GridField $gridField, SS_HTTPRequest $request) {
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
		$child->Title = "New " . $type;
		$child->write();

		$obj->Children()->Add($child);

		$state = $gridField->getState(true);
		$this->openElement($state, $obj);

		return $gridField->FieldHolder();
	}

	public function handleRemove(GridField $gridField, SS_HTTPRequest $request) {
		$id = intval($request->postVar("id"));
		$obj = DataObject::get_by_id("PageElement", $id);

		$parentId = intval($request->postVar("parentId"));
		$parentObj = DataObject::get_by_id("PageElement", $parentId);

		// Detach it from this parent (from the page if we're top level)
		if (!$parentObj) {
			$gridField->getList()->Remove($obj);
		} else {
			$parentObj->Children()->Remove($obj);
		}

		return $gridField->FieldHolder();
	}

	public function handleDelete(GridField $gridField, SS_HTTPRequest $request) {
		$id = intval($request->postVar("id"));
		$obj = DataObject::get_by_id("PageElement", $id);

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
