<?php

namespace Ecode\CRUDBundle\Filter\Object;

use Symfony\Component\Form\FormBuilderInterface;

/**
 * form type for additional filter
 */
class ObjectFilterAddType extends ObjectFilterBase
{
    protected $section = 'add';

    public function buildForm(FormBuilderInterface $builder, array $options) {
        parent::buildForm($builder, $options);
    }
}
