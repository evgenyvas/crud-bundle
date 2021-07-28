<?php

namespace Ecode\CRUDBundle\Filter\Object;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * form type for search filter section
 */
class ObjectSearchType extends AbstractType
{
    private $translator;

    public function __construct(
        TranslatorInterface $translator
    ) {
        $this->translator = $translator;
    }

    public function configureOptions(OptionsResolver $resolver) {
        $resolver->setDefaults([
            'label'=>false,
            'attr'=>[
                'placeholder'=>$this->translator->trans('Search', [], 'crud_filter_add'),
            ],
        ]);
    }

    public function getParent() {
        return Type\TextType::class;
    }
}
