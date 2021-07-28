<?php

namespace Ecode\CRUDBundle\Event\Object;

/**
 * The object.changed event is dispatched each time an object is changed
 * by any user.
 */
class ObjectChangedEvent extends ObjectEvent
{
    public const NAME = 'object.changed';
}
