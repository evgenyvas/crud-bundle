<?php

namespace Ecode\CRUDBundle\Event\Object;

/**
 * The object.added event is dispatched each time an object is created
 * by any user.
 */
class ObjectAddedEvent extends ObjectEvent
{
    public const NAME = 'object.added';
}
