<?php

namespace Ecode\CRUDBundle\Filter\Type;

use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type;
use Symfony\Component\Form\FormView;
use Symfony\Component\Form\FormInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class BooleanFilterType extends BaseFilterType
{
    private $translator;

    public function __construct(
        TranslatorInterface $translator
    ) {
        $this->translator = $translator;
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options) {
        $params = [
            'label'=>false,
            'placeholder'=>'',
            'empty_data'=>null,
            'choices'=>[
                $options['yes_val'] => 'y',
                $options['no_val'] => 'n',
            ],
            'attr'=>[
                'class'=>'form-control-sm',
            ]
        ];
        if ($options['check']) {
            $params['expanded'] = true;
            $params['multiple'] = true;
            $params['choices'] = [$options['label']=>'y'];
        }
        $builder->add('val', Type\ChoiceType::class, $params);
    }

    public function buildView(FormView $view, FormInterface $form, array $options) {
        $view->vars['width'] = $options['width'];
        if ($options['check']) {
            $view->vars['headerhide'] = '1';
        }
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver) {
        parent::configureOptions($resolver);
        $resolver->setDefaults([
            'type' => 'boolean',
            'yes_val' => $this->translator->trans('Yes', [], 'crud_filter'),
            'no_val' => $this->translator->trans('No', [], 'crud_filter'),
            'check' => false,
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix() {
        return 'booleanfilter';
    }
}
