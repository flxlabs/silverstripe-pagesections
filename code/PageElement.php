<?php
class PageElement extends DataObject {
    public static $singular_name = 'Element';
	public static $plural_name = 'Elements';
    public function getSingularName() { return static::$singular_name; }
    public function getPluralName() { return static::$plural_name; }

    function canView($member = null) { return true; }
    function canEdit($member = null) { return true; }
    function canDelete($member = null) { return true; }
    function canCreate($member = null) { return true; }

    private static $can_be_root = true;

    private static $db = array(
        'Title' => 'Varchar(255)',
    );

    private static $versioned_many_many = array(
        'Children' => 'PageElement',
    );

	private static $versioned_belongs_many_many = array(
        'Parents' => 'PageElement',
        'Pages' => 'Page',
    );

	private static $many_many_extraFields = array(
        'Children' => array(
            'SortOrder' => 'Int',
        ),
	);

    public static $summary_fields = array(
        'SingularName',
        'ID',
        'GridFieldPreview',
    );

    public static $searchable_fields = array(
        'ClassName',
        'Title',
        'ID'
    );

    public static function getAllowedPageElements() {
        $classes = array_values(ClassInfo::subclassesFor('PageElement'));
        // remove
        $classes = array_diff($classes, ["PageElement"]);
        return $classes;
    }

    public function getChildrenGridField() {
        $comp = new GridFieldAddNewMultiClass();
        $comp->setClasses($this->getAllowedPageElements());
        $autoCompl = new GridFieldAddExistingAutocompleter('buttons-before-right');
        $autoCompl->setResultsFormat('$Title ($ID)');
        $autoCompl->setSearchList(PageElement::get()->exclude("ID", $this->getParentIDs()));
        $f = new GridField(
            'Children',
            'Children',
            $this->Children(),
            GridFieldConfig::create()
            ->addComponent(new GridFieldButtonRow('before'))
            ->addComponent($autoCompl)
            ->addComponent($comp)
            ->addComponent(new GridFieldToolbarHeader())
            ->addComponent(new GridFieldDataColumns())
            //->addComponent(new GridFieldOrderableRows('SortOrder'))
            ->addComponent(new GridFieldEditButton())
            ->addComponent(new GridFieldDeleteAction(true))
            ->addComponent(new GridFieldDeleteAction(false))
            //->addComponent(new GridFieldPageSectionsExtension())
            ->addComponent(new GridFieldDetailForm())
            ->addComponent($pagination = new GridFieldPaginator(PHP_INT_MAX))
        );
        return $f;
    }

    public function getGridFieldPreview() {
        return null;
    }

    public function getCMSFields() {
        $fields = parent::getCMSFields();
        $fields->removeByName('Pages');
        $fields->removeByName('Parents');

        $fields->removeByName("Children");
        if ($this->ID && count(static::getAllowedPageElements())) {
            $fields->addFieldToTab('Root.PageSections', $this->getChildrenGridField());
        }

        return $fields;
    }

    public function getParentIDs() {
        $IDArr = array($this->ID);
        foreach($this->Parents() as $parent) {
            $IDArr = array_merge($IDArr, $parent->getParentIDs());
        }
        return $IDArr;
    }

    public function renderHtml() {
        Config::inst()->update('SSViewer', 'theme_enabled', true);
        $ret = $this->renderWith(array_reverse($this->getClassAncestry()));
        foreach ($this->Children() as $child) {
            $ret .= $child->renderHtml();
        }
        return $ret;
    }

    public function getBetterButtonsActions() {
        $fieldList = FieldList::create(array(
            BetterButton_SaveAndClose::create(),
            BetterButton_Save::create(),
        ));
        return $fieldList;
    }
}