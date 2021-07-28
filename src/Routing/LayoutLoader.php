<?php

namespace Ecode\CRUDBundle\Routing;

use Symfony\Component\Config\Loader\Loader;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Serializer\Encoder\XmlEncoder;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpKernel\Config\FileLocator;
use Symfony\Component\Finder\Finder;

class LayoutLoader extends Loader
{
    private $isLoaded = false;
    private $params;
    private $fileLocator;

    public function __construct(
        ParameterBagInterface $params,
        FileLocator $fileLocator
    ) {
        $this->params = $params;
        $this->fileLocator = $fileLocator;
    }

    public function load($resource, $type = null) {
        if (true === $this->isLoaded) {
            throw new \RuntimeException('Do not add the "layout" loader twice');
        }

        $routes = new RouteCollection();

        // find layouts config in bundles
        $bundles_meta = $this->params->get('kernel.bundles_metadata');
        $bundles_crud = [];
        $finder = new Finder();
        $encoder = new XmlEncoder();
        $layout_data = [];
        foreach ($bundles_meta as $bundle_name=>$bundle_meta) {
            $dir_conf = implode(DIRECTORY_SEPARATOR, [
                $bundle_meta['path'], 'Resources', 'config', 'crud'
            ]);
            if (file_exists($dir_conf)) {
                $finder->ignoreUnreadableDirs()->files()->name('*.xml')->in($dir_conf);
                foreach ($finder as $file) {
                    if (!isset($layout_data[$bundle_name])) $layout_data[$bundle_name] = [];
                    if (!isset($layout_data[$bundle_name]['layout'])) $layout_data[$bundle_name]['layout'] = [];
                    $val = $encoder->decode($file->getContents(), 'xml', ['as_collection'=>true]);
                    if (is_array($val) and !empty($val) and isset($val['layout'])) {
                        foreach ($val['layout'] as $l_v) {
                            $layout_data[$bundle_name]['layout'][] = $l_v;
                        }
                    }
                }
            }
        }
        $app_layout_dir = implode(DIRECTORY_SEPARATOR, [
            $this->params->get('kernel.project_dir'), 'config', 'crud'
        ]);
        if (file_exists($app_layout_dir)) {
            $finder->ignoreUnreadableDirs()->files()->name('*.xml')->in($app_layout_dir);
            foreach ($finder as $file) {
                if (!isset($layout_data['App'])) $layout_data['App'] = [];
                if (!isset($layout_data['App']['layout'])) $layout_data['App']['layout'] = [];
                $val = $encoder->decode($file->getContents(), 'xml', ['as_collection'=>true]);
                if (is_array($val) and !empty($val) and isset($val['layout'])) {
                    foreach ($val['layout'] as $l_v) {
                        $layout_data['App']['layout'][] = $l_v;
                    }
                }
            }
        }

        // generate routes for each action
        $nameConv = new CamelCaseToSnakeCaseNameConverter;
        foreach ($layout_data as $bundle=>$layout_val) {
            if (!isset($layout_val['layout'])) continue;
            foreach ($layout_val['layout'] as $layout) {
                $layout_id = $layout['@id'] ?? null;
                $entity = $layout['@entity'] ?? null;
                // generate layout id - bundle name + entity class name
                if (!$layout_id and $entity) {
                    $layout_id = $nameConv->normalize(
                        (($bundle === 'App') ? '' : preg_replace('/Bundle$/', '', $bundle)).
                        array_values(array_slice(explode('\\', $entity), -1))[0]
                    );
                }

                $actions = [];

                $param_extra = [];
                if (isset($layout['param'])) {
                    foreach ($layout['param'] as $param) {
                        $param_extra[$param['@name']] = $param['#'];
                    }
                }
                foreach ($layout['action'] as $action) {
                    if (isset($action['@path'])) {
                        $path = $action['@path'];
                    } else {
                        if (isset($layout['@path'])) {
                            $path = $layout['@path'].'/'.$action['@type'];
                        } else {
                            $path = '/'.str_replace('_', '/', $layout_id).'/'.$action['@type'];
                        }
                    }
                    $action_conf = [
                        'path' => $path,
                        'route' => $action['@route'] ?? $layout_id.'_'.$action['@type'],
                        'action' => $action,
                        'controller' => $action['@controller'] ?? null,
                        'list_controller' => $action['@list_controller'] ?? null, // for list
                        'paginate_controller' => $action['@paginate_controller'] ?? null, // for list
                        'filter_controller' => $action['@filter_controller'] ?? null, // for list
                        'template' => $action['@template'] ?? null,
                        'template_path' => $action['@template_path'] ?? $layout['@template_path'] ?? '@CRUD/object',
                        'template_prefix' => $action['@template_prefix'] ?? $layout['@template_prefix'] ?? '',
                        'add_route' => $action['@add_route'] ?? null,
                        'edit_route' => $action['@edit_route'] ?? null,
                        'view_route' => $action['@view_route'] ?? null,
                        'delete_route' => $action['@delete_route'] ?? null,
                        'history_route' => $action['@history_route'] ?? null,
                        'list_route' => $action['@list_route'] ?? null,
                    ];
                    if (!empty($param_extra)) {
                        $action_conf['extra'] = $param_extra;
                    }
                    $actions[$action['@type']][] = $action_conf;
                }
                foreach ($actions as $action_type=>$action_data) {
                    foreach ($action_data as $action_params) {
                        $template_path = $action_params['template_path'];
                        $template_prefix = $action_params['template_prefix'];
                        if ($action_type === 'list') {
                            $tmpl_attr = $action_params['template'] ?? 'list';
                            // route for pagination ajax requests
                            $list_param = [
                                '_controller' => $action_params['list_controller'] ?? (isset($layout['@controller'])
                                ? $layout['@controller'].'::list' : 'Ecode\CRUDBundle\Controller\CRUDController::list'),
                                'entity' => $entity,
                                'layout_id' => $layout_id,
                                'layout' => $action_params['action'],
                                'page' => 1,
                                'perPage' => null,
                                'template' => $template_path.'/'.$tmpl_attr.'.html.twig',
                                'template_prefix' => $template_prefix,
                                'add_route' => $action_params['add_route'] ?? isset($actions['add']) ? $actions['add']['0']['route'] : '',
                                'edit_route' => $action_params['edit_route'] ?? isset($actions['edit']) ? $actions['edit']['0']['route'] : '',
                                'view_route' => $action_params['view_route'] ?? isset($actions['view']) ? $actions['view']['0']['route'] : '',
                                'delete_route' => $action_params['delete_route'] ?? isset($actions['delete']) ? $actions['delete']['0']['route'] : '',
                                'filter_route' => $action_params['route'].'_filter',
                                'history_route' => $action_params['history_route'] ?? isset($actions['history']) ? $actions['history']['0']['route'] : '',
                            ];
                            if (isset($action_params['extra'])) {
                                foreach ($action_params['extra'] as $ex_k=>$ex_v) {
                                    $list_param[$ex_k] = $ex_v;
                                }
                            }
                            $list_param_valid = [
                                'page' => '\d+',
                                'perPage' => '\d+',
                            ];
                            $paginatePath = $layout['@path'] ?? '/'.str_replace('_', '/', $layout_id);
                            $paginateRoute = new Route(
                                $paginatePath.'/paginate/{page}/{perPage}',
                                array_replace_recursive($list_param, [
                                    '_controller' => $action_params['paginate_controller'] ?? (isset($layout['@controller'])
                                    ? $layout['@controller'].'::paginate' : 'Ecode\CRUDBundle\Controller\CRUDController::paginate'),
                                ]), $list_param_valid);
                            $paginateRoute->setMethods(['GET','POST']);
                            $routes->add($layout_id.'_paginate', $paginateRoute);
                            // main route
                            $route = new Route($action_params['path'].'/{page}/{perPage}', $list_param,
                                $list_param_valid);
                            $route->setMethods(['GET']);
                            $routes->add($action_params['route'], $route);
                            if (isset($layout['@path'])) {
                                $route = new Route($layout['@path'].'/{page}/{perPage}', $list_param,
                                    $list_param_valid);
                                $route->setMethods(['GET']);
                                $routes->add($layout_id, $route);
                            }
                            // filter
                            $filter_param = [
                                '_controller' => $action_params['filter_controller'] ?? (isset($layout['@controller'])
                                    ? $layout['@controller'].'::filter'
                                    : 'Ecode\CRUDBundle\Controller\FilterController::filter'),
                                'entity' => $entity,
                                'layout_id' => $layout_id,
                                'template' => $template_path.'/filter.html.twig',
                                'template_prefix' => $template_prefix,
                                'route' => $action_params['route'].'_filter',
                                'list_route' => $action_params['route'],
                                'field' => null,
                            ];
                            if (isset($action_params['extra'])) {
                                foreach ($action_params['extra'] as $ex_k=>$ex_v) {
                                    $filter_param[$ex_k] = $ex_v;
                                }
                            }
                            // route for filter
                            $filterRoute = new Route($action_params['path'].'/filter', $filter_param);
                            $filterRoute->setMethods(['GET', 'POST']);
                            $routes->add($action_params['route'].'_filter', $filterRoute);
                        } elseif ($action_type === 'add') {
                            $tmpl_attr = $action_params['template'] ?? 'form';
                            $add_param = [
                                '_controller' => $action_params['controller'] ?? (isset($layout['@controller'])
                                ? $layout['@controller'].'::manage'
                                : 'Ecode\CRUDBundle\Controller\CRUDController::manage'),
                                'entity' => $entity,
                                'layout_id' => $layout_id,
                                'template' => $template_path.'/'.$tmpl_attr.'.html.twig',
                                'template_prefix' => $template_prefix,
                                'action' => $action_type,
                                'layout' => $action_params['action'],
                                'route' => $action_params['route'],
                                'redirect_route' => $action_params['redirect_route'] ?? isset($actions['list']) ? $actions['list']['0']['route'] : '',
                                'id' => null, // prevent error
                            ];
                            if (isset($action_params['extra'])) {
                                foreach ($action_params['extra'] as $ex_k=>$ex_v) {
                                    $add_param[$ex_k] = $ex_v;
                                }
                            }
                            // route for adding new object
                            $route = new Route($action_params['path'], $add_param);
                            $route->setMethods(['GET', 'POST']);
                            $routes->add($action_params['route'], $route);
                        } elseif ($action_type === 'edit') {
                            $tmpl_attr = $action_params['template'] ?? 'form';
                            $edit_param = [
                                '_controller' => $action_params['controller'] ?? (isset($layout['@controller'])
                                ? $layout['@controller'].'::manage'
                                : 'Ecode\CRUDBundle\Controller\CRUDController::manage'),
                                'entity' => $entity,
                                'layout_id' => $layout_id,
                                'template' => $template_path.'/'.$tmpl_attr.'.html.twig',
                                'template_prefix' => $template_prefix,
                                'action' => $action_type,
                                'layout' => $action_params['action'],
                                'route' => $action_params['route'],
                                'redirect_route' => $action_params['redirect_route'] ?? isset($actions['list']) ? $actions['list']['0']['route'] : '',
                            ];
                            if (isset($action_params['extra'])) {
                                foreach ($action_params['extra'] as $ex_k=>$ex_v) {
                                    $edit_param[$ex_k] = $ex_v;
                                }
                            }
                            $list_param_valid = [
                                'id' => '\d+',
                            ];
                            // route for edit object by id
                            $route = new Route($action_params['path'].'/{id}', $edit_param, $list_param_valid);
                            $route->setMethods(['GET', 'POST']);
                            $routes->add($action_params['route'], $route);
                        } elseif ($action_type === 'view') {
                            $tmpl_attr = $action_params['template'] ?? 'view';
                            $view_param = [
                                '_controller' => $action_params['controller'] ?? (isset($layout['@controller'])
                                ? $layout['@controller'].'::view'
                                : 'Ecode\CRUDBundle\Controller\CRUDController::view'),
                                'entity' => $entity,
                                'layout_id' => $layout_id,
                                'template' => $template_path.'/'.$tmpl_attr.'.html.twig',
                                'template_prefix' => $template_prefix,
                                'layout' => $action_params['action'],
                                'edit_route' => $action_params['edit_route'] ?? isset($actions['edit']) ? $actions['edit']['0']['route'] : '',
                            ];
                            if (isset($action_params['extra'])) {
                                foreach ($action_params['extra'] as $ex_k=>$ex_v) {
                                    $view_param[$ex_k] = $ex_v;
                                }
                            }
                            $view_param_valid = [
                                'id' => '\d+',
                            ];
                            // route for view object by id
                            $route = new Route($action_params['path'].'/{id}', $view_param, $view_param_valid);
                            $route->setMethods(['GET']);
                            $routes->add($action_params['route'], $route);
                        } elseif ($action_type === 'history') {
                            $tmpl_attr = $action_params['template'] ?? 'history';
                            $history_param = [
                                '_controller' => $action_params['controller'] ?? (isset($layout['@controller'])
                                ? $layout['@controller'].'::history'
                                : 'Ecode\CRUDBundle\Controller\HistoryController::history'),
                                'entity' => $entity,
                                'layout_id' => $layout_id,
                                'template' => $template_path.'/'.$tmpl_attr.'.html.twig',
                                'template_prefix' => $template_prefix,
                                'layout' => $action_params['action'],
                            ];
                            if (isset($action_params['extra'])) {
                                foreach ($action_params['extra'] as $ex_k=>$ex_v) {
                                    $history_param[$ex_k] = $ex_v;
                                }
                            }
                            $list_param_valid = [
                                'id' => '\d+',
                            ];
                            // route for edit object by id
                            $route = new Route($action_params['path'].'/{id}', $history_param, $list_param_valid);
                            $route->setMethods(['GET']);
                            $routes->add($action_params['route'], $route);
                        } elseif ($action_type === 'delete') {
                            $delete_params = [
                                '_controller' => $action_params['controller'] ?? (isset($layout['@controller'])
                                ? $layout['@controller'].'::delete'
                                : 'Ecode\CRUDBundle\Controller\CRUDController::delete'),
                                'entity' => $entity,
                                'layout_id' => $layout_id,
                                'route' => $action_params['route'],
                                'redirect_route' => $action_params['redirect_route'] ?? isset($actions['list']) ? $actions['list']['0']['route'] : '',
                            ];
                            if (isset($action_params['extra'])) {
                                foreach ($action_params['extra'] as $ex_k=>$ex_v) {
                                    $delete_params[$ex_k] = $ex_v;
                                }
                            }
                            // route for delete object by id
                            $route = new Route($action_params['path'].'/{id}', $delete_params, [
                                'id' => '\d+',
                            ]);
                            $route->setMethods(['DELETE']);
                            $routes->add($action_params['route'], $route);
                        }
                    }
                }
            }
        }

        $this->isLoaded = true;

        return $routes;
    }

    public function supports($resource, $type = null) {
        return 'layout' === $type;
    }
}
