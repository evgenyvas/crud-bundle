<?php

namespace Ecode\CRUDBundle\Filter;

use Doctrine\ORM\QueryBuilder;

class FilterQuery
{
    private $repo;
    private $qb;
    private $meta;
    private $alias;
    private $query_class = '';
    private $default_query_class = 'relational';
    private $query_objects = [];
    private $disableVersioning = false;
    private $filterManager;

    public function __construct($repo, $meta, $filterManager, $options=[]) {
        $this->repo = $repo;
        $this->alias = $options['alias'] ?? 'flt';
        $this->qb = $repo->createQueryBuilder($this->alias);
        $this->meta = $meta;
        $this->query_class = $this->default_query_class;
        $this->disableVersioning = $options['disableVersioning'] ?? false;
        $this->filterManager = $filterManager;

        $is_versioning = $repo->is_versioning ?? false;
        if ($is_versioning and !$this->disableVersioning) {
            $versionSubquery = $repo->createQueryBuilder($this->alias.'v')
                ->select('MAX('.$this->alias.'v.version)')->where($this->alias.'v.id = '.$this->alias.'.id');
            $this->qb->where($this->alias.'.version = ('.$versionSubquery->getDql().')');
        }
    }

    public function getQueryBuilder(): QueryBuilder {
        return $this->qb;
    }

    public function setSelectId(): self {
        $is_versioning = $this->repo->is_versioning ?? false;
        if ($is_versioning and !$this->disableVersioning) {
            $this->qb->select($this->alias.'.id, '.$this->alias.'.version')->distinct();
        } else {
            $this->qb->select($this->alias.'.id')->distinct();
        }
        return $this;
    }

    public function setSelectCount(): self {
        $this->qb->select('COUNT(DISTINCT '.$this->alias.'.id)');
        return $this;
    }

    public function setOrder(string $sortBy, bool $sortDesc, $alias=null): self {
        $this->qb->orderBy(($alias ?: $this->alias).'.'.$sortBy, $sortDesc ? 'DESC' : 'ASC');
        return $this;
    }

    public function addJoin($field, $alias=null): self {
        if ($alias) {
            $this->qb->leftJoin($alias.'.'.$field, $alias.'_'.$field);
        } else {
            $this->qb->leftJoin($this->alias.'.'.$field, $field);
        }
        return $this;
    }

    public function getQueryClass(): string {
        return $this->query_class;
    }

    public function setQueryClass(string $query_class): self {
        $this->query_class = $query_class;
        return $this;
    }

    public function restoreQueryClass(): self {
        $this->query_class = $this->default_query_class;
        return $this;
    }

    private function &getQueryObject() {
        if (!isset($this->query_objects[$this->query_class])) {
            $newQueryClass = $this->filterManager->getService($this->query_class);
            // init query class
            $newQueryClass->init(
                $this->repo, $this->qb, $this->meta, $this->alias
            );
            $this->query_objects[$this->query_class] = $newQueryClass;
        }
        return $this->query_objects[$this->query_class];
    }

    public function filter(string $method, array $params = []): bool {
        $queryObj = &$this->getQueryObject();
        if (method_exists($queryObj, $method)) {
            return $queryObj->$method(...$params);
        } else {
            return false;
        }
    }
}
