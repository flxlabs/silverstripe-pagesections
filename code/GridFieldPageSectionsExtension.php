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

        $field->addExtraClass("ss-gridfield-pagesections");
        $field->setAttribute("data-url-reorder", $field->Link("reorder"));
        $field->setAttribute("data-url-movetopage", $field->Link("movetopage"));

        return array(
            "header" => "<ul id='treenav-menu'></ul>"
        );
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
            return array(
                "class" => "col-treenav",
                "data-class" => $record->ClassName,
                "data-level" => isset($record->Level) ? $record->Level : "0",
                "data-allowed-elements" => implode(",", $record->getAllowedPageElements()),
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
            $level = isset($record->Level) ? $record->Level : 0;
            $field = null;

            if ($record->Children() && $record->Children()->Count() > 0) {
                $icon = ($open === true ? "- " : "+ ");

                $field = GridField_FormAction::create($gridField, "TreeNavAction".$record->ID, null, 
                    "dotreenav", array("element" => $record, "level" => $level));
                $field->addExtraClass("level".$level . ($open ? " is-open" : " is-closed"));
                $field->setButtonContent("<span>" . $icon . "</span><span>" . $record->Title . "</span>");
            } else {
                $field = GridField_FormAction::create($gridField, "TreeNavAction".$record->ID, null, 
                    "dotreenav", array("element" => $record, "level" => $level));
                $field->addExtraClass("level".$level . ($open ? " is-open" : " is-closed"));
                $field->setButtonContent("<span>&#8226;</span><span>" . $record->Title . "</span>");
            }

            $field->setForm($gridField->getForm());

            return ViewableData::create()->customise(array(
                "ButtonField" => $field,
            ))->renderWith("GridFieldPageElement");
        }
    }

    public function getActions($gridField) {
        return array("dotreenav");
    }

    public function handleAction(GridField $gridField, $actionName, $arguments, $data) {
        if ($actionName == "dotreenav") {
            $elem = $arguments["element"];
            $level = $arguments["level"];
            $id = $elem->ID;

            if ($elem->Children()->Count() == 0) {
                Controller::curr()->getResponse()->setStatusCode(
                    200,
                    "This element has no children"
                );
                return;
            }

            $state = $gridField->getState(true);

            if (isset($state->open->$id)) {
                $this->closeElement($elem, $state);
            } else {
                $state->open->$id = array("class" => $elem->ClassName, "level" => $level + 1);
            }
        }
    }

    private function closeElement($element, $state) {
        $id = $element->ID;
        foreach ($element->Children() as $child) {
            $this->closeElement($child, $state);
        }
        unset($state->open->$id);
    }

    public function getManipulatedData(GridField $gridField, SS_List $dataList) {
        $state = $gridField->getState(true);
        $opens = $state->open->toArray();
        $list = $dataList->toArray();
        $sort = $this->getSortField();

        // Add child elements for every open element
        foreach ($opens as $id => $value) {
            $obj = DataObject::get_by_id($value["class"], $id);

            // Get index of the current element in the list
            $base = current(array_filter($list, function($item) use ($id) { return $item->ID == $id; }));
            $index = array_search($base, $list);

            // Get all children and insert them into the list
            $children = $obj->Children()->Sort($sort);
            for ($i = 0; $i < $children->count(); $i++) {
                $child = $children[$i];
                $child->Level = $value["level"];
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
}
