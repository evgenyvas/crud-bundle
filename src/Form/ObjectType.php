<?php

namespace Ecode\CRUDBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\DataMapperInterface;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Ecode\CRUDBundle\Form\Type as FormType;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Ecode\CRUDBundle\Service\ObjectManager;
use Ecode\CRUDBundle\Service\FormTypeManager;
use Symfony\Component\Validator\Constraints\Valid;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * form type for objects - required value object for each entity
 * implements custom DataMapper
 */
class ObjectType extends AbstractType implements DataMapperInterface
{
    protected $accessor;
    protected $data_class;
    protected $entity;
    protected $att_settings;
    protected $form_type;
    protected $id_prefix;
    protected $date_format;
    protected $datetime_format;
    protected $params;
    protected $fields;
    protected $em;
    protected $om;
    protected $extra_att;
    protected $formTypeManager;
    protected $translator;

    public function __construct(
        PropertyAccessorInterface $accessor,
        EntityManagerInterface $em,
        ObjectManager $om,
        ParameterBagInterface $params,
        FormTypeManager $formTypeManager,
        TranslatorInterface $translator
    ) {
        $this->accessor = $accessor;
        $this->em = $em;
        $this->om = $om;
        $this->params = $params;
        $this->formTypeManager = $formTypeManager;
        $this->translator = $translator;
    }

    public function mapDataToForms($data, $forms) {
        $forms = iterator_to_array($forms);
        if ($data) {
            foreach ($this->att_settings as $prop=>$params) {
                $widget = $params['widget'] ?? null;
                $tp = $params['type'] ?? 'string';
                if (isset($forms[$prop])) {
                    if ($widget === 'subform' and $tp === 'entity') {
                        continue;
                    }
                    if (array_key_exists($prop, $this->extra_att)) {
                        $prop_val = $this->extra_att[$prop];
                    } else {
                        $prop_val = ($data and $this->accessor->isReadable($data, $prop))
                            ? $this->accessor->getValue($data, $prop) : '';
                    }
                    if ($widget === 'file') {
                        if ($prop_val) {
                            if (file_exists($prop_val)) {
                                $prop_val = new File($prop_val);
                            } else {
                                $prop_val = null;
                            }
                        }
                    }
                    $forms[$prop]->setData($prop_val);
                }
            }
        }
    }

    public function mapFormsToData($forms, &$data) {
        $forms = iterator_to_array($forms);
        $class = new \ReflectionClass($this->data_class);

        $params = [];
        if ($class->name === 'stdClass') {
            $params = array_keys($this->att_settings);
        } else {
            $method = $class->getMethod('__construct');
            $param_data = $method->getParameters();
            foreach ($param_data as $param) {
                $params[] = $param->name;
            }
        }

        $args = [];
        foreach ($params as $par) {
            $widget = $this->att_settings[$par]['widget'] ?? null;
            $tp = $this->att_settings[$par]['type'] ?? 'string';
            $par_val = isset($forms[$par]) ?
                $forms[$par]->getData() : null;
            if (isset($this->att_settings[$par]['default']) and is_null($par_val)) {
                $par_val = $this->att_settings[$par]['default'];
            }
            if ($class->name === 'stdClass') {
                $data->$par = $par_val;
            } else {
                $args[] = $par_val;
            }
        }
        if ($class->name !== 'stdClass') {
            // data object is immutable, so we must set data via constructor
            $data = new $this->data_class(...$args);
        }
        foreach ($this->extra_att as $att_alias=>$att_value) {
            $par_val = isset($forms[$att_alias]) ?
                $forms[$att_alias]->getData() : null;
            if (property_exists($data, $att_alias)) {
                $this->accessor->setValue($data, $att_alias, $par_val);
            } else {
                $data->$att_alias = $par_val;
            }
        }
    }

    public function buildForm(FormBuilderInterface $builder, array $options) {
        $this->data_class = $options['data_class'];
        $this->entity = $options['entity'];
        $this->att_settings = $options['att_settings'];
        $this->extra_att = $options['extra_att'];

        $builder->setDataMapper($this);
        if (!$this->att_settings and isset($options['entity'])) {
            $repo = $this->em->getRepository(get_class($options['entity']));
            $this->att_settings = $repo->attSettings();
        }
        foreach ($this->att_settings as $prop=>$params) {
            if (!empty($options['render_fields'])
                and !in_array($prop, $options['render_fields'])
            ) {
                continue;
            }
            $required = $params['required'] ?? '';
            $change = $params['change'] ?? true;
            $read_only = $params['read_only'] ?? false;
            $render_add = $params['render_add'] ?? true;
            $render_edit = $params['render_edit'] ?? true;
            if ($options['form_type'] === 'add' and !$render_add) continue;
            elseif ($options['form_type'] === 'edit' and !$render_edit) continue;
            $show_add = $params['show_add'] ?? true;
            $show_edit = $params['show_edit'] ?? true;
            $show_view = $params['show_view'] ?? true;
            $opt = [];
            $tp = $params['type'] ?? 'string';
            if ($tp === 'join') continue;
            $widget = $params['widget'] ?? null;
            $opt['label'] = $params['label'];
            $opt['required'] = $required === 'required';

            // find custom filter
            $custom = $this->formTypeManager->getService($tp, $widget);

            if (!$show_add and $options['form_type'] === 'add') {
                $type = Type\HiddenType::class;
            } elseif (!$show_edit and $options['form_type'] === 'edit') {
                $type = Type\HiddenType::class;
            } elseif (!$show_view and $options['form_type'] === 'view') {
                $type = Type\HiddenType::class;
            } elseif ($custom) {
                $type = get_class($custom);
                foreach ($custom->params as $par) {
                    if (isset($params[$par])) {
                        $opt[$par] = $params[$par];
                    }
                }
            } elseif ($widget === 'password') {
                if ($options['form_type'] === 'edit') {
                    $opt['required'] = false;
                }
                $type = Type\RepeatedType::class;
                $opt['type'] = Type\PasswordType::class;
                $opt['first_options'] = ['label' => $params['label']];
                $opt['second_options'] = ['label' => $params['repeat_label']];
            } elseif ($widget === 'hidden') {
                $type = Type\HiddenType::class;
            } elseif ($widget === 'hidden' and $tp === 'entity') {
                $type = FormType\EntityHiddenType::class;
                $opt['class'] = $params['class'];
            } elseif ($widget === 'select' and $tp === 'entity') {
                $type = EntityType::class;
                $opt['class'] = $params['class'];
            } elseif ($widget === 'multichoice' and $tp === 'entity') {
                $type = EntityType::class;
                $opt['class'] = $params['class'];
                $opt['multiple'] = true;
            } elseif ($widget === 'selectautocomplete' and $tp === 'entity') {
                $type = FormType\SelectAutocompleteType::class;
                $opt['class'] = $params['class'];
                $opt['route'] = $params['route'];
            } elseif ($widget === 'colour') {
                $type = FormType\ColourType::class;
                $opt['colours'] = $params['colours'] ?? '';
            } elseif ($widget === 'multiselectautocomplete' and $tp === 'entity') {
                $type = FormType\MultiSelectAutocompleteType::class;
                if (isset($params['route'])) {
                    $opt['route'] = $params['route'];
                } else { // try to load options
                    if (isset($params['opt_query_builder'])) {
                        $opt_data = $params['opt_query_builder'](
                            $this->em->getRepository($params['class'])
                        )->getQuery()->getResult();
                    } else {
                        $opt_data = $this->em
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
                $opt['label_field'] = $params['label_field'];
                $opt['class'] = $params['class'];
            } elseif ($widget === 'subform' and $tp === 'entity') {
                $type = $params['subform_class'];
                $subEntity = $this->accessor->getValue($this->entity, $prop);
                if (is_null($subEntity)) { // must be an object
                    $subEntity = new $params['class'];
                }
                $dataClass = $this->om->getDataClass($params['class']);
                $opt['data'] = $this->om->getDataObject($dataClass, $subEntity);
                $opt['data_class'] = $dataClass;
                $opt['entity'] = $subEntity;
                $opt['form_type'] = $options['form_type'];
            } elseif ($widget === 'select') {
                $type = Type\ChoiceType::class;
                $opt['choices'] = $params['choices'] ?? [];
            } elseif ($widget === 'radio') {
                $type = Type\ChoiceType::class;
                $opt['expanded'] = true;
                $opt['placeholder'] = false;
                if ($tp === 'boolean') {
                    $yes_val = $params['yes_val'] ?? $this->translator->trans('Yes', [], 'crud_form');
                    $no_val = $params['no_val'] ?? $this->translator->trans('No', [], 'crud_form');
                    $opt['choices'] = [
                        $yes_val => '1',
                        $no_val => '0',
                    ];
                } else {
                    $opt['choices'] = $params['choices'];
                }
            } elseif ($widget === 'multichoice') {
                $type = Type\ChoiceType::class;
                $opt['multiple'] = true;
            } elseif ($tp === 'date' or ($tp === 'joinfield' and $widget === 'date')) {
                $type = Type\DateType::class;
                $opt['widget'] = 'single_text';
            } elseif ($tp === 'datetime') {
                $type = Type\DateTimeType::class;
                $opt['widget'] = 'single_text';
            } elseif ($tp === 'datetimesec') {
                $type = Type\DateTimeType::class;
                $opt['widget'] = 'single_text';
                $opt['with_seconds'] = true;
            } elseif ($tp === 'time') {
                $type = Type\TimeType::class;
                $opt['widget'] = 'single_text';
            } elseif ($widget === 'textarea') {
                $type = Type\TextareaType::class;
            } elseif ($widget === 'checkbox') {
                $type = Type\CheckboxType::class;
            } elseif ($widget === 'email') {
                $type = Type\EmailType::class;
            } elseif ($widget === 'file') {
                $type = Type\FileType::class;
            } elseif ($widget === 'subformadd') {
                $type = FormType\SubformAddType::class;
                $opt['entry_type'] = $params['entry_type'];
                $opt['allow_add'] = true;
                $opt['allow_delete'] = true;
                $opt['labels'] = $params['subform_labels'];
                $opt['hide_empty_table'] = $params['hide_empty_table'] ?? false;
                $opt['add_button_title'] = $params['add_button_title'] ?? $this->translator->trans('add', [], 'crud_form');
                $opt['prototype_data'] = $params['prototype_data'];
                $opt['constraints'] = [new Valid]; // enable validation for subform
                $entry_options = [
                    'label' => false,
                    'form_type' => $options['form_type'],
                    'att_settings' => $params['att_settings'] ?? [],
                ];
                if (isset($params['class'])) {
                    $entry_options['data_class'] = $this->om->getDataClass($params['class']);
                    $entry_options['entity'] = new $params['class'];
                }
                $opt['entry_options'] = $entry_options;
                if (isset($params['prototype_name'])) {
                    $opt['prototype_name'] = $params['prototype_name'];
                }
                if (isset($params['entry_options'])) {
                    foreach ($params['entry_options'] as $opt_k=>$opt_v) {
                        $opt['entry_options'][$opt_k] = $opt_v;
                    }
                }
            } else { // text by default
                $type = Type\TextType::class;
            }
            if (!$change and $options['form_type'] === 'edit') {
                $opt['disabled'] = true;
            }
            if ($read_only) {
                $opt['disabled'] = true;
            }
            if (isset($params['widget_params'])) {
                foreach ($params['widget_params'] as $w_k=>$w_v) {
                    $opt[$w_k] = $w_v;
                }
            }
            $builder->add($prop, $type, $opt);
        }
    }

    public function buildView(FormView $view, FormInterface $form, array $options) {
        $this->getAllIds($form, $form->getName(), $this->getBlockPrefix());
        $view->vars['fields'] = $this->fields;
        $view->vars['id'] = $options['id_prefix'].$view->vars['id'];
        $view->vars['id_prefix'] = $options['id_prefix'];
        $view->vars['date_format'] = $options['date_format'];
        $view->vars['datetime_format'] = $options['datetime_format'];
    }

    public function getAllIds(FormInterface $form, $parent_name, $parent_prefix) {
        foreach ($form as $ch) {
            $name = $parent_name.'_'.$ch->getName();
            $prefix = $ch->getConfig()->getType()->getInnerType()->getBlockPrefix();
            $has_children = (bool)$ch->count();
            if (!$has_children) {
                $this->fields[] = [
                    'id'=>$name,
                    'tp'=>$prefix,
                    'par'=>$parent_prefix,
                ];
            }
            if ($has_children and ($ch instanceof FormInterface)) {
                $this->getAllIds($ch, $name, $prefix);
            }
        }
    }

    public function configureOptions(OptionsResolver $resolver) {
        $resolver->setDefaults([
            'empty_data' => null, // required
            'entity' => null,
            'att_settings' => null,
            'form_type' => null,
            'render_fields' => [],
            'validation_groups' => ['Default'],
            'id_prefix' => null,
            'extra_att' => [],
            'date_format' => $this->params->get('flatpickr_date_format'),
            'datetime_format' => $this->params->get('flatpickr_datetime_format'),
        ]);
    }
}
