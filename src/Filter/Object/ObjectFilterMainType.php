<?php

namespace Ecode\CRUDBundle\Filter\Object;

use Symfony\Component\Form\FormBuilderInterface;

/**
 * form type for main filter section
 */
class ObjectFilterMainType extends ObjectFilterBase
{
    protected $section = 'main';

    public function buildForm(FormBuilderInterface $builder, array $options) {
        parent::buildForm($builder, $options);
    }
}
