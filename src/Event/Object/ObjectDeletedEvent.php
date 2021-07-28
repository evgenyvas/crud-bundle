<?php

namespace Ecode\CRUDBundle\Event\Object;

/**
 * The object.deleted event is dispatched each time an object is deleted
 * by any user.
 */
class ObjectDeletedEvent extends ObjectEvent
{
    public const NAME = 'object.deleted';
}
