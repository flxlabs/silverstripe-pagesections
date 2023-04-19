<?php

namespace FLXLabs\PageSections;

use SilverStripe\Control\Controller;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\FormAction;
use SilverStripe\Forms\FormField;
use SilverStripe\Forms\HiddenField;
use SilverStripe\ORM\DataObjectInterface;
use SilverStripe\ORM\PaginatedList;
use SilverStripe\View\ArrayData;
use SilverStripe\View\HTML;
use SilverStripe\View\Requirements;

/**
 * Class TreeView
 */
class TreeView extends FormField
{
    /**
     * @var string
     */
    protected $sortField = 'SortOrder';
    protected $parent = null;
    /**
     * @var \SilverStripe\ORM\Search\SearchContext
     */
    protected $context = null;
    protected $opens = null;

    private static $allowed_actions = array(
        'index',
        'tree',
        'move',
        'add',
        'remove',
        'delete',
        'search',
        'detail',
    );

    public function __construct($name, $title = null, $section = null, $readonly = false)
    {
        parent::__construct($name, $title, null);

        $this->section = $section;
        $this->readonly = $readonly;
        $this->context = singleton(PageElement::class)->getDefaultSearchContext();

        if ($section) {
            // Open default elements
            $this->opens = new \stdClass();
            foreach ($this->getItems() as $item) {
                $this->openRecursive($item);
            }
        }
    }

    public function performReadonlyTransformation()
    {
        return new TreeView($this->name, $this->title, $this->section, $this->readonly);
    }

    public function setValue($value, $data = null)
    {
        if (!$value) {
            return $this;
        }

        $this->section = $value;
        return $this;
    }

    /**
     * Saves this TreeView into the specified record
     *
     * We do nothing here, because the TreeView saves all changes while editing,
     * so there are no additional actions we have to perform here. We overwrite
     * this because the default behavior would write a NULL value into the relation.
     */
    public function saveInto(DataObjectInterface $record)
    {}

    /**
     * Recursively opens an item
     *
     * Recursively opens items if they have children and ->isOpenByDefault() returns true
     */
    private function openRecursive($item, $parents = [])
    {
        if ($item->isOpenByDefault() && $item->Children()->Count()) {
            $this->openItem(
                array_merge(
                    array_map(function ($e) {
                        return $e->ID;
                    }, $parents),
                    [$item->ID]
                )
            );
            foreach ($item->Children() as $child) {
                $this->openRecursive($child, array_merge($parents, [$item]));
            }
        }
    }

    /**
     * Extracts info from an incoming request
     * @param \SilverStripe\Control\HTTPRequest $request
     * @return array
     */
    private function pre($request)
    {
        $data = $request->requestVars();

        // Protection against CSRF attacks
        $token = $this->getForm()->getSecurityToken();
        if (!$token->checkRequest($request)) {
            $this->httpError(400, _t(
                "SilverStripe\\Forms\\Form.CSRF_FAILED_MESSAGE",
                "There seems to have been a technical problem. Please click the back button, " .
                "refresh your browser, and try again."
            ));
            return;
        }

        // Restore state from session
        $session = $request->getSession();
        if (isset($data["state"])) {
            $this->opens = $session->get($data["state"]);
        }

        return $data;
    }

    public function index($request)
    {
        $this->pre($request);
        return $this->FieldHolder();
    }

    /**
     * This action is called when opening or closing an element in the tree
     * @param \SilverStripe\Control\HTTPRequest $request
     * @return string
     */
    public function tree($request)
    {
        $data = $this->pre($request);
        if (!$data) {
            return $this->FieldHolder();
        }

        if (!isset($data["itemId"]) || !isset($data["parents"])) {
            Controller::curr()->getResponse()->setStatusCode(
                400,
                "Missing required data!"
            );
            return $this->FieldHolder();
        }

        $itemId = intval($data["itemId"]);
        $parents = array_values(array_filter(explode(",", $data["parents"]), 'strlen'));
        $path = array_merge($parents, [$itemId]);

        if ($this->isOpen($path)) {
            $this->closeItem($path);
        } else {
            $this->openItem($path);
        }

        return $this->FieldHolder();
    }

    /**
     * This action is called when an element in the tree is moved to another spot
     * @param \SilverStripe\Control\HTTPRequest $request
     * @return string
     */
    public function move($request)
    {
        $data = $this->pre($request);
        if (!$data) {
            return $this->FieldHolder();
        }

        if (
            !isset($data["itemId"]) || !isset($data["parents"]) ||
            !isset($data["newParent"]) || !isset($data["sort"])
        ) {
            Controller::curr()->getResponse()->setStatusCode(
                400,
                "Missing required data!"
            );
            return $this->FieldHolder();
        }

        $itemId = intval($data["itemId"]);
        $parents = array_values(array_filter(explode(",", $data["parents"]), 'strlen'));

        $item = PageElement::get()->byID($itemId);

        // Get the new parent
        $newParentId = $data["newParent"];
        if ($newParentId) {
            $newParent = PageElement::get()->byID($newParentId);
        } else {
            $newParent = $this->section;
        }

        // Check if this element is allowed as a child on the new element
        $allowed = in_array($item->ClassName, $newParent->getAllowedPageElements());
        if (!$allowed) {
            Controller::curr()->getResponse()->setStatusCode(
                400,
                "The type " . $item->ClassName . " is not allowed as a child of " . $newParent->ClassName
            );
            return $this->FieldHolder();
        }

        // Get the current parent
        if (count($parents) == 0) {
            $parent = $this->section;
        } else {
            $parent = PageElement::get()->byID($parents[count($parents) - 1]);
        }

        // Get requested sort order
        $sort = intval($data["sort"]);
        $sortBy = $this->getSortField();
        $sortArr = [$sortBy => $sort];

        // Check if we moved the element within the same parent
        if ($parent->ClassName === $newParent->ClassName && $parent->ID === $newParent->ID) {
            // Move the element around in the current parent
            if ($newParent->ClassName == PageSection::class) {
                $newParent->Elements()->Add($itemId, $sortArr);
            } else {
                $newParent->Children()->Add($itemId, $sortArr);
            }
        } else {
            // Remove the element from the current parent
            if (count($parents) == 0) {
                $parent = $this->section;
                $this->getItems()->removeByID($itemId);
            } else {
                $parent = PageElement::get()->byID($parents[count($parents) - 1]);
                $parent->Children()->removeByID($itemId);
            }

            // Add the element to the new parent
            if ($newParent->ClassName == PageSection::class) {
                $newParent->Elements()->Add($item, $sortArr);
            } else {
                $newParent->Children()->Add($item, $sortArr);
            }
        }

        return $this->FieldHolder();
    }

    /**
     * This action is called when adding a new or an existing item
     * @param \SilverStripe\Control\HTTPRequest $request
     * @return string
     */
    public function add($request)
    {
        $data = $this->pre($request);
        if (!$data) {
            return $this->FieldHolder();
        }

        if (!isset($data["type"]) && !isset($data["id"])) {
            Controller::curr()->getResponse()->setStatusCode(
                400,
                "Missing required data!"
            );
            return $this->FieldHolder();
        }

        // If we have an id then add an existing item...
        if (isset($data["id"])) {
            $existing = true;
            $child = PageElement::get()->byID($data["id"]);
            if (!$child) {
                Controller::curr()->getResponse()->setStatusCode(
                    400,
                    "Could not find PageElement with id " . $data['id']
                );
                return $this->FieldHolder();
            }
            $type = $child->ClassName;
        } else {
            // ...otherwise add a completely new item
            $existing = false;
            $type = $data["type"];

            $child = $type::create();
        }

        $itemId = isset($data["itemId"]) ? intval($data["itemId"]) : null;
        $sort = isset($data["sort"]) ? intval($data["sort"]) : 0;
        $sortBy = $this->getSortField();
        $sortArr = [$sortBy => $sort];

        // If we have an itemId then we're adding to another element
        // otherwise we're adding to the root
        if ($itemId) {
            $parents = array_values(array_filter(explode(",", $data["parents"]), 'strlen'));
            $path = array_merge($parents, [$itemId]);

            $item = PageElement::get()->byID($itemId);
            if (!in_array($type, $item->getAllowedPageElements())) {
                Controller::curr()->getResponse()->setStatusCode(
                    400,
                    "The type " . $type . " is not allowed as a child of " . $item->ClassName
                );
                return $this->FieldHolder();
            }

            if (!$existing) {
                $child->write();
            }
            $item->Children()->Add($child, $sortArr);
            $item->write();

            // Make sure we can see the child (and its children)
            if ($existing) {
                $this->openRecursive($child, [$item]);
            } else {
                $this->openItem(array_merge($path, [$item->ID]));
            }
        } else {
            if ($existing) {
                $this->openRecursive($child);
            } else {
                $child->write();
            }
            $this->getItems()->Add($child, $sortArr);
        }

        return $this->FieldHolder();
    }

    /**
     * Action called when an item is removed from the TreeView
     * @param \SilverStripe\Control\HTTPRequest $request
     * @return string
     */
    public function remove($request)
    {
        $data = $this->pre($request);
        if (!$data) {
            return $this->FieldHolder();
        }

        if (!isset($data["itemId"]) || !isset($data["parents"])) {
            Controller::curr()->getResponse()->setStatusCode(
                400,
                "Missing required data!"
            );
            return $this->FieldHolder();
        }

        $itemId = intval($data["itemId"]);
        $parents = array_values(array_filter(explode(",", $data["parents"]), 'strlen'));

        // We only need the parent directly above the child, all parents further up don't matter,
        // because the relations are duplicated if the item is duplicated.
        // If we have no parents then we're removing it from the root
        if (count($parents) == 0) {
            $this->getItems()->removeByID($itemId);
        } else {
            $parent = PageElement::get()->byID($parents[count($parents) - 1]);
            $parent->Children()->removeByID($itemId);
        }

        return $this->FieldHolder();
    }

    /**
     * This action is called when an element is deleted
     * @param \SilverStripe\Control\HTTPRequest $request
     * @return string
     */
    public function delete($request)
    {
        $data = $this->pre($request);
        if (!$data) {
            return $this->FieldHolder();
        }

        if (!isset($data["itemId"]) || !isset($data["parents"])) {
            Controller::curr()->getResponse()->setStatusCode(
                400,
                "Missing required data!"
            );
            return $this->FieldHolder();
        }

        $itemId = intval($data["itemId"]);
        $parents = array_values(array_filter(explode(",", $data["parents"]), 'strlen'));
        $path = array_merge($parents, [$itemId]);

        $item = PageElement::get()->byID($itemId);

        // Close the element in case it's open to avoid errors
        $this->closeItem($path);

        // let's remove all relations
        $this->getItems()->removeByID($itemId);
        foreach ($parents as $parentId) {
            $parent = PageElement::get()->byID($parentId);
            if ($parent) {
                $parent->Children()->removeByID($itemId);
            }

        }

        // Delete the element
        $item->doArchive();

        return $this->FieldHolder();
    }

    protected function getTarget($itemId)
    {
        $target = $this->section;
        if ($itemId && $targetElement = PageElement::get()->byID($itemId)) {
            $target = $targetElement;
        }
        return $target;
    }

    /**
     * This action is called when the find existing dialog is shown.
     * @param \SilverStripe\Control\HTTPRequest $request
     * @return string
     */
    public function search($request)
    {
        $parents = $request->getVar('parents') ?? [];
        $itemId = $request->getVar('itemId');
        $sort = $request->getVar('sort') ?? 9999;
        $target = $this->getTarget($itemId);

        $fields = $this->context->getFields();
        if ($classField = $fields->fieldByName('ClassName')) {
            $allowed = $target->getAllowedPageElements();
            $classField->setSource(array_combine($allowed, $allowed));
        }

        $fields->push(HiddenField::create('parents', 'parents', $parents));
        $fields->push(HiddenField::create('itemId', 'itemId', $itemId));
        $fields->push(HiddenField::create('sort', 'sort', $sort));

        $form = Form::create(
            $this,
            'search',
            $this->context->getFields(),
            FieldList::create(
                FormAction::create('doSearch', _t('GridFieldExtensions.SEARCH', 'Search'))
                    ->setUseButtonTag(true)
                    ->addExtraClass('btn btn-primary font-icon-search')
            )
        );
        $form->addExtraClass('stacked add-existing-search-form form--no-dividers');
        $form->setFormMethod('GET');

        // Check if we're requesting the form for the first time (we return the template)
        // or if this is a submission (we return the form, so it calls the submitted action)
        $extraRequestVars = array_filter($request->requestVars(), function ($name) {
            return !in_array($name, ['parents', 'sort', 'itemId']);
        }, ARRAY_FILTER_USE_KEY);

        if (count($extraRequestVars) === 0) {
            return $form->forAjaxTemplate();
        }
        return $form;
    }

    /**
     * This action is called when a search is performed in the find existing dialog
     * @param \SilverStripe\Control\HTTPRequest $request
     * @return string
     */
    public function doSearch($data, $form)
    {
        $list = $this->context->getQuery([
            'ClassName' => $data['ClassName'],
            'Name' => $data['Name'],
        ], false, false);
        $allowed = $this->getTarget($data['itemId'])->getAllowedPageElements();
        // Remove all disallowed classes
        $list = $list->filter(["ClassName" => $allowed, "Name:not" => [null, '']]);
        $sql = $list->dataQuery()->getFinalisedQuery()->sql($parameters);

        $list = new PaginatedList($list, $data);
        $data = $this->customise([
            'SearchForm' => $form,
            'Items' => $list,
            'AddArguments' => [
                'Parents' => $data['parents'],
                'ItemID' => $data['itemId'],
                'Sort' => $data['sort'],
            ],
        ]);
        return $data->renderWith("FLXLabs\PageSections\TreeViewFindExistingForm");
    }

    /**
     * Creates a detail edit form for the specified item
     * @param \FLXLabs\PageSections\PageElement $item
     * @param bool $loadData True if the data from $item should be loaded into the form, false otherwise.
     * @return \SilverStripe\Forms\Form
     */
    public function DetailForm(PageElement $item, bool $loadData = true)
    {
        $canEdit = $item->canEdit();
        $canDelete = $item->canDelete();

        $actions = new FieldList();
        if ($canEdit) {
            $actions->push(FormAction::create('doSave', _t('SilverStripe\\Forms\\GridField\\GridFieldDetailForm.Save', 'Save'))
                    ->setUseButtonTag(true)
                    ->addExtraClass('btn-primary font-icon-save'));
        }
        if ($canDelete) {
            $actions->push(FormAction::create('doDelete', _t('SilverStripe\\Forms\\GridField\\GridFieldDetailForm.Delete', 'Delete'))
                    ->setUseButtonTag(true)
                    ->addExtraClass('btn-outline-danger btn-hide-outline font-icon-trash-bin action-delete'));
        }

        $fields = $item->getCMSFields();
        $fields->addFieldToTab("Root.Main", HiddenField::create("ID", "ID", $item->ID));

        $form = Form::create(
            $this,
            'detail',
            $fields,
            $actions
        );
        if ($loadData) {
            $form->loadDataFrom($item, Form::MERGE_DEFAULT);
        }

        $form->setTemplate([
            'type' => 'Includes',
            'SilverStripe\\Admin\\LeftAndMain_EditForm',
        ]);
        $form->addExtraClass(
            'view-detail-form cms-content cms-edit-form center fill-height flexbox-area-grow'
        );
        if ($form->Fields()->hasTabSet()) {
            $form->Fields()->findOrMakeTab('Root')->setTemplate('SilverStripe\\Forms\\CMSTabSet');
            $form->addExtraClass('cms-tabset');
        }

        return $form;
    }

    /**
     * This action is called when the detail form for an item is opened.
     * @param \SilverStripe\Control\HTTPRequest $request
     * @return string
     */
    public function detail($request)
    {
        $id = intval($request->requestVar("ID"));
        if ($id) {
            $request->getSession()->set("ElementID", $id);
        } else {
            $id = $request->getSession()->get("ElementID");
        }

        // This is a request to show the form so we return it as a template so
        // that SilverStripe doesn't think this is already a submission
        // (it would call the first action on the form)
        if ($request->isGET()) {
            $item = PageElement::get()->byID($id);
            if (!$item) {
                return $this->httpError(404);
            }
            $form = $this->DetailForm($item);
            // Save the id of the page element on this form's security token
            //$request->getSession()->set("_tv_df_" . $form->getSecurityToken()->getValue(), $id);
            return $form->forTemplate();
        }

        // If it's a POST request then it's a submission and we have to get the ID
        // from the session using the form's security token.
        //$id = $request->getSession()->get("_tv_df_" . $request->requestVar("SecurityID"));
        $item = PageElement::get()->byID($id);
        return $this->DetailForm($item, false);
    }

    public function handleRequest(HTTPRequest $request)
    {
        $this->setRequest($request);

        // Forward requests to the elements in the detail form to their respective controller
        if ($request->match('detail/$ID!')) {
            $id = $request->getSession()->get("ElementID");
            $item = PageElement::get()->byID($id);
            $form = $this->DetailForm($item);
            $request->shift(1);
            return $form->getRequestHandler()->handleRequest($request);
        }

        return parent::handleRequest($request);
    }

    /**
     * This action is called when the detail form is submitted (saved/deleted)
     * @param \SilverStripe\Control\HTTPRequest $request
     * @return string
     */
    public function doSave($data, $form)
    {
        $id = intval($data["ID"]);
        $item = PageElement::get()->byID($id);

        $form->saveInto($item);
        $item->write();
    }

    /**
     * Get base items
     *
     * Gets all the top level items of this TreeView.
     * @return \SilverStripe\ORM\ArrayList
     */
    public function getItems()
    {
        return $this->section->Elements();
    }

    /**
     * Gets the sort field
     *
     * Gets the name of the field by which items are sorted.
     * @return string
     */
    public function getSortField()
    {
        return $this->sortField;
    }

    /**
     * Renders this TreeView as an HTML tag
     * @param array $properties The additional properties for the TreeView
     * @return string
     */
    public function FieldHolder($properties = array())
    {
        Requirements::css("flxlabs/silverstripe-pagesections:client/css/TreeView.css");
        Requirements::javascript("flxlabs/silverstripe-pagesections:client/javascript/TreeView.js");
        Requirements::add_i18n_javascript('flxlabs/silverstripe-pagesections:client/javascript/lang', false, true);

        // Ensure $id doesn't contain only numeric characters
        $sessionId = 'ps_tv_' . substr(md5(serialize($this->opens)), 0, 8);
        $session = Controller::curr()->getRequest()->getSession();
        $session->set($sessionId, $this->opens);

        $content = '<div class="treeview-pagesections__header">';

        $classes = $this->section->getAllowedPageElements();
        $elems = [];
        foreach ($classes as $class) {
            $elems[$class] = singleton($class)->singular_name();
        }

        if (!$this->readonly) {
            // Create the add new button at the very top
            $addButton = TreeViewFormAction::create(
                $this,
                "AddActionBase",
                null,
                null,
                null
            );
            $addButton->setAttribute("data-allowed-elements", json_encode($elems, JSON_UNESCAPED_UNICODE));
            $addButton->addExtraClass("btn add-button font-icon-plus");
            if (!count($elems)) {
                $addButton->setDisabled(true);
            }
            $addButton->setButtonContent(' ');
            $content .= ArrayData::create([
                "Button" => $addButton,
            ])->renderWith("\FLXLabs\PageSections\TreeViewAddNewButton");
        }

        $content .= "</div>";

        $list = $this->getItems()->sort($this->sortField)->toArray();

        $first = true;
        foreach ($list as $item) {
            $content .= $this->renderTree($item, [], $this->opens, $first);
            $first = false;
        }

        return HTML::createTag(
            'fieldset',
            [
                'class' => 'treeview-pagesections pagesection-' . $this->getName(),
                'data-readonly' => $this->readonly,
                'data-name' => $this->getName(),
                'data-url' => !$this->readonly ? $this->Link() : null,
                'data-state-id' => $sessionId,
                'data-allowed-elements' => json_encode($elems),
            ],
            $content
        );
    }

    public function Field($properties = array())
    {
        return $this->FieldHolder($properties);
    }

    /**
     * Renders an item tree
     *
     * Renders the specified item and it's children
     * @param PageElement $item The item to render
     * @param string[] $parents The hierarchy of parents this item is a child of
     * @param \Stdclass $opens The local open state for the item
     * @param boolean $isFirst True if this is the first item of the direct parent, false otherwise
     */
    private function renderTree($item, $parents, $opens, $isFirst)
    {
        $childContent = null;
        $level = count($parents) + 1;
        $isOpen = isset($opens->{$item->ID}) && $item->Children()->Count() > 0;

        // Render children if we are open
        if ($isOpen) {
            $children = $item->Children()->Sort($this->sortField);
            $first = true;
            foreach ($children as $child) {
                $childContent .= $this->renderTree(
                    $child,
                    array_merge($parents, [$item]),
                    $opens->{$item->ID},
                    $first
                );
                $first = false;
            }
        }

        // Get the list of parents of this element as an array of ids
        // (already converted to json/a string)
        $tree = "[" .
        implode(
            ',',
            array_map(
                function ($item) {
                    return $item->ID;
                },
                $parents
            )
        ) .
            "]";

        // Construct the array of all allowed child elements
        $classes = $item->getAllowedPageElements();
        $elems = [];
        foreach ($classes as $class) {
            $elems[$class] = singleton($class)->singular_name();
        }

        // Construct the array of all allowed child elements in parent slot
        $parentClasses = count($parents) > 0
        ? $parents[count($parents) - 1]->getAllowedPageElements()
        : $this->section->getAllowedPageElements();
        $parentElems = [];
        foreach ($parentClasses as $class) {
            $parentElems[$class] = singleton($class)->singular_name();
        }

        // Find out if this item is allowed as a root item
        // There are two cases, either this GridField is on a page,
        // or it is on a PageElement and we're looking at the children
        $isAllowedRoot = in_array($item->ClassName, $parentClasses);

        // Create a button to add a new child element
        // and save the allowed child classes on the button
        if (!$this->readonly && count($classes)) {
            $addButton = TreeViewFormAction::create(
                $this,
                "AddAction" . $item->ID,
                null,
                null,
                null
            );
            $addButton->setAttribute("data-allowed-elements", json_encode($elems, JSON_UNESCAPED_UNICODE));
            $addButton->addExtraClass("btn add-button font-icon-plus");
            if (!count($elems)) {
                $addButton->setDisabled(true);
            }
            $addButton->setButtonContent(' ');
        }

        if (!$this->readonly) {
            // Create a button to add an element after
            // and save the allowed child classes on the button
            $addAfterButton = TreeViewFormAction::create(
                $this,
                "AddAfterAction" . $item->ID,
                null,
                null,
                null
            );
            $addAfterButton->setAttribute(
                "data-allowed-elements",
                json_encode($parentElems, JSON_UNESCAPED_UNICODE)
            );
            $addAfterButton->addExtraClass("btn add-after-button font-icon-plus");
            if (!count($parentElems)) {
                $addAfterButton->setDisabled(true);
            }
            $addAfterButton->setButtonContent(' ');

            // Create a button to delete and/or remove the element from the parent
            $deleteButton = TreeViewFormAction::create(
                $this,
                "DeleteAction" . $item->ID,
                null,
                null,
                null
            );
            $deleteButton->setAttribute(
                "data-used-count",
                $item->getAllUses()->Count()
            );
            $deleteButton->addExtraClass("btn delete-button font-icon-trash-bin");
            $deleteButton->setButtonContent('Delete');

            // Create a button to edit the record
            $editButton = TreeViewFormAction::create(
                $this,
                "EditAction" . $item->ID,
                null,
                null,
                null
            );
            $editButton->addExtraClass("btn edit-button font-icon-edit");
            $editButton->setButtonContent('Edit');
        }

        // Create the tree icon
        $icon = '';
        if (!$this->readonly && $item->Children() && $item->Children()->Count() > 0) {
            $icon = ($isOpen === true ? 'font-icon-down-open' : 'font-icon-right-open');
        }

        // Create the tree field
        $treeButton = TreeViewFormAction::create(
            $this,
            "TreeNavAction" . $item->ID,
            null,
            "dotreenav",
            ["element" => $item]
        );
        $treeButton->addExtraClass("tree-button treeview-item__treeswitch__button btn " . ($isOpen ? "is-open" : "is-closed"));
        if (!$item->Children()->Count()) {
            $treeButton->addExtraClass(" is-end");
            $treeButton->setDisabled(true);
        }
        $treeButton->addExtraClass($icon);
        $treeButton->setButtonContent(' ');

        return ArrayData::create([
            "Readonly" => $this->readonly,
            "Item" => $item,
            "Tree" => $tree,
            "IsOpen" => $isOpen,
            "IsFirst" => $isFirst,
            "Children" => $childContent,
            "AllowedRoot" => $isAllowedRoot,
            "AllowedElements" => json_encode($elems, JSON_UNESCAPED_UNICODE),
            "TreeButton" => $treeButton,
            "AddButton" => isset($addButton) ? $addButton : null,
            "AddAfterButton" => isset($addAfterButton) ? $addAfterButton : null,
            "EditButton" => isset($editButton) ? $editButton : null,
            "DeleteButton" => isset($deleteButton) ? $deleteButton : null,
            "UsedCount" => $item->getAllUses()->Count(),
        ])->renderWith("\FLXLabs\PageSections\TreeViewPageElement");
    }

    /**
     * Checks if the specified item is open
     *
     * @param string[] $path The hierarchy of item ids, the last being the item to check.
     * @return boolean
     */
    private function isOpen($path)
    {
        $opens = $this->opens;
        foreach ($path as $itemId) {
            if (!isset($opens->{$itemId})) {
                return false;
            }

            $opens = $opens->{$itemId};
        }

        return true;
    }

    /**
     * Opens an item
     *
     * Opens the item at the specified path
     * @param string[] $path The hierarchy of item ids, the last being the item to open.
     */
    private function openItem($path)
    {
        $opens = $this->opens;
        foreach ($path as $itemId) {
            if (!isset($opens->{$itemId})) {
                $opens->{$itemId} = new \stdClass();
            }

            $opens = $opens->{$itemId};
        }
    }

    /**
     * Closes an item
     *
     * Closes the item at the specified path
     * @param string[] $path The hierarchy of item ids, the last being the item to close.
     */
    private function closeItem($path)
    {
        $opens = $this->opens;
        for ($i = 0; $i < count($path) - 1; $i++) {
            if (!isset($opens->{$path[$i]})) {
                return;
            }

            $opens = $opens->{$path[$i]};
        }

        unset($opens->{$path[count($path) - 1]});
    }
}

class TreeView_Readonly extends TreeView
{}
