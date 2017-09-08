<?php

class GridFieldPageSectionsExtension implements
	GridField_ColumnProvider,
	GridField_ActionProvider,
	GridField_DataManipulator,
	GridField_HTMLProvider,
	GridField_URLHandler,
	GridField_SaveHandler
{

	protected $sortField;

	protected static $allowed_actions = array(
		"handleAdd",
		"handleRemove",
		"handleDelete",
		"handleReorder",
		"handleMoveToPage"
	);


	public function __construct($sortField = 'Sort') {
		//parent::__construct();
		$this->sortField = $sortField;
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
		$state = $gridField->getState();

		// Insert column for row sorting
		if (!in_array("Reorder", $columns) && $state->GridFieldOrderableRows->enabled) {
			array_splice($columns, 0, 0, "Reorder");
		}

		// Insert columns for level
		if (!isset($state->open)) $state->open = array();
		if (!in_array("TreeNav", $columns)) {
			array_splice($columns, 1, 0, "TreeNav");
		}

		if (!in_array("Actions", $columns)) {
			array_push($columns, "Actions");
		}
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
				"class" => "col-treenav group" . $record->_GroupId,
				"data-class" => $record->ClassName,
				"data-level" => strval($record->_Level),
				"data-group" => strval($record->_GroupId),
				"data-allowed-elements" => json_encode($elems, JSON_UNESCAPED_UNICODE),
			);
		}

		else return array();
	}

	public function getColumnMetadata($gridField, $columnName) {
		return array("title" => "");
	}

	public function getColumnsHandled($gridField) {
		return array(
			"Reorder",
			"TreeNav",
			"Actions",
		);
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
				"Title" => $record->Title,
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

		$curr = $state->open;
		foreach ($list as $item) {
			if (!isset($curr->{$item->ID})) {
				return false;
			}

			$curr = $curr->{$item->ID}->open;
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
		$curr = $state->open;
		foreach ($list as $item) {
			if (!isset($curr->{$item->ID})) {
				$curr->{$item->ID} = array(
					"level" => $i,
					"open" => array(),
				);
			}

			$i++;
			$curr = $curr->{$item->ID}->open;
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

		$curr = $state->open;
		foreach ($list as $item) {
			$curr = $curr->{$item->ID}->open;
		}

		unset($curr->{$element->ID});
	}

	public function getManipulatedData(GridField $gridField, SS_List $dataList) {
		$state = $gridField->getState(true);
		$opens = $state->open->toArray();
		$list = $dataList->toArray();
		$groupId = 0;

		$arr = array();
		foreach ($list as $item) {
			$item->_GroupId = $groupId % 2;
			$item->_Level = 0;
			$item->_Open = false;
			$groupId++;

			$arr[] = $item;
			if (isset($opens[$item->ID])) {
				$item->_Open = true;
				$cArr = $this->getOpens($item, $opens[$item->ID]);
				$arr = array_merge($arr, $cArr);
			}
		}

		return ArrayList::create($arr);
	}

	// Add element and all it's open children
	private function getOpens($item, $openInfo) {
		$arr = array();
		$sort = $this->getSortField();
		$level = $openInfo["level"];
		$opens = $openInfo["open"];

		// Get all children and insert them into the list
		$children = $item->Children()->Sort($sort);
		foreach ($children as $child) {
			$child->_Level = $level + 1;
			$child->_GroupId = $item->_GroupId;
			$child->_Parent = $item;
			$child->_Open = false;
			$arr[] = $child;

			if (isset($opens[$child->ID])) {
				$child->_Open = true;
			  $cArr = $this->getOpens($child, $opens[$child->ID]);  
			  $arr = array_merge($arr, $cArr);
			}
		}

		return $arr;
	}

	public function handleSave(GridField $gridField, DataObjectInterface $record) {
		//if (!$this->immediateUpdate) {
			$value = $gridField->Value();
			$sortedIDs = $this->getSortedIDs($value);
			if ($sortedIDs) {
				$this->executeReorder($gridField, $sortedIDs);
			}
		//}
	}

	public function handleAdd(GridField $gridField, SS_HTTPRequest $request) {
		$id = intval($request->postVar("id"));
		$type = $request->postVar("type");

		$obj = DataObject::get_by_id("PageElement", $id);
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
