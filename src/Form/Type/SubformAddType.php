<?php

namespace Ecode\CRUDBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormEvent;

class SubformAddType extends AbstractType
{
    /**
     * {@inheritdoc}
     */
    public function buildView(FormView $view, FormInterface $form, array $options) {
        $max_index = 0;
        $formData = $form->getData();
        foreach ($formData as $k=>$v) {
            if ($k > $max_index) {
                $max_index = $k;
            }
        }
        if ($max_index>0) $max_index++;
        if ($max_index < count($formData)) {
            $max_index = count($formData);
        }
        $view->vars['labels'] = $options['labels'];
        $view->vars['max_index'] = $max_index;
        $view->vars['hide_empty_table'] = $options['hide_empty_table'];
        $view->vars['add_button_title'] = $options['add_button_title'];
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver) {
        $resolver->setDefaults([
            'labels' => '',
            'hide_empty_table' => false,
            'add_button_title' => '',
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix() {
        return 'subformadd';
    }

    /**
     * {@inheritdoc}
     */
    public function getParent() {
        return Type\CollectionType::class;
    }
}
