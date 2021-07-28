<?php

namespace Ecode\CRUDBundle\Filter\Type;

use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type;

class JsonListFilterType extends BaseFilterType
{
    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options) {
        $opt = [
            'label'=>false,
            'multiple'=>true,
            'expanded'=>true,
            'choices'=>$options['choices'],
        ];
        if (isset($options['widget_params'])) {
            foreach ($options['widget_params'] as $w_k=>$w_v) {
                $opt[$w_k] = $w_v;
            }
        }
        $builder->add('val', Type\ChoiceType::class, $opt);
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver) {
        parent::configureOptions($resolver);
        $resolver->setDefaults([
            'type' => 'jsonList',
            'choices' => [],
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix() {
        return 'jsonlistfilter';
    }
}
