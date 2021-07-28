<?php

namespace Ecode\CRUDBundle\Filter\Type;

use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Contracts\Translation\TranslatorInterface;

class EntityMultipleFilterType extends BaseFilterType
{
    private $translator;

    public function __construct(
        TranslatorInterface $translator
    ) {
        $this->translator = $translator;
    }

    protected function getModVal() {
        return [
            $this->translator->trans('contains', [], 'crud_filter')     => 'con',
            $this->translator->trans('not contains', [], 'crud_filter') => 'ncon',
            $this->translator->trans('not filled', [], 'crud_filter')   => 'null',
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options) {
        $opt = [
            'label'=>false,
            'placeholder'=>'',
            'empty_data'=>null,
            'multiple'=>true,
        ];
        if (isset($options['widget_params'])) {
            foreach ($options['widget_params'] as $w_k=>$w_v) {
                $opt[$w_k] = $w_v;
            }
        }
        if (!isset($opt['expanded'])) {
            if (!isset($opt['attr'])) {
                $opt['attr'] = [];
            }
            if (!isset($opt['attr']['class'])) {
                $opt['attr']['class'] = '';
            }
            $opt['attr']['class'] .= ' form-control-sm';
        }
        $builder->add('val', EntityType::class, $opt);
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver) {
        parent::configureOptions($resolver);
        $resolver->setDefaults([
            'type' => 'multipleEntity',
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix() {
        return 'entitymultiplefilter';
    }
}
