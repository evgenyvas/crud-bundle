<?php

namespace Ecode\CRUDBundle\Filter\Type;

use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Contracts\Translation\TranslatorInterface;

class EntitySingleFilterType extends BaseFilterType
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
        $opt = [
            'label'=>false,
            'empty_data'=>null,
        ];
        if (isset($options['widget_params'])) {
            foreach ($options['widget_params'] as $w_k=>$w_v) {
                $opt[$w_k] = $w_v;
            }
        }
        if (!isset($opt['expanded'])) { // select
            $opt['attr'] = [
                'class' => 'form-control-sm'
            ];
            $opt['placeholder'] = $opt['placeholder'] ?? '';
        } else { // radio
            $opt['required'] = false;
            $opt['placeholder'] = $opt['placeholder'] ?? $this->translator->trans('Neither', [], 'crud_filter');
        }
        $builder->add('val', EntityType::class, $opt);
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver) {
        parent::configureOptions($resolver);
        $resolver->setDefaults([
            'type' => 'single',
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix() {
        return 'entitysinglefilter';
    }
}
