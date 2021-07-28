<?php

namespace Ecode\CRUDBundle\Filter\Type;

use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type;
use Ecode\CRUDBundle\Form\Type\MonthType;
use Symfony\Contracts\Translation\TranslatorInterface;

class MonthFilterType extends BaseFilterType
{
    private $translator;

    public function __construct(
        TranslatorInterface $translator
    ) {
        $this->translator = $translator;
    }

    protected function getModVal() {
        return [
            $this->translator->trans('equal', [], 'crud_filter')         => 'eq',
            $this->translator->trans('not equal', [], 'crud_filter')     => 'neq',
            $this->translator->trans('more', [], 'crud_filter')          => 'more',
            $this->translator->trans('more or equal', [], 'crud_filter') => 'eqmore',
            $this->translator->trans('less', [], 'crud_filter')          => 'less',
            $this->translator->trans('less or equal', [], 'crud_filter') => 'eqless',
            $this->translator->trans('not filled', [], 'crud_filter')    => 'null',
            $this->translator->trans('any value', [], 'crud_filter')     => 'notnull',
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
        $builder->add('val', MonthType::class, [
            'label'=>false,
            'attr'=>[
                'class'=>'form-control-sm',
            ]
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver) {
        parent::configureOptions($resolver);
        $resolver->setDefaults([
            'type' => 'month',
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix() {
        return 'monthfilter';
    }
}
