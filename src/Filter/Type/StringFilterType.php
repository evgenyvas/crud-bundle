<?php

namespace Ecode\CRUDBundle\Filter\Type;

use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type;
use Symfony\Contracts\Translation\TranslatorInterface;

class StringFilterType extends BaseFilterType
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
            $this->translator->trans('equal', [], 'crud_filter')        => 'eq',
            $this->translator->trans('not equal', [], 'crud_filter')    => 'neq',
            $this->translator->trans('not filled', [], 'crud_filter')   => 'null',
            $this->translator->trans('any value', [], 'crud_filter')    => 'notnull',
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
        $builder->add('val', Type\TextType::class, [
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
            'type' => 'string',
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix() {
        return 'stringfilter';
    }
}
