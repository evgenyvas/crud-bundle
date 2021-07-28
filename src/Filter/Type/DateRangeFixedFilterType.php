<?php

namespace Ecode\CRUDBundle\Filter\Type;

use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type;
use Symfony\Contracts\Translation\TranslatorInterface;

class DateRangeFixedFilterType extends BaseFilterType
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

    protected function getIntervals() {
        return [
            $this->translator->trans('Today', [], 'crud_filter')           => 'today',
            $this->translator->trans('Week', [], 'crud_filter')            => 'week',
            $this->translator->trans('A week ahead', [], 'crud_filter')    => 'fweek',
            $this->translator->trans('Current week', [], 'crud_filter')    => 'curweek',
            $this->translator->trans('Month', [], 'crud_filter')           => 'month',
            $this->translator->trans('A month ahead', [], 'crud_filter')   => 'fmonth',
            $this->translator->trans('Current month', [], 'crud_filter')   => 'curmonth',
            $this->translator->trans('Quarter', [], 'crud_filter')         => 'quarter',
            $this->translator->trans('Quarter ahead', [], 'crud_filter')   => 'fquarter',
            $this->translator->trans('Current quarter', [], 'crud_filter') => 'curquarter',
            $this->translator->trans('Half a year', [], 'crud_filter')     => 'halfyear',
            $this->translator->trans('9 months', [], 'crud_filter')        => 'ninemonth',
            $this->translator->trans('Year', [], 'crud_filter')            => 'year',
            $this->translator->trans('Year ahead', [], 'crud_filter')      => 'fyear',
            $this->translator->trans('Current year', [], 'crud_filter')    => 'curyear',
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
        $builder->add('val', Type\ChoiceType::class, [
            'label'=>false,
            'placeholder'=>'',
            'empty_data'=>null,
            'choices'=>$this->getIntervals(),
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
            'type' => 'dateRangeFixed',
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix() {
        return 'daterangefixedfilter';
    }
}
