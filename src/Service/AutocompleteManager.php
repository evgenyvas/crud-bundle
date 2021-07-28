<?php

namespace Ecode\CRUDBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class AutocompleteManager
{
    private $em;
    private $authChecker;
    private $tokenStorage;

    public function __construct(
        EntityManagerInterface $em,
        AuthorizationCheckerInterface $authChecker,
        TokenStorageInterface $tokenStorage
    ) {
        $this->em = $em;
        $this->authChecker = $authChecker;
        $this->tokenStorage = $tokenStorage;
    }

    public function getAutocompleteData(
        $q, $entity, $search, $title=null, $value=null, $res=false, $max_results=10, $label_delimeter=' ',
        $label_key='label', $value_key='value', $fields=[], $filter=[], $fields_join=[]
    ) {
        if ($fields) {
            $cols_arr = [];
            foreach ($fields as $field=>$f_alias) {
                if (isset($fields_join[$field])) {
                    foreach ($f_alias as $f_a=>$f_v) {
                        $cols_arr[] = $fields_join[$field]['alias'].".".$f_a." AS ".$f_v;
                    }
                } else {
                    $cols_arr[] = "u.".$field;
                }
            }
            $cols = implode(', ', $cols_arr).($value ? ", u.".$value : '');
        } else {
            $cols = "u.".$title.($value ? ", u.".$value : '');
        }
        $result = [];
        if ($q) {
            $qb = $this->em->getRepository($entity)->createQueryBuilder('u');
            $qb->select("DISTINCT ".$cols);
            $searchQuery = [];
            foreach ($search as $s) {
                if (isset($fields_join[$s])) continue;
                $searchQuery[] = "UPPER(u.".$s.") LIKE UPPER(:value)";
            }
            $qb->andWhere(implode(' OR ', $searchQuery));
            foreach ($filter as $flt) {
                if ($flt['type'] === 'userfirm') {
                    if (isset($flt['notrole']) and $this->authChecker->isGranted($flt['notrole'])) {
                        continue;
                    }
                    if (isset($flt['role']) and !$this->authChecker->isGranted($flt['role'])) {
                        continue;
                    }
                    $user = $this->tokenStorage->getToken()->getUser();
                    $userfirm = $user->getUserfirm();
                    $qb->andWhere('u.'.$flt['field'].' = '.$userfirm);
                }
            }
            foreach ($fields_join as $f_j=>$f_jv) {
                $qb->leftJoin('u.'.$f_j, $f_jv['alias']);
            }
            if ($value) {
                $qb->orderBy('u.'.$value, 'DESC');
            }
            $query = $qb->setParameter('value', '%'.$q.'%')->setMaxResults($max_results)->getQuery();
            $data = $query->getResult(\Doctrine\ORM\Query::HYDRATE_ARRAY);
            foreach ($data as $val) {
                if ($fields) {
                    $row = [];
                    foreach ($fields as $field=>$f_alias) {
                        if (isset($fields_join[$field])) {
                            foreach ($f_alias as $f_a=>$f_v) {
                                $row[$f_v] = $val[$f_v];
                            }
                        } else {
                            $row[$f_alias] = $val[$field];
                        }
                    }
                    if ($value) {
                        $row[$label_key] = implode($label_delimeter, $row);
                        $row[$value_key] = $val[$value];
                    }
                    $result[] = $row;
                } else {
                    if ($value) {
                        $result[] = [
                            $label_key => $val[$title],
                            $value_key => $val[$value]
                        ];
                    } else {
                        $result[] = $val[$title];
                    }
                }
            }
        }
        return $res ? $result : ['data'=>$result];
    }
}
