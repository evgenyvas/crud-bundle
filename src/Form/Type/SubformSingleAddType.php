<?php

namespace Ecode\CRUDBundle\Form\Type;

use Symfony\Component\Form\AbstractType;

class SubformSingleAddType extends AbstractType
{
    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix() {
        return 'subformsingleadd';
    }
}
