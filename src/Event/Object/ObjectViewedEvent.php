<?php

namespace Ecode\CRUDBundle\Event\Object;

/**
 * The object.viewed event is dispatched each time an object is viewed
 * by any user.
 */
class ObjectViewedEvent extends ObjectEvent
{
    public const NAME = 'object.viewed';
}
