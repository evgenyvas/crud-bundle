<?php

namespace Ecode\CRUDBundle\Filter\Object;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

/**
 * form type for objects filter
 */
class ObjectType extends AbstractType
{
    private $params;
    protected $att_settings;

    private $fields;

    public function __construct(
        ParameterBagInterface $params
    ) {
        $this->params = $params;
    }

    public function buildForm(FormBuilderInterface $builder, array $options) {
        $this->att_settings = $options['att_settings'];

        $section_opt = [
            'att_settings' => $this->att_settings,
        ];
        if ($options['render_search']) {
            $builder->add('search', ObjectSearchType::class);
        }
        if ($options['render_main']) {
            $builder->add('filtermain', ObjectFilterMainType::class, $section_opt);
        }
        if ($options['render_add']) {
            $builder->add('filteradd', ObjectFilterAddType::class, $section_opt);
        }
    }

    public function buildView(FormView $view, FormInterface $form, array $options) {
        $this->getAllIds($form, $form->getName(), $this->getBlockPrefix());
        $view->vars['fields'] = $this->fields;
        $view->vars['date_format'] = $options['date_format'];
        $view->vars['datetime_format'] = $options['datetime_format'];
        $view->vars['filter_is_show'] = $options['filter_is_show'];
    }

    public function getAllIds(FormInterface $form, $parent_name, $parent_prefix) {
        foreach ($form as $ch) {
            $name = $parent_name.'_'.$ch->getName();
            $prefix = $ch->getConfig()->getType()->getInnerType()->getBlockPrefix();
            $has_children = (bool)$ch->count();
            if (!$has_children) {
                $this->fields[] = [
                    'id'=>$name,
                    'tp'=>$prefix,
                    'par'=>$parent_prefix,
                ];
            }
            if ($has_children and ($ch instanceof FormInterface)) {
                $this->getAllIds($ch, $name, $prefix);
            }
        }
    }

    public function configureOptions(OptionsResolver $resolver) {
        $resolver->setDefaults([
            'required' => false,
            'att_settings' => [],
            'render_add' => true,
            'render_main' => true,
            'render_search' => true,
            'date_format' => $this->params->get('flatpickr_date_format'),
            'datetime_format' => $this->params->get('flatpickr_datetime_format'),
            'filter_is_show' => false,
        ]);
    }

    public function getBlockPrefix() {
        return 'objectfilter';
    }
}
