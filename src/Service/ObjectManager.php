<?php

namespace Ecode\CRUDBundle\Service;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\Form\Form;
use Doctrine\ORM\EntityManagerInterface;
use Ecode\CRUDBundle\Filter\FilterQuery;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Ecode\CRUDBundle\Event\Object\ObjectAddedEvent;
use Ecode\CRUDBundle\Event\Object\ObjectChangedEvent;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Ecode\CRUDBundle\Repository\FormatColumnsTrait;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Ecode\DocumentusBundle\Helpers\ImageHelper;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Ecode\CRUDBundle\Service\FilterManager;
use Symfony\Contracts\Translation\TranslatorInterface;

class ObjectManager extends BaseFormManager {

    use FormatColumnsTrait;

    private $accessor;
    private $fmt;
    private $em;
    private $tokenStorage;
    private $formFactory;
    private $validator;
    private $dispatcher;
    private $params;
    private $filterManager;
    private $translator;

    public function __construct(
        TokenStorageInterface $tokenStorage,
        PropertyAccessorInterface $accessor,
        EntityManagerInterface $em,
        ObjectFormatter $fmt,
        FormFactoryInterface $formFactory,
        ValidatorInterface $validator,
        EventDispatcherInterface $dispatcher,
        ParameterBagInterface $params,
        FilterManager $filterManager,
        TranslatorInterface $translator
    ) {
        $this->accessor = $accessor;
        $this->fmt = $fmt;
        $this->tokenStorage = $tokenStorage;
        $this->em = $em;
        $this->formFactory = $formFactory;
        $this->validator = $validator;
        $this->dispatcher = $dispatcher;
        $this->params = $params;
        $this->filterManager = $filterManager;
        $this->translator = $translator;
    }

    public function getDataClass($entity) {
        return str_replace('\Entity\\', '\Data\\', $entity).'Data';
    }

    public function getDataObject($dataClass, $entityObj, $attrs=[], $dataObjDefault=[]) {
        $repository = $this->em->getRepository(get_class($entityObj));
        $att_settings = $attrs ?: $repository->attSettings();
        $dataClassDesc = new \ReflectionClass($dataClass);
        $dataMethod = $dataClassDesc->getMethod("__construct");
        $data_params = $dataMethod->getParameters();

        // populate data object
        $args = [];
        foreach ($data_params as $par) {
            if (isset($dataObjDefault[$par->name])) {
                $args[] = $dataObjDefault[$par->name];
                continue;
            }
            $att_params = $att_settings[$par->name];
            $tp = $att_params['type'] ?? 'string';
            if ($tp === 'joinfield') {
                $par_val = $this->dataFormat($entityObj, [
                    'format_fields'=>[$par->name],
                    'attrs'=>$attrs,
                ])[$par->name];
            } elseif (isset($att_params['get_method'])) {
                $par_val = $repository->{$att_params['get_method']}($entityObj);
                if (isset($att_params['format_method'])) {
                    $par_val = $repository->{$att_params['format_method']}($par_val);
                }
            } else {
                $par_val = $this->accessor->isReadable($entityObj, $par->name) ?
                    $this->accessor->getValue($entityObj, $par->name) : null;
            }
            if (isset($att_params['default']) and is_null($par_val)) {
                $par_val = $att_params['default'];
            }
            $args[] = $par_val;
        }

        // data object is immutable, so we must set data via constructor
        return new $dataClass(...$args);
    }

    /**
     * get list of objects in array
     *
     * @param $entity string Doctrine entity name
     * @param $page int page number
     * @param $perPage int number of elements per page
     * @param $sortBy string field name for sort
     * @param $sortDesc boolean sort objects by descending
     * @param $filter array filter parameters
     *
     * @return array list of objects data with additional information
     */
    public function getList($entity, $page, $perPage, $sortBy, $sortDesc, $filter=[], $options=[], $formatParams=[]) {
        $disableVersioning = $options['disableVersioning'] ?? false;

        $config = $this->em->getConfiguration();
        $meta = $this->em->getClassMetadata($entity);
        $fields = $meta->getFieldNames();
        $repo = $this->em->getRepository($entity);
        $att = $options['attrs'] ?? $repo->attSettings();
        $to_sort = [];
        $to_sort_join = [];
        $to_sort_entity = [];
        foreach ($att as $field=>$field_params) {
            $sort = $field_params['sort'] ?? true;
            $type = $field_params['type'];
            if ($sort) {
                $to_sort[] = $field;
                if ($type === 'joinfield') {
                    $to_sort_join[] = $field;
                } elseif ($type === 'entity') {
                    $to_sort_entity[] = $field;
                }
            }
        }
        $sortBy = ($sortBy and in_array($sortBy, $to_sort)) ? $sortBy : ($repo->sort_by ?? 'id');

        // main query
        $qb = $repo->createQueryBuilder('e');
        if (isset($options['fields'])) {
            $options['fields'][] = 'id';
            $qb->select('partial e.{'.implode(',', $options['fields']).'}');
        }
        if (in_array($sortBy, $to_sort_join)) { // first field used for sorting
            $query = $qb->orderBy($att[$sortBy]['join_field'].'.'.$att[$sortBy]['join_field_data']['0']['field'], $sortDesc ? 'DESC' : 'ASC');
        } elseif (in_array($sortBy, $to_sort_entity)) {
            $query = $qb->orderBy($sortBy.'.'.$att[$sortBy]['label_field'], $sortDesc ? 'DESC' : 'ASC');
        } else {
            $query = $qb->orderBy('e.'.$sortBy, $sortDesc ? 'DESC' : 'ASC');
        }

        // filters
        $filterQuery = new FilterQuery($repo, $meta, $this->filterManager, $options);

        $search = '';
        foreach ($filter as $filter_data) {
            if (isset($filter_data['ftype']) and $filter_data['ftype'] === 'search') {
                $search = $filter_data['val'];
                break;
            }
        }

        $filter_by_fields = [];
        if (!empty($filter)) {
            foreach ($filter as $fi) {
                $f_att = $fi['att']['0'] ?? null;
                if (!is_null($f_att)) {
                    $filter_by_fields[$f_att] = $fi; // single filter for field
                }
            }
        }

        $join_fields = [];
        // find fields for join
        foreach ($att as $field=>$field_params) {
            $type = $field_params['type'];
            if ($type === 'joinfield') {
                $join_fields[$field_params['join_field']][$field] = $field_params['join_field_data'];
            }
        }

        // relations
        foreach ($att as $field=>$field_params) {
            if (isset($options['fields']) and !in_array($field, $options['fields'])) continue;
            $type = $field_params['type'];
            $widget = $field_params['widget'] ?? 'text';
            $load_list = $field_params['load_list'] ?? true;
            $is_search = $field_params['search'] ?? true;
            $field_joined = false;
            // must join for filter
            if ($type === 'entity' or $type === 'join' or $type === 'entitylist') {
                if ($search) {
                    if ($is_search) {
                        $filterQuery->addJoin($field);
                        if ($type === 'entitylist') {
                            $filterQuery->addJoin($field_params['join_field'], $field);
                        }
                        $field_joined = true;
                    }
                } else {
                    if (isset($join_fields[$field])) {
                        foreach (array_keys($join_fields[$field]) as $j_f) {
                            if (isset($filter_by_fields[$j_f])) {
                                $filterQuery->addJoin($field);
                                $field_joined = true;
                                break;
                            }
                        }
                    } else {
                        if (isset($filter_by_fields[$field]) and $filter_by_fields[$field]) {
                            $filterQuery->addJoin($field);
                            if ($type === 'entitylist') {
                                $filterQuery->addJoin($field_params['join_field'], $field);
                            }
                            $field_joined = true;
                        }
                    }
                }
            }
            if (!$load_list and $type !== 'join') continue;
            if ($type === 'entity') {
                $w_params = $field_params['widget_params'] ?? [];
                $w_exp = $w_params['expanded'] ?? false;
                $w_label = $w_params['choice_label'] ?? null;
                if (!$w_label and isset($field_params['label_field'])) {
                    $w_label = $field_params['label_field'];
                }
                $e_join_fields = [];
                if (isset($join_fields[$field])) {
                    foreach ($join_fields[$field] as $j_data) {
                        foreach ($j_data as $j_f_data) {
                            $e_join_fields[] = $j_f_data['field'];
                        }
                    }
                }
                $canSort = false;
                if (isset($att[$sortBy]['join_field_data'])) {
                    foreach ($att[$sortBy]['join_field_data'] as $j_data) {
                        if (in_array($j_data['field'], $e_join_fields)) {
                            $canSort = true;
                            break;
                        }
                    }
                }
                $data_full_fields = [];
                if (isset($field_params['data_full'])) {
                    foreach ($field_params['data_full'] as $ff) {
                        if ($ff === 'id' or $ff === $w_label) {
                            continue;
                        }
                        $data_full_fields[] = $ff;
                    }
                }
                $query->leftJoin('e.'.$field, $field)
                    ->addSelect('partial '.$field.'.{id'.($w_label ? ','.$w_label : '').
                    (empty($data_full_fields) ? '' : ','.implode(',', $data_full_fields)).
                    (isset($join_fields[$field]) ? ','.implode(',', $e_join_fields) : '').'}');
                if ($sortBy === $field or $canSort) {
                    // must join is sorting
                    if (!$field_joined) $filterQuery->addJoin($field);
                }
            } elseif ($type === 'entitylist') {
                $extra_fields = $field_params['extra_fields'] ?? [];
                $junction_fields = array_merge([$field_params['join_field']],
                    array_keys($extra_fields));
                $query->leftJoin('e.'.$field, $field)
                    ->addSelect('partial '.$field.'.{id,'.implode(',', $junction_fields).'}')
                    ->leftJoin($field.'.'.$field_params['join_field'],
                        $field.'_'.$field_params['join_field'])
                    ->addSelect('partial '.$field.'_'.$field_params['join_field'].
                        '.{id,'.implode(',', array_keys($field_params['main_fields'])).'}');
            } elseif ($type === 'join') {
                $is_versioning = $field_params['versioning'] ?? false;
                $e_join_fields = [];
                if (isset($join_fields[$field])) {
                    foreach ($join_fields[$field] as $j_data) {
                        foreach ($j_data as $j_f_data) {
                            $e_join_fields[] = $j_f_data['field'];
                        }
                    }
                }
                if ($is_versioning) {
                    $e_join_fields[] = 'version';
                }
                $query->leftJoin('e.'.$field, $field)
                    ->addSelect('partial '.$field.'.{id,'.implode(',', $e_join_fields).'}');
                if ($is_versioning) {
                    $joinRepo = $this->em->getRepository($meta->associationMappings[$field]['targetEntity']);
                    $query->andWhere($field.'.version = ('.
                        $joinRepo->createQueryBuilder($field.'ver')->select('MAX('.$field.'ver.version)')
                        ->where($field.'ver.'.$meta->associationMappings[$field]['mappedBy'].' = e.id')->getDql().
                        ') OR '.$field.'.version IS NULL');
                }
                if (in_array($sortBy, $e_join_fields)) {
                    // must join is sorting
                    if (!$field_joined) $filterQuery->addJoin($field);
                }
            }
        }

        $is_filter = false;
        if (!empty($filter)) {
            if ($att) {
                foreach ($att as $attcol=>$attr) {
                    $filter_check = isset($attr['filter_check']) ? boolval($attr['filter_check']) : false;
                    if ($filter_check) {
                        $att[$attcol]['filter'] = true;
                    }
                }
            }
            $filter_info = $this->getFilterInfo($entity, $att);
        }
        $deleted_filter = false;
        foreach ($filter as $filter_data) {
            if (isset($filter_data['ftype'])) {
                if ($filter_data['ftype'] === 'search') {
                    if ($search) {
                        $is_filter = $filterQuery->filter('search', [$filter_data['val'], $att]);
                    }
                    continue;
                }
                $filterQuery->setQueryClass($filter_data['ftype']);
                $flt = $filterQuery->filter($filter_data['type'], [$filter_data, $filter]);
                if (!$is_filter) {
                    $is_filter = $flt;
                }
                $filterQuery->restoreQueryClass();
                continue;
            }
            $field = $filter_data['att']['0'];
            if ($field === 'isDeleted') {
                $deleted_filter = true;
            }
            $field_params = $att[$field] ?? null;
            if (!isset($filter_info[$field])) continue;
            $filterQuery->setQueryClass($filter_info[$field]['class']);
            $f_type = $filter_data['type'] ?? $filter_info[$field]['type'] ?? 'string';
            $field_type = $field_params['type'] ?? 'string';
            $field_widget = $field_params['widget'] ?? 'text';
            if ($field_type === 'entitylist' and $f_type === 'single') {
                $f_type = 'multipleEntity';
            }
            // apply filters
            if ($field_params) { // filter by field
                $alias = null;
                if ($field_type === 'joinfield') {
                    $alias = $field_params['join_field'];
                    if (count($field_params['join_field_data']) === 1) {
                        $field = $field_params['join_field_data']['0']['field'];
                    } else { // many fields - special type
                        $f_type = 'stringOR';
                        $field = array_column($field_params['join_field_data'], 'field');
                    }
                }
                if ($field_type === 'entity' and $field_widget === 'subformadd') { // many-to-many
                    $alias = $field;
                    $field = 'id';
                }
                if ($f_type === 'multiple' and $field_type === 'entity') { // many-to-many
                    $alias = $field;
                    $field = 'id';
                }
                if (!is_array($field) and isset($filter_info[$field]['field'])) {
                    $field = $filter_info[$field]['field'];
                }
                if (isset($filter_data['mod']) and
                    $filter_data['mod'] === 'null') { // not filled attribute
                    $flt = $filterQuery->filter('null', [$field, $alias]);
                } else { // filter by certain value
                    $flt = $filterQuery->filter($f_type, [$field, $filter_data, $alias]);
                }
            } else { // special filter
                $flt = $filterQuery->filter($f_type, [$filter_data, $filter]);
            }
            if (!$is_filter) {
                $is_filter = $flt;
            }
        }
        $filterQuery->restoreQueryClass();
        if (method_exists($repo, 'listFilter')) {
            $repo->listFilter($filterQuery, $filter); // repository custom filter
        }
        if (in_array('isDeleted', $fields) and !$deleted_filter) {
            $filterQuery->filter('string', ['isDeleted', ['mod'=>'eq', 'val'=>'0']]);
        }
        // by id
        if (isset($filter['id'])) {
            $filterQuery->filter('number', ['id', $filter['id']]);
        }
        //dump($filterQuery->getQueryBuilder()->getQuery()->getDql()); die;

        // execute count query for filters to getting total number of elements
        $count_total = $filterQuery->setSelectCount()->setOrder('id', 'DESC')
            ->getQueryBuilder()->getQuery()->getSingleScalarResult();

        $check_total = isset($options['check_total']) ? $options['check_total'] : false;
        if ($check_total and $count_total > $perPage) {
            return ['data'=>'not_all'];
        }

        // execute filter query to getting elements identificators
        $filterQuery->setSelectId();
        if (in_array($sortBy, $to_sort_join)) {
            $filterQuery->setOrder($att[$sortBy]['join_field_data']['0']['field'], $sortDesc, $att[$sortBy]['join_field']);
        } elseif (in_array($sortBy, $to_sort_entity)) {
            $filterQuery->setOrder($att[$sortBy]['label_field'], $sortDesc, $sortBy);
        } else {
            $filterQuery->setOrder($sortBy, $sortDesc);
        }
        $fltQuery = $filterQuery->getQueryBuilder()->getQuery();
        if ($perPage) {
            $fltQuery
                ->setFirstResult($perPage * ($page - 1))
                ->setMaxResults($perPage);
        }
        $fltRes = $fltQuery->getScalarResult();
        $is_versioning = $repo->is_versioning ?? false;
        if ($is_versioning and !$disableVersioning) {
            $filterRes = array_column($fltRes, 'version', 'id');
        } else {
            $filterRes = array_column($fltRes, 'id');
        }

        $queryFilter = $filterQuery->getQueryBuilder()->getQuery();
        $filterParams = [];
        foreach ($queryFilter->getParameters() as $param) {
            $filterParams[$param->getName()] = $param->getValue();
        }

        if ($is_versioning and !$disableVersioning) {
            $version_cond = ['e.id = 0'];
            foreach ($filterRes as $el_id=>$el_ver) {
                $version_cond[] = $qb->expr()->andX(...[
                    'e.id = '.$el_id,
                    'e.version = '.$el_ver
                ]);
            }
            $query->andWhere($qb->expr()->orX(...$version_cond));
        } else {
            $filterRes[] = 0; // in case when nothing is found
            $query->andWhere($qb->expr()->in('e.id', $filterRes));
        }
        $q = $query->getQuery();
        // execute main query
        $fetchObjects = $options['fetchObjects'] ?? false;
        $data = $dataFull = $q->getResult();
        $data_full = [];
        if (!$fetchObjects) { // format data
            foreach ($data as $idx=>$dataObj) {
                if (isset($options['fields'])) {
                    $formatParams['format_fields'] = $options['fields'];
                }
                if (isset($options['attrs'])) {
                    $formatParams['attrs'] = $options['attrs'];
                }
                $data[$idx] = $this->dataFormat($dataObj, $formatParams);
                $data_full[$idx] = $data[$idx]['_dataFull'] ?? [];
            }
        }
        // get columns parameters for current user
        //$userSettings = $this->tokenStorage->getToken()->getUser()->getSettings();
        //$userGridSettings = $userSettings ? json_decode($userSettings->getGrid(), true) : [];
        return [
            'query' => [
                'main' => [
                    'dql' => $query->getDql(),
                    'sql' => $q->getSql(),
                ],
                'filter' => [
                    'dql' => $filterQuery->getQueryBuilder()->getDql(),
                    'sql' => $queryFilter->getSql(),
                    'parameters' => $filterParams,
                ]
            ],
            'is_filter' => $is_filter,
            'total' => $count_total,
            'per_page' => $perPage,
            'current_page' => $page,
            'sort_by' => $sortBy,
            'sort_desc' => $sortDesc,
            'data' => $data,
            'data_full' => $data_full,
            '_dataFull' => $dataFull,
            //'columns' => $repo->formatColumns(null, $userGridSettings[mb_strtolower($entity)] ?? []),
            'columns' => method_exists($repo, 'formatColumns') ? $repo->formatColumns($att) : $this->formatColumns($att),
        ];
    }

    /**
     * get additional data for filter fields
     *
     * @param $entity string Doctrine entity name
     *
     * @return array fields data
     */
    public function getFilterInfo($entity, $att=[]) {
        $entityRepo = $this->em->getRepository($entity);
        if (!$att) {
            $att = $entityRepo->attSettings();
        }
        $result = [];
        // try to find in configuration
        foreach ($att as $k=>$v) {
            if (isset($v['filter_info'])) {
                $result[$k] = $v['filter_info'];
            }
        }
        $form = $this->formFactory->create(
            \Ecode\CRUDBundle\Filter\Object\ObjectType::class, null, [
                'att_settings' => $att,
            ]
        );
        foreach ($form as $f_name=>$f_par) {
            if ($f_name === 'filtermain' or $f_name === 'filteradd') {
                foreach ($f_par as $sf_name=>$sf_par) {
                    $result[$sf_name] = [
                        'class' => $sf_par->getConfig()->getOption('class'),
                        'type' => $sf_par->getConfig()->getOption('type'),
                    ];
                }
            }
        }
        return $result;
    }

    public function getDiff($old, $new, $entity, $joinData=[], $attrs=[]) {
        $repo = $this->em->getRepository($entity);
        $att_settings = $attrs ?: $repo->attSettings(false);
        $diff = [];
        $joinEntityData = $joinData
            ? $this->dataFormat(new $entity, [
                'format_data'=>$joinData,
                'attrs'=>$att_settings,
            ]) : [];
        foreach ($old as $att=>$att_val) {
            if (!array_key_exists($att, $att_settings)) continue;
            $att_params = $att_settings[$att];
            $w_params = $att_params['widget_params'] ?? [];
            if ($att_params['type'] === 'entitylist') {
                if (isset($joinEntityData[$att])) {
                    $new[$att] = $joinEntityData[$att];
                }
                $ch_old = [];
                foreach ($old[$att] as $old_k=>$old_v) {
                    $join_data = $old_v[$att_params['join_field']];
                    $ch_old[$join_data['id']] = $old_v;
                }
                $ch_add = [];
                $ch_del = [];
                $ch_change = [];
                foreach ($new[$att] as $ch_k=>$ch_v) {
                    $join_data_id = $ch_v[$att_params['join_field']]['id'];
                    $join_data_name = $ch_v[$att_params['join_field']][array_keys($att_params['main_fields'])['0']];
                    if (isset($ch_old[$join_data_id])) {
                        $changed = [];
                        foreach ($att_params['extra_fields'] as $ex=>$ex_title) {
                            if ($ch_old[$join_data_id][$ex] !== $ch_v[$ex]) {
                                $changed[$ex_title] = [
                                    'old' => $ch_old[$join_data_id][$ex],
                                    'new' => $ch_v[$ex],
                                ];
                            }
                        }
                        if (!empty($changed)) {
                            $ch_change[$join_data_name] = $changed;
                        }
                        unset($ch_old[$join_data_id]);
                    } else {
                        $add_data = [];
                        foreach ($att_params['extra_fields'] as $ex=>$ex_title) {
                            $add_data[$ex_title] = $ch_v[$ex];
                        }
                        $ch_add[$join_data_name] = $add_data;
                    }
                }
                foreach ($ch_old as $del_v) {
                    $ch_del[] = $del_v[$att_params['join_field']][array_keys($att_params['main_fields'])['0']];
                }
                $ch_diff = [];
                if (!empty($ch_add)) {
                    $ch_diff['add'] = $ch_add;
                }
                if (!empty($ch_change)) {
                    $ch_diff['change'] = $ch_change;
                }
                if (!empty($ch_del)) {
                    $ch_diff['del'] = $ch_del;
                }
                if (!empty($ch_diff)) {
                    $ch_diff['title'] = $att_params['label'];
                    $ch_diff['type'] = $att_params['type'];
                    $diff[$att] = $ch_diff;
                }
            } elseif ($att_params['type'] === 'entity' and isset($w_params['expanded'])) {
                $ch_add = array_values(array_diff($new[$att], $old[$att]));
                $ch_del = array_values(array_diff($old[$att], $new[$att]));
                $ch_diff = [];
                if (!empty($ch_add)) {
                    $ch_diff['add'] = $ch_add;
                }
                if (!empty($ch_del)) {
                    $ch_diff['del'] = $ch_del;
                }
                if (!empty($ch_diff)) {
                    $ch_diff['title'] = $att_params['label'];
                    $ch_diff['type'] = $att_params['type'];
                    $diff[$att] = $ch_diff;
                }
            } else {
                if ($att_val !== $new[$att]) {
                    $diff[$att] = [
                        'title' => $att_params['label'],
                        'type' => $att_params['type'],
                        'old' => $att_val,
                        'new' => $new[$att],
                    ];
                }
            }
        }
        return $diff;
    }

    public function dataFormat($entityObj, $params=[]) {
        $entityClass = get_class($entityObj);
        $check_permissions = array_key_exists('check_permissions', $params)
            ? $params['check_permissions'] : true;
        $repo = $this->em->getRepository($entityClass);
        $meta = $this->em->getClassMetadata($entityClass);
        $entity_fields = array_flip($meta->getFieldNames());
        if (isset($params['attrs'])) {
            $att = $params['attrs'];
        } else {
            $att = $repo->attSettings($check_permissions);
        }
        $show_list = $params['show_list'] ?? false;
        $show_single = $params['show_single'] ?? false;
        $is_filter = $params['is_filter'] ?? false;
        $format_data = $params['format_data'] ?? [];
        $is_grid = $params['is_grid'] ?? false;
        $is_print = $params['is_print'] ?? false;
        $relations_keys = $params['relations_keys'] ?? false;
        $data = [];
        $data_full = [];
        foreach ($att as $field=>$field_params) {
            if (isset($entity_fields[$field])) {
                unset($entity_fields[$field]);
            }
            if (isset($params['format_fields']) and !in_array($field, $params['format_fields'])) {
                continue;
            }
            $show_list_att = $field_params['show_list'] ?? false;
            $load_list_att = $field_params['load_list'] ?? true;
            $show_single_att = $field_params['show_single'] ?? true;
            $show_print_att = $field_params['show_print'] ?? true;
            $ignore_format = $field_params['ignore_format'] ?? false;
            $type = $field_params['type'];
            $widget = $field_params['widget'] ?? null;
            $filter_widget = $field_params['filter_widget'] ?? $widget;
            if ($show_list and !$show_list_att and !$load_list_att) continue;
            if ($show_single and !$show_single_att) continue;
            if ($is_print and !$show_print_att) continue;
            if ($ignore_format) continue;
            $valueformat = $field_params['valueformat'] ?? null;
            $is_property = $this->accessor->isReadable($entityObj, $field);
            $allowed = ($type === 'joinfield');
            if (!$is_property and !$allowed) continue;
            if (isset($field_params['get_method'])) {
                $origval = $repo->{$field_params['get_method']}($entityObj);
            } elseif ($is_filter and array_key_exists($field, $format_data)) {
                $origval = array_key_exists('val', $format_data[$field])
                    ? $format_data[$field]['val'] : $format_data[$field];
            } elseif (array_key_exists($field, $format_data)) {
                $origval = $format_data[$field];
            } else {
                $origval = $is_property ? $this->accessor->getValue($entityObj, $field) : null;
            }
            $fmt_params = $params;
            if (!isset($fmt_params['value_format']) and isset($field_params['value_format'])) {
                if ($is_filter and ($type === 'date' or $type === 'datetime' or $type === 'datetimesec')) {

                } else {
                    $fmt_params['value_format'] = $field_params['value_format'];
                }
            }
            $newval = null;
            if ($is_filter) {
                $widget = $filter_widget;
            }
            if (!is_null($origval) or $allowed) {
                if (isset($field_params['format_method'])) {
                    $newval = $repo->{$field_params['format_method']}($origval);
                } elseif ($widget === 'daterange' or $widget === 'datetimerange') {
                    if ($is_filter) {
                        if (is_null($origval['since']) or is_null($origval['until'])) {
                            $newval = null;
                        } else {
                            $newval = $origval;
                            $newval['since'] = $this->fmt->format($type, $origval['since'], $fmt_params);
                            $newval['until'] = $this->fmt->format($type, $origval['until'], $fmt_params);
                        }
                    } else {
                        $newval = $this->fmt->format($type, $origval, $fmt_params);
                    }
                } elseif ($widget === 'numrange') {
                    if ($is_filter) {
                        if (is_null($origval['from']) or is_null($origval['to'])) {
                            $newval = null;
                        } else {
                            $newval = $origval;
                            $newval['from'] = $this->fmt->format($type, $origval['from'], $fmt_params);
                            $newval['to'] = $this->fmt->format($type, $origval['to'], $fmt_params);
                        }
                    } else {
                        $newval = $this->fmt->format($type, $origval, $fmt_params);
                    }
                } elseif ($type === 'datetimesec') {
                    $newval = $this->fmt->format($type, $origval, $fmt_params);
                } elseif ($type === 'datetime') {
                    $newval = $this->fmt->format($type, $origval, $fmt_params);
                } elseif ($type === 'date') {
                    $newval = $this->fmt->format($type, $origval, $fmt_params);
                } elseif ($type === 'time') {
                    $newval = $origval->format('H:i');
                } elseif ($type === 'entity') { // many-to-one
                    if ($widget === 'subform') {
                        continue;
                    }
                    if ($widget === 'multiselectautocomplete') {
                        // many-to-many
                        $value_att = $field_params['value_field'] ?? 'id';
                        $label_att = $field_params['label_field'];
                        if ($is_filter or $relations_keys) {
                            // for filter we need only id
                            $label_att = 'id';
                        }

                        $newval = [];
                        foreach ($origval as $o_v) {
                            $value_data = $this->accessor->getValue($o_v, $value_att);
                            $newval[] = $this->accessor->getValue($o_v, $label_att);
                            if (isset($field_params['data_full'])) {
                                foreach ($field_params['data_full'] as $d_f) {
                                    $data_full[$field][$value_data][$d_f] = $this->accessor->getValue($o_v, $d_f);
                                }
                            }
                        }
                    } elseif ($widget === 'subformadd') {
                        $format_fields = $field_params['format_fields'] ?? [];
                        $j_format = $field_params['format_params'] ?? [];
                        $conn_att_settings = $field_params['att_settings'] ?? [];
                        $delimiter_field = $j_format['delimiter_field'] ?? ', ';
                        $get_label = isset($j_format['get_label']) ? boolval($j_format['get_label']) : false;
                        $delimiter_label = $j_format['delimiter_label'] ?? ': ';
                        $list_data = [];
                        foreach ($origval as $connDoc) {
                            // format
                            $fmt_conn = $this->dataFormat($connDoc, [
                                'attrs'=>$conn_att_settings,
                                'check_permissions'=>$check_permissions,
                            ]);
                            if ($valueformat === 'list') {
                                $j_f_val = [];
                                foreach ($format_fields as $f_label=>$f_field) {
                                    if (!isset($fmt_conn[$f_field])) continue;
                                    $conn_label = (is_int($f_label) and isset($conn_att_settings[$f_field]))
                                        ? $conn_att_settings[$f_field]['label'] : $f_label;
                                    $j_f_val[] = ($get_label ? $conn_label.$delimiter_label : '').$fmt_conn[$f_field];
                                }
                                if (!empty($j_f_val)) {
                                    $list_data[] = implode($delimiter_field, $j_f_val);
                                }
                            } elseif ($valueformat === 'table') {
                                $result_data = ['id'=>[
                                    'entity'=>$connDoc->getId()
                                ], 'view'=>[]];
                                foreach ($format_fields as $f_label=>$f_field) {
                                    $f_val = $fmt_conn[$f_field] ?? '';
                                    $conn_label = (is_int($f_label) and isset($conn_att_settings[$f_field]))
                                        ? $conn_att_settings[$f_field]['label'] : $f_label;
                                    $result_data['view'][$conn_label] = $fmt_conn[$f_field];
                                }
                                $list_data[] = $result_data;
                            }
                        }
                        $newval = json_encode($list_data, JSON_UNESCAPED_UNICODE);
                    } else {
                        if ($widget === 'hidden') { // only get id
                            $newval = $origval;
                        } else {
                            $w_params = $field_params['widget_params'] ?? [];
                            $w_label = $w_params['choice_label'] ?? null;
                            if (!$w_label and isset($field_params['label_field'])) {
                                $w_label = $field_params['label_field'];
                            }
                            if ($is_filter or $relations_keys) {
                                // for filter we need only id
                                $w_label = 'id';
                            }
                            if (isset($w_params['expanded'])) { // many-to-many
                                $newval = [];
                                foreach ($origval as $singleElem) {
                                    $newval[] = $this->accessor->getValue($singleElem, $w_label);
                                    if (isset($field_params['data_full'])) {
                                        $d_fv = [];
                                        foreach ($field_params['data_full'] as $d_f) {
                                            $d_fv[$d_f] = $this->accessor->getValue($singleElem, $d_f);
                                        }
                                        $data_full[$field][] = $d_fv;
                                    }
                                }
                            } else {
                                if ($w_label and $this->accessor->isReadable($origval, $w_label)) {
                                    $newval = $this->accessor->getValue($origval, $w_label);
                                    if (isset($field_params['data_full'])) {
                                        foreach ($field_params['data_full'] as $d_f) {
                                            $data_full[$field][$d_f] = $this->accessor->getValue($origval, $d_f);
                                        }
                                    }
                                }
                            }
                        }
                    }
                } elseif ($type === 'entitylist') {
                    if (!empty($origval)) {
                        if ($is_filter) {
                            $list_data = [];
                            if ($filter_widget === 'select' && $origval) {
                                // single value
                                if (is_object($origval) and method_exists($origval, 'getId')) {
                                    $list_data[] = $origval->getId();
                                } else {
                                    $list_data[] = $origval;
                                }
                            } else {
                                foreach ($origval as $singleElem) {
                                    if (is_object($singleElem) and method_exists($singleElem, 'getId')) {
                                        $list_data[] = $singleElem->getId();
                                    } else {
                                        $list_data[] = $singleElem;
                                    }
                                }
                            }
                            $newval = $list_data ?: null;
                        } elseif ($is_grid) {
                            $list_data = [];
                            foreach ($origval as $singleElem) {
                                if ($valueformat === 'list') {
                                    $joinObj = $this->accessor->getValue($singleElem, $field_params['join_field']);
                                    if (!$joinObj) continue;
                                    foreach ($field_params['main_fields'] as $m_f=>$m_t) {
                                        if (isset($field_params['main_fields_format'])
                                            and isset($field_params['main_fields_format'][$m_f])) {
                                            if (in_array('print', $field_params['main_fields_format'][$m_f]) and !$is_print) {
                                                continue;
                                            }
                                        }
                                        $list_data[] = $this->accessor->getValue($joinObj, $m_f);
                                    }
                                } else {
                                    $result_data = ['id'=>[
                                        'junction'=>$singleElem->getId(),
                                        'entity'=>$joinObj->getId()
                                    ], 'view'=>[]];
                                    foreach ($field_params['main_fields'] as $m_f=>$m_t) {
                                        if (isset($field_params['main_fields_format'])
                                            and isset($field_params['main_fields_format'][$m_f])) {
                                            if (in_array('print', $field_params['main_fields_format'][$m_f]) and !$is_print) {
                                                continue;
                                            }
                                        }
                                        $result_data['view'][$m_t] = $this->accessor->getValue($joinObj, $m_f);
                                    }
                                    $extra_fields = $field_params['extra_fields'] ?? [];
                                    foreach ($extra_fields as $x_f=>$x_t) {
                                        $result_data['view'][$x_t] = $this->accessor->getValue($singleElem, $x_f);
                                    }
                                    $list_data[] = $result_data;
                                }
                            }
                            $newval = json_encode($list_data, JSON_UNESCAPED_UNICODE);
                        } elseif ($relations_keys) {
                            $list_data = [];
                            foreach ($origval as $singleElem) {
                                $joinObj = $this->accessor->getValue($singleElem, $field_params['join_field']);
                                if (!$joinObj) continue;
                                $join_data = [$field_params['join_field']=>$joinObj->getId()];
                                foreach (array_keys($field_params['extra_fields']) as $x_f) {
                                    $join_data[$x_f] = $this->accessor->getValue($singleElem, $x_f);
                                }
                                $list_data[] = $join_data;
                            }
                            $newval = $list_data;
                        } else {
                            $list_data = [];
                            foreach ($origval as $singleElem) {
                                $extra_fields = $field_params['extra_fields'] ?? [];
                                $single_data = ['id'=>$singleElem->getId()];
                                foreach (array_keys($extra_fields) as $ex_f) {
                                    $single_data[$ex_f] = $this->accessor->getValue($singleElem, $ex_f);
                                }
                                $joinObj = $this->accessor->getValue($singleElem, $field_params['join_field']);
                                if (!$joinObj) continue;
                                $join_data = ['id'=>$joinObj->getId()];
                                foreach (array_keys($field_params['main_fields']) as $m_f) {
                                    if (isset($field_params['main_fields_format'])
                                        and isset($field_params['main_fields_format'][$m_f])) {
                                        if (in_array('print', $field_params['main_fields_format'][$m_f]) and !$is_print) {
                                            continue;
                                        }
                                    }
                                    $join_data[$m_f] = $this->accessor->getValue($joinObj, $m_f);
                                }
                                $single_data[$field_params['join_field']] = $join_data;
                                $list_data[] = $single_data;
                            }
                            $newval = $list_data;
                        }
                    } else {
                        $newval = '';
                    }
                } elseif ($type === 'joinfield') {
                    if ($is_filter) {
                        if (is_null($origval)) continue;
                        $newval = $this->fmt->format((count($field_params['join_field_data']) === 1
                            and isset($field_params['join_field_data']['0']['type']))
                            ? $field_params['join_field_data']['0']['type'] : 'string', $origval, $fmt_params);
                    } else {
                        $newval = '';
                        if ($this->accessor->isReadable($entityObj, $field_params['join_field'])) {
                            $join_data = $this->accessor->getValue($entityObj, $field_params['join_field']);
                            if ($join_data instanceof Collection) {
                                $join_data = $join_data->last(); // for versioning data - get last
                            }
                            if ($join_data) {
                                $join_data_val = [];
                                foreach ($field_params['join_field_data'] as $j_f_data) {
                                    $j_f_value = $this->accessor->getValue($join_data, $j_f_data['field']);
                                    if (count($field_params['join_field_data']) === 1 and !$is_grid) {
                                        // single field original value
                                        $join_data_val[$j_f_data['field']] = $j_f_value;
                                    } else {
                                        $join_data_val[$j_f_data['field']] = $this->fmt->format(
                                            $j_f_data['type'] ?? 'string', $j_f_value, $fmt_params
                                        );
                                    }
                                }
                                if (count($join_data_val) === 1) { // for single data - just print it value
                                    $newval = array_values($join_data_val)['0'];
                                } else { // custom format
                                    $join_f_p = $field_params['join_att_settings'];
                                    $j_format = $field_params['join_field_format'] ?? [];
                                    $j_format_tp = $j_format['type'] ?? 'concat';
                                    if ($j_format_tp === 'concat') { // strings concatenation
                                        $delimiter_field = $j_format['delimiter_field'] ?? ', ';
                                        $delimiter_label = $j_format['delimiter_label'] ?? ': ';
                                        $j_f_val = [];
                                        foreach ($field_params['join_field_data'] as $j_f_data) {
                                            if (isset($join_f_p[$j_f_data['field']]) and $join_data_val[$j_f_data['field']]) {
                                                $j_f_label = $j_f_data['label'] ?? $join_f_p[$j_f_data['field']]['label'] ?? $j_f_data['field'];
                                                $j_f_val[] = $j_f_label.$delimiter_label.$join_data_val[$j_f_data['field']];
                                            }
                                        }
                                        $newval = implode($delimiter_field, $j_f_val);
                                    }
                                }
                            } else {
                                if ($widget === 'date') {
                                    $newval = null;
                                }
                            }
                        }
                    }
                } elseif ($type === 'json') {
                    if ($is_filter) {
                        $newval = $origval;
                    } elseif ($is_grid) {
                        $w_params = $field_params['widget_params'] ?? [];
                        if (isset($w_params['choices'])) {
                            $choices = array_flip($w_params['choices']);
                            $val_arr = [];
                            foreach ($origval as $val_data) {
                                if (isset($choices[$val_data])) {
                                    $val_arr[] = $choices[$val_data];
                                }
                            }
                            $newval = json_encode($val_arr, JSON_UNESCAPED_UNICODE);
                        } else {
                            $newval = json_encode($origval, JSON_UNESCAPED_UNICODE);
                        }
                    } else {
                        $newval = $origval;
                    }
                } elseif ($type === 'boolean') {
                    if ($is_filter) {
                        $newval = $origval;
                    } elseif ($is_grid) {
                        $yes_val = $field_params['yes_val'] ?? $this->translator->trans('Yes', [], 'crud_table');
                        $no_val = $field_params['no_val'] ?? $this->translator->trans('No', [], 'crud_table');
                        $newval = $origval ? $yes_val : $no_val;
                    } else {
                        $newval = $origval;
                    }
                } else {
                    $newval = $origval;
                }
            }
            if ($is_filter) {
                if (isset($format_data[$field]) and !is_null($newval)) {
                    if (array_key_exists('val', $format_data[$field])) {
                        $format_data[$field]['val'] = $newval;
                    } else {
                        $format_data[$field] = $newval;
                    }
                    if (!$format_data[$field]) {
                        continue;
                    }
                    $format_data[$field]['att'] = [$field];
                    if (isset($field_params['filter_type'])) {
                        $format_data[$field]['type'] = $field_params['filter_type'];
                    }
                    if (isset($field_params['filter_ftype'])) {
                        $format_data[$field]['ftype'] = $field_params['filter_ftype'];
                    }
                    $data[] = $format_data[$field];
                }
            } else {
                $att_names = $fmt_params['att_names'] ?? false;
                if ($att_names) {
                    $data[$field_params['label']] = $newval;
                } else {
                    $data[$field] = $newval;
                }
            }
        }
        if (!$is_filter and !$is_print and !$show_single and !isset($fmt_params['format_fields'])) {
            foreach (array_keys($entity_fields) as $ef) {
                if ($this->accessor->isReadable($entityObj, $ef)) {
                    $data[$ef] = $this->accessor->getValue($entityObj, $ef);
                }
            }
            $data['_dataFull'] = $data_full;
        }
        return $data;
    }

    public function getFormJoinData(Form $form, $entityObj, $params=[]) {
        $repository = $this->em->getRepository(get_class($entityObj));
        $att_settings = $params['attrs'] ?? $repository->attSettings();
        $res = [];
        foreach ($form as $f_name=>$f_par) {
            $f_params = $att_settings[$f_name];
            $tp = $f_params['type'] ?? 'string';
            if ($tp === 'entitylist') {
                $f_data = $f_par->getData();
                $res[$f_name] = $f_data;
            }
        }
        return $res;
    }

    public function applyForm(Form $form, $entityObj, $attrs=[], $action='add') {
        $repository = $this->em->getRepository(get_class($entityObj));
        $att_settings = $attrs ?: $repository->attSettings();
        foreach ($att_settings as $f_name=>$f_params) {
            if ($action === 'edit' and array_key_exists('change', $f_params) and !$f_params['change']) {
                continue;
            }
            $f_par = $form[$f_name] ?? null;
            $f_data = null;
            $check_null = true;
            if ($f_par) {
                $check_null = true;
                $f_data = $f_par->getViewData();
                $widget = $f_params['widget'] ?? null;
                $tp = $f_params['type'] ?? 'string';
                if ($tp === 'entity') {
                    $f_data = $f_par->getData();
                    $check_null = false;
                    if ($widget === 'subform') {
                        continue;
                    }
                }
                if ($tp === 'entitylist') {
                    continue; // skip because we will manage one-to-many connections manually after save main entity
                }
                if ($tp === 'boolean') {
                    $f_data = (bool)$f_data;
                }
                if ($tp === 'int') {
                    $f_data = $f_data ? (int)$f_data : null;
                    $check_null = false;
                }
                if ($widget === 'password') {
                    $f_data = $f_par->getData(); // because password value not rendered in form
                    if ($action === 'edit') {
                        if (!$f_params['show_edit']) {
                            continue;
                        }
                        // for password - not change if empty
                        if (!$f_data) {
                            continue;
                        }
                    }
                }
                if ($tp === 'date' or $tp === 'datetime' or $tp === 'time') {
                    $f_data = $f_par->getData();
                }
                if ($action === 'add' and !$f_data and isset($f_params['default'])) {
                    $f_data = $f_params['default'];
                }
            } else {
                if (isset($f_params['default']) and is_null($f_data)) {
                    $f_data = $f_params['default'];
                }
            }
            $is_write = false;
            if ($this->accessor->isWritable($entityObj, $f_name) ) {
                if ($check_null) {
                    if (!is_null($f_data)) {
                        $is_write = true;
                    }
                } else {
                    $is_write = true;
                }
            }
            if ($is_write) {
                try {
                    $this->accessor->setValue($entityObj, $f_name, $f_data);
                } catch(\Symfony\Component\PropertyAccess\Exception\NoSuchPropertyException $e) {
                }
            }
        }
        return $entityObj;
    }

    public function saveEntity($curEntity, $joinData=[], $params=[]) {
        $entityClass = get_class($curEntity);
        $repository = $this->em->getRepository($entityClass);
        $is_versioning = $repository->is_versioning ?? false;
        $att_settings = $params['attrs'] ?? $repository->attSettings();
        $this->em->persist($curEntity);
        $this->em->flush($curEntity);
        $entity_id = $curEntity->getId();
        if (!$entity_id) {
            return false;
        }
        $meta = $this->em->getClassMetadata($entityClass);
        foreach ($joinData as $ex_field=>$ex_data) {
            $f_params = $att_settings[$ex_field];
            $join_field = $f_params['join_field'] ?? 'id';
            $join_mapped_by = $f_params['join_mapped_by'] ?? $meta->associationMappings[$ex_field]['mappedBy'];
            $extra_fields = $f_params['extra_fields'] ?? [];

            $checked = [];
            $to_del = [];
            $curVal = $this->accessor->getValue($curEntity, $ex_field);
            foreach ($curVal as $curValObj) {
                // checked values from database
                $checkedJoinObj = $this->accessor->getValue($curValObj, $join_field);
                if ($checkedJoinObj) {
                    $checked[$checkedJoinObj->getId()] = $curValObj;
                } else {
                    $to_del[] = $curValObj;
                }
            }

            foreach ($ex_data as $junctionObj) {
                $junctionJoinObj = $this->accessor->getValue($junctionObj, $join_field);
                if ($junctionJoinObj) { // checked now
                    $curJoinObj = $checked[$junctionJoinObj->getId()] ?? null;
                    $this->accessor->setValue($junctionObj, $join_mapped_by, $curEntity);
                    if ($curJoinObj) { // already exists
                        // check extra fields
                        foreach ($extra_fields as $e_f=>$e_t) {
                            $old_val = $this->accessor->getValue($curJoinObj, $e_f);
                            $new_val = $this->accessor->getValue($junctionObj, $e_f);
                            if ($new_val !== $old_val) {
                                $this->accessor->setValue($curJoinObj, $e_f, $new_val);
                            }
                        }
                    } else {
                        $curEntity->addEquipmentType($junctionObj);
                    }
                    if ($is_versioning) {
                        // to persist new entity with higher version
                        $junctionObj = clone $junctionObj;
                    }
                    $this->em->persist($junctionObj);
                    $this->em->flush($junctionObj);
                }
            }
            // delete unchecked
            if (!$is_versioning) {
                foreach ($to_del as $delObj) {
                    $this->em->remove($delObj);
                    $this->em->flush($delObj);
                }
            }
        }
        return true;
    }

    public function saveObj($obj, $action='', $origData=[], $joinData=[], $attrs=[], $forced=false) {
        $entity = get_class($obj);
        $repo = $this->em->getRepository($entity);
        // validate entity
        $errors = $this->validator->validate($obj, null, ['Default', $action]);
        $result = ['status'=>'info', 'message'=>$this->translator->trans('There were no changes', [], 'crud_form')];
        if (count($errors) > 0) {
            $statmsg = $this->translator->trans('Database validation error', [], 'crud_form').':<br/>';
            foreach ($errors as $error) {
                $statmsg .= $error->getMessage().'<br/>';
            }
            return ['status'=>'error', 'message'=>$statmsg];
        } else {
            // no errors - save entity in database
            $conn = $this->em->getConnection();
            // detect changes
            if (!empty($origData)) {
                if ($forced) {
                    $is_changed_entity = true;
                } else {
                    $newData = $this->dataFormat($obj, ['attrs'=>$attrs]);
                    $diff = $this->getDiff($origData, $newData, $entity, $joinData, $attrs);
                    $is_changed_entity = !empty($diff);
                }
            } else {
                $is_changed_entity = true;
            }
            $is_changed_entity = ($forced or $is_changed_entity);
            $is_changed = $is_changed_entity;
            if ($action === 'edit') {
                if (!$is_changed) {
                    return $result;
                }
            }
            if ($action === 'add' or $is_changed) {
                $conn->beginTransaction();
                try {
                    if ($is_changed_entity) {
                        if (property_exists($repo, 'is_versioning') and $repo->is_versioning) {
                            if ($action === 'add') {
                                $max_id = $repo->getMaxId();
                                $max_id++; // for new entity
                                $obj->setId($max_id);
                            }
                            if ($action === 'edit') {
                                $version = $obj->getVersion() + 1;
                                $obj = clone $obj;
                                $obj->setVersion($version);
                            }
                        }
                    }
                    if ($is_changed_entity) {
                        $this->saveEntity($obj, $joinData, ['attrs'=>$attrs]);
                    }
                    $conn->commit();
                    $statmsg = ($action === 'add')
                        ? $this->translator->trans('Added successfully', [], 'crud_form')
                        : $this->translator->trans('Attributes updated successfully', [], 'crud_form');
                    $result = ['status'=>'success', 'message'=>$statmsg];
                    if ($action === 'add') {
                        $eventAdded = new ObjectAddedEvent($obj);
                        $this->dispatcher->dispatch($eventAdded, ObjectAddedEvent::NAME);
                    } elseif ($action === 'edit' and $is_changed) {
                        $eventChanged = new ObjectChangedEvent($obj);
                        $this->dispatcher->dispatch($eventChanged, ObjectChangedEvent::NAME);
                    }
                } catch(\Doctrine\DBAL\DBALException $e) {
                    $conn->rollBack();
                    $result = ['status'=>'error', 'message'=>$this->translator->trans('Error saving data', [], 'crud_form').' - '.$e->getMessage()];
                }
            }
        }
        return $result;
    }

    public function uploadFiles(string $folder, array $files) {
        $files_dir = implode(DIRECTORY_SEPARATOR, [
            $this->params->get('kernel.project_dir'), 'upload', $folder
        ]).DIRECTORY_SEPARATOR;
        if (!file_exists($files_dir)) {
            if (@!mkdir($files_dir, 0755, true)) {
                return ['status'=>'error', 'message'=>$this->translator->trans('unable to create folder', [], 'crud_form')];
            }
        }
        try {
            foreach ($files as $file) {
                $mime = $file['file']->getMimeType();
                // remove EXIF tags
                if (explode('/',$mime)[0] === 'image') {
                    $filePath = $file['file']->getRealPath();
                    $filePathTmp = $filePath.'noexif';
                    @ImageHelper::removeExif($filePath, $filePathTmp);
                    if (file_exists($filePathTmp)) {
                        unlink($filePath);
                        rename($filePathTmp, $filePath);
                    }
                }
                $file['file']->move($files_dir, $file['name']);
            }
        } catch (FileException $e) {
            return ['status'=>'error', 'message'=>$e->getMessage()];
        }
        return ['status'=>'success', 'message'=>$this->translator->trans('files uploaded successfully', [], 'crud_form')];
    }
}
