<?php

namespace Ecode\CRUDBundle\Form\Type;

use Ecode\CRUDBundle\Form\ObjectType;

class SubformSingleObjectAddType extends ObjectType
{
    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix() {
        return 'subformsingleadd';
    }
}
