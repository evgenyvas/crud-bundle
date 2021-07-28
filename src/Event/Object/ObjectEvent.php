<?php

namespace Ecode\CRUDBundle\Event\Object;

use Symfony\Contracts\EventDispatcher\Event;

/**
 * base class for all object events
 */
abstract class ObjectEvent extends Event
{
    // entity object
    protected $object;

    public function __construct(?object $object = null) {
        $this->object = $object;
    }

    public function getObject() {
        return $this->object;
    }
}
