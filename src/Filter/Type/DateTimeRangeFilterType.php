<?php

namespace Ecode\CRUDBundle\Filter\Type;

use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type;
use Symfony\Contracts\Translation\TranslatorInterface;

class DateTimeRangeFilterType extends BaseFilterType
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
            $this->translator->trans('any value', [], 'crud_filter')  => 'notnull',
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
        $builder->add('since', Type\DateTimeType::class, [
            'label'=>'с',
            'widget'=>'single_text',
            'data'=>$options['data_since'] ?? null,
            'attr'=>[
                'class'=>'form-control-sm',
                '@on-change'=>'onDateRangeStartChange',
            ]
        ]);
        $builder->add('until', Type\DateTimeType::class, [
            'label'=>'по',
            'widget'=>'single_text',
            'data'=>$options['data_until'] ?? null,
            'attr'=>[
                'class'=>'form-control-sm',
                '@on-change'=>'onDateRangeEndChange',
                'data-until'=>'1',
            ]
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver) {
        parent::configureOptions($resolver);
        $resolver->setDefaults([
            'type' => 'dateTimeRange',
            'data_since' => null,
            'data_until' => null,
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix() {
        return 'datetimerangefilter';
    }
}
