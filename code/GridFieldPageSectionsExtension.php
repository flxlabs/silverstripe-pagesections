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
    }

    public function getColumnMetadata($gridField, $columnName) {
        return array("title" => "");
    }

    public function getColumnsHandled($gridField) {
        return array(
            "Reorder",
            "TreeNav"
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
            $state = $gridField->getState(true);

            $id = $record->ID;
            $open = isset($state->open->$id);
            $level = $record->_Level;
            $field = null;

            if ($record->Children() && $record->Children()->Count() > 0) {
                $icon = ($open === true ? '<span class="is-open">▼</span>' : '<span class="is-closed">▶</span>');
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
                $this->openElement($state, $elem, $level);
            }
        }
    }

    private function isOpen($state, $element) {
        $id = $element->ID;
        return isset($state->open->$id);
    }
    private function getLevel($state, $element) {
        if ($this->isOpen($state, $element)) {
            return $state->open->{$element->ID}->level;
        }
        $parents = $element->Parents();
        foreach ($parents as $parent) {
            if ($this->isOpen($state, $parent)) {
                return $state->open->{$parent->ID}->level + 1;
            }
        }
        return 0;
    }
    private function openElement($state, $element) {
        $id = $element->ID;
        $lvl = $this->getLevel($state, $element);
        $state->open->$id = array("level" => $lvl);
    }
    private function closeElement($state, $element) {
        $id = $element->ID;
        foreach ($element->Children() as $child) {
            $this->closeElement($state, $child);
        }
        unset($state->open->$id);
    }

    public function getManipulatedData(GridField $gridField, SS_List $dataList) {
        $state = $gridField->getState(true);
        $opens = $state->open->toArray();
        $list = $dataList->toArray();
        $sort = $this->getSortField();
        $groupId = 0;

        // Create groups for coloring
        foreach ($list as $item) {
            $item->_GroupId = $groupId % 2;
            $item->_Level = 0;
            $groupId++;
        }

        // Add child elements for every open element
        foreach ($opens as $id => $value) {
            $obj = DataObject::get_by_id("PageElement", $id);

            // Get index of the current element in the list
            $base = current(array_filter($list, function($item) use ($id) { return $item->ID == $id; }));
            $index = array_search($base, $list);

            // Get all children and insert them into the list
            $children = $obj->Children()->Sort($sort);
            for ($i = 0; $i < $children->count(); $i++) {
                $child = $children[$i];
                $child->_Level = $value["level"] + 1;
                $child->_GroupId = $base->_GroupId;
                array_splice($list, $index + 1 + $i, 0, array($child));
            }
        }

        return new ArrayList($list);
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

        // Close the element in case it's open to avoid errors
        $state = $gridField->getState(true);
        if ($this->isOpen($state, $obj)) {
            $this->closeElement($obj);
        }

        // Delete the element
        $obj->delete();

        return $gridField->FieldHolder();
    }  
}
