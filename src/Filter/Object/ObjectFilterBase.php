<?php

namespace Ecode\CRUDBundle\Filter\Object;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type;
use Ecode\CRUDBundle\Filter\Type as FilterType;
use Ecode\CRUDBundle\Service\FilterTypeManager;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * form type for additional filter
 */
class ObjectFilterBase extends AbstractType
{
    protected $container;
    protected $att_settings;
    protected $section;
    protected $filterTypeManager;

    public function __construct(
        ContainerInterface $container,
        FilterTypeManager $filterTypeManager
    ) {
        $this->container = $container;
        $this->filterTypeManager = $filterTypeManager;
    }

    public function buildForm(FormBuilderInterface $builder, array $options) {
        $this->att_settings = $options['att_settings'];
        foreach ($this->att_settings as $prop=>$params) {
            $is_filter = $params['filter'] ?? true;
            $tp = $params['type'] ?? 'string';
            if (!$is_filter or $tp === 'join') continue;
            $section = $params['filter_section'] ?? 'add';
            if ($this->section !== $section) continue;
            if ($tp === 'joinfield') {
                $tp = (isset($params['join_field_data']) and count($params['join_field_data']) === 1
                    and isset($params['join_field_data']['0']['type'])) ? $params['join_field_data']['0']['type'] : 'string';
            }
            $widget = $params['filter_widget'] ?? $params['widget'] ?? null;
            $opt = [];
            $opt['label'] = $params['filter_label'] ?? $params['label'];
            // find custom filter
            $custom = $this->filterTypeManager->getService($tp, $widget);
            if ($custom) {
                $type = get_class($custom);
                foreach ($custom->params as $par) {
                    if (isset($params[$par])) {
                        $opt[$par] = $params[$par];
                    }
                }
            } elseif ($tp === 'int' and $widget === 'number') {
                $type = FilterType\NumberFilterType::class;
            } elseif ($tp === 'int' and $widget === 'numrange') {
                $type = FilterType\NumberRangeFilterType::class;
            } elseif (($tp === 'datetime' or $tp === 'date') and ($widget === 'date' or $widget === 'datetime')) {
                $type = FilterType\DateFilterType::class;
            } elseif (($tp === 'datetime' or $tp === 'date') and $widget === 'daterange') {
                $type = FilterType\DateRangeFilterType::class;
            } elseif ($tp === 'date' and $widget === 'month') {
                $type = FilterType\MonthFilterType::class;
            } elseif ($tp === 'datetime' and $widget === 'datetimerange') {
                $type = FilterType\DateTimeRangeFilterType::class;
            } elseif (($tp === 'datetime' or $tp === 'date') and $widget === 'daterangefixed') {
                $type = FilterType\DateRangeFixedFilterType::class;
            } elseif ($widget === 'selectautocomplete' and $tp === 'entity') {
                $type = FilterType\EntityAutocompleteSingleFilterType::class;
                $opt['route'] = $params['route'];
                $opt['widget_params'] = ['class'=>$params['class']];
            } elseif ($widget === 'multiselectautocomplete' and $tp === 'entity') {
                $type = FilterType\EntityAutocompleteMultipleFilterType::class;
                if (isset($params['route'])) {
                    $opt['route'] = $params['route'];
                } else { // try to load options
                    $em = $this->container->get('doctrine')->getManager();
                    if (isset($params['opt_query_builder'])) {
                        $opt_data = $params['opt_query_builder']($em->getRepository($params['class']))
                            ->getQuery()->getResult();
                    } else {
                        $opt_data = $em
                            ->createQueryBuilder()
                            ->select('e.id, e.'.$params['label_field'])
                            ->from($params['class'], 'e')
                            ->getQuery()->getResult();
                    }
                    $opt_init = [];
                    foreach ($opt_data as $v) {
                        $opt_init[] = [
                            'label' => $v[$params['label_field']],
                            'value' => $v['id'],
                        ];
                    }
                    $opt['opt_init'] = json_encode($opt_init, JSON_UNESCAPED_UNICODE);
                }
                $opt['widget_params'] = ['class'=>$params['class']];
            } elseif ($tp === 'boolean') {
                $type = FilterType\BooleanFilterType::class;
            } elseif ($tp === 'entity' and ($widget === 'select' or $widget === 'multichoice')) {
                if ($widget === 'select') {
                    $type = FilterType\EntitySingleFilterType::class;
                } elseif ($widget === 'multichoice') {
                    $type = FilterType\EntityMultipleFilterType::class;
                }
                $opt['widget_params'] = ['class'=>$params['class']];
                foreach (['choice_label', 'query_builder'] as $w_a) {
                    if (isset($params['widget_params'][$w_a])) {
                        $opt['widget_params'][$w_a] = $params['widget_params'][$w_a];
                    }
                }
            } elseif ($tp === 'string' and $widget === 'select') {
                $type = FilterType\SelectSingleFilterType::class;
                $opt['widget_params']['choices'] = $params['choices'] ?? $params['widget_params']['choices'] ?? [];
            } elseif ($tp === 'string' and $widget === 'colour') {
                $type = FilterType\SelectSingleFilterType::class;
                $opt['widget_params']['choices'] = $params['choices'];
            } elseif ($tp === 'json' and $widget === 'multichoice') {
                $type = FilterType\JsonListFilterType::class;
                $opt['choices'] = $params['widget_params']['choices'];
            } else {
                $type = FilterType\StringFilterType::class;
            }
            if (isset($params['filter_params'])) {
                $opt = array_replace_recursive($opt, $params['filter_params']);
            }
            if (isset($opt['required'])) {
                $opt['widget_params']['constraints'][] = new Assert\NotBlank();
            }
            $builder->add($prop, $type, $opt);
        }
    }

    public function configureOptions(OptionsResolver $resolver) {
        $resolver->setDefaults([
            'att_settings' => [],
        ]);
    }
}
