<?php

namespace Ecode\CRUDBundle\Filter\Type;

use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type;
use Ecode\CRUDBundle\Form\Type\MultiSelectAutocompleteType;
use Symfony\Contracts\Translation\TranslatorInterface;

class EntityAutocompleteMultipleFilterType extends BaseFilterType
{
    private $translator;

    public function __construct(
        TranslatorInterface $translator
    ) {
        $this->translator = $translator;
    }

    protected function getModVal() {
        return [
            $this->translator->trans('equal', [], 'crud_filter')      => 'eq',
            $this->translator->trans('not equal', [], 'crud_filter')  => 'neq',
            $this->translator->trans('not filled', [], 'crud_filter') => 'null',
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options) {
        if ($options['mod']) {
            $builder->add('mod', Type\ChoiceType::class, [
                'label'=>false,
                'choices'=>$this->getModOptions($options['is_required']),
                'attr'=>[
                    'class'=>'form-control-sm',
                ]
            ]);
        }
        $params = [
            'label'=>false,
            'class'=>$options['widget_params']['class'],
        ];
        if (isset($options['route'])) {
            $params['route'] = $options['route'];
        }
        if (isset($options['opt_init'])) {
            $params['opt_init'] = $options['opt_init'];
        }
        $builder->add('val', MultiSelectAutocompleteType::class, $params);
    }

    /**
     * {@inheritdoc}
     */
    public function buildView(FormView $view, FormInterface $form, array $options) {
        parent::buildView($view, $form, $options);
        if (isset($options['route'])) {
            $view->vars['route'] = $options['route'];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver) {
        parent::configureOptions($resolver);
        $resolver->setDefaults([
            'type' => 'multiple',
            'route' => '',
            'opt_init' => '',
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix() {
        return 'entityautocompletemultiplefilter';
    }
}
