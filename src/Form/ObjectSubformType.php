<?php

namespace Ecode\CRUDBundle\Form;

use Ecode\CRUDBundle\Form\ObjectType;
use Symfony\Component\Form\DataMapperInterface;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Ecode\CRUDBundle\Service\ObjectManager;
use Ecode\CRUDBundle\Service\FormTypeManager;

class ObjectSubformType extends ObjectType implements DataMapperInterface
{
    public function __construct(
        PropertyAccessorInterface $accessor,
        EntityManagerInterface $em,
        ObjectManager $om,
        ParameterBagInterface $params,
        FormTypeManager $formTypeManager
    ) {
        parent::__construct($accessor, $em, $om, $params, $formTypeManager);
    }

    public function mapDataToForms($data, $forms) {
        parent::mapDataToForms($data, $forms);
    }

    public function mapFormsToData($forms, &$data) {
        parent::mapFormsToData($forms, $data);
    }

    public function buildForm(FormBuilderInterface $builder, array $options) {
        $builder->setDataMapper($this);
        parent::buildForm($builder, $options);
    }

    public function buildView(FormView $view, FormInterface $form, array $options) {
        parent::buildView($view, $form, $options);
    }

    public function configureOptions(OptionsResolver $resolver) {
        parent::configureOptions($resolver);
        $resolver->setDefaults([
        ]);
    }
}
