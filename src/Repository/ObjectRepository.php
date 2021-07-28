<?php

namespace Ecode\CRUDBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;

abstract class ObjectRepository extends ServiceEntityRepository
{
    use FormatColumnsTrait;

    public $sort_desc = false;
    public $sort_by = 'id';
    public $is_versioning = false;

    public function listFilter(object &$filterQuery, array $filter=[]) {
    }
}
