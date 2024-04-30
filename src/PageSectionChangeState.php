<?php

namespace FLXLabs\PageSections;

use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Injector\Injector;

/**
 * Utility class for storing PageSections specific state.
 * @package FLXLabs\PageSections
 */
class PageSectionChangeState
{
    use Configurable;

    private $propagateWrites = true;

    public function setPropagateWrites(bool $value)
    {
        $this->propagateWrites = $value;
    }

    public function getPropagateWrites()
    {
        if ($this->config()->get('always_propagate_writes')) {
            return true;
        }

        return $this->propagateWrites;
    }

    public static function inst()
    {
        /** @var PageSectionChangeState */
        $inst = Injector::inst()->get(PageSectionChangeState::class);
        return $inst;
    }

    /**
     * @param bool|null $value 
     */
    public static function propagateWrites($value = null)
    {
        $inst = static::inst();
        if ($value !== null) {
            $inst->setPropagateWrites((bool) $value);
        }
        return $inst->getPropagateWrites();
    }
}
