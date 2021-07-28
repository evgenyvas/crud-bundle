<?php

namespace Ecode\CRUDBundle\Filter;

abstract class BaseQuery
{
    protected $repo;
    protected $qb;
    protected $meta;
    protected $alias;
    protected $mod_compare = [
        'eq'     => '=',
        'neq'    => '!=',
        'more'   => '>',
        'eqmore' => '>=',
        'less'   => '<',
        'eqless' => '<=',
    ];

    public function init(&$repo, &$qb, &$meta, $alias) {
        $this->repo = $repo;
        $this->qb = $qb;
        $this->meta = $meta;
        $this->alias = $alias;
    }
}
