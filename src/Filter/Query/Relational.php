<?php

namespace Ecode\CRUDBundle\Filter\Query;

use Ecode\CRUDBundle\Filter\BaseQuery;

class Relational extends BaseQuery
{
    const FILTER_TYPE = 'relational';

    public function search(string $word, array $att, ?string $alias=null): bool {
        if (!$word) return false;
        $alias = $alias ?? $this->alias;
        $keyword = trim($word);
        if ($keyword !== '') {
            if ((mb_substr($keyword, 0, 1) === '"' or mb_substr($keyword, 0, 1) === "'")
                and mb_substr($keyword, 0, 1) === mb_substr($keyword, -1, 1)) {
                // get string from quotes
                $keywords = [mb_substr($keyword, 1, -1)];
            } else {
                // split by spaces
                $keywords = explode(' ', $keyword);
            }
        }
        $idx = 0;
        foreach ($keywords as $keyw) {
            $idx++;
            $keyw = trim($keyw); // remove extra spaces from the beginning and end of the line
            $keyw = stripslashes($keyw); // remove character escaping
            if (!$keyw) continue;
            $search_fields = [];
            $search_col_param = '';
            foreach ($att as $field=>$field_conf) {
                $search = $field_conf['search'] ?? true;
                $type = $field_conf['type'];
                $widget = $field_conf['widget'] ?? 'text';
                if (!$search) continue;
                if ($type === 'string') {
                    $search_col_param .= "UPPER(".$alias.".".$field.") LIKE UPPER(:".$alias."_search_word".$idx.") OR ";
                } elseif ($type === 'entity') {
                    if ($widget === 'subformadd') {
                        $subform_att_settings = $field_conf['att_settings'] ?? [];
                        foreach ($subform_att_settings as $f=>$f_v) {
                            $subform_search = $f_v['search'] ?? true;
                            if (!$subform_search) continue;
                            $search_col_param .= "UPPER(".$field.".".$f.") LIKE UPPER(:".$alias."_search_word".$idx.") OR ";
                        }
                    } else {
                        $w_params = $field_conf['widget_params'] ?? [];
                        $w_label = $w_params['choice_label'] ?? null;
                        if (!$w_label and isset($field_conf['label_field'])) {
                            $w_label = $field_conf['label_field'];
                        }
                        if (isset($field_conf['search_fields'])) {
                            foreach ($field_conf['search_fields'] as $s_f) {
                                $search_col_param .= "UPPER(".$field.".".$s_f.") LIKE UPPER(:".$alias."_search_word".$idx.") OR ";
                            }
                        } else {
                            $search_col_param .= "UPPER(".$field.".".$w_label.") LIKE UPPER(:".$alias."_search_word".$idx.") OR ";
                        }
                    }
                } elseif ($type === 'joinfield') {
                    foreach ($field_conf['join_field_data'] as $j_f) {
                        $search_col_param .= "UPPER(".$field_conf['join_field'].".".$j_f['field'].") LIKE UPPER(:".$alias."_search_word".$idx.") OR ";
                    }
                }
            }
            $search_col_param = substr($search_col_param, 0, -4);
            $this->qb->andWhere($search_col_param)->setParameter($alias.'_search_word'.$idx, '%'.$keyw.'%');
        }
        return true;
    }

    public function null(string $field, ?string $alias=null): bool {
        $alias = $alias ?? $this->alias;
        $this->qb->andWhere($alias.".".$field." IS NULL OR ".$alias.".".$field."=''");
        return true;
    }

    public function string(string $field, array $filter_data, ?string $alias=null): bool {
        if (!isset($filter_data['val'])) return false;
        $mod = $filter_data['mod'] ?? 'eq';
        $value = $filter_data['val'] ?? '';
        $res = false;
        $dqlData = $this->getStringDql($field, $value, $mod, $alias);
        if ($dqlData) {
            $this->qb->andWhere($dqlData['dql']);
            foreach ($dqlData['params'] as $param) {
                $this->qb->setParameter(...$param);
            }
            $res = true;
        }
        return $res;
    }

    private function getStringDql($field, $value, $mod, $alias) {
        $alias = $alias ?? $this->alias;
        $prefix = in_array($mod, ['neq', 'ncon']) ? 'NOT ' : '';
        $dql = '';
        $params = [];
        if ($mod === 'eq' or $mod === 'neq') { // exact value comparison
            $dql = $prefix.$alias.".".$field." = :filter_".$alias.'_'.$field;
            $params[] = ['filter_'.$alias.'_'.$field, $value];
        } elseif ($mod === 'con' or $mod === 'ncon') { // comparison on entry of substring
            $dql = $prefix."UPPER(".$alias.".".$field.") LIKE UPPER(:filter_".$alias.'_'.$field.")";
            $params[] = ['filter_'.$alias.'_'.$field, '%'.$value.'%'];
        }
        $result = false;
        if ($dql and $params) {
            $result = [
                'dql' => $dql,
                'params' => $params,
            ];
        }
        return $result;
    }

    public function stringOR(array $fields, array $filter_data, ?string $alias=null): bool {
        if (!isset($filter_data['val'])) return false;
        $mod = $filter_data['mod'] ?? 'eq';
        $value = $filter_data['val'] ?? '';
        $res = false;
        $dql = [];
        $params = [];
        foreach ($fields as $field) {
            $dqlData = $this->getStringDql($field, $value, $mod, $alias);
            if ($dqlData) {
                $dql[] = $dqlData['dql'];
                $params = array_merge($params, $dqlData['params']);
            }
        }
        if ($dql and $params) {
            $this->qb->andWhere(implode(' OR ', $dql));
            foreach ($params as $param) {
                $this->qb->setParameter(...$param);
            }
            $res = true;
        }
        return $res;
    }

    public function number(string $field, array $filter_data, ?string $alias=null): bool {
        if (!isset($filter_data['val'])) return false;
        $mod = $filter_data['mod'] ?? 'eq';
        $value = $filter_data['val'] ?? '';
        $alias = $alias ?? $this->alias;
        $opt = $this->mod_compare[$mod] ?? '=';
        $this->qb->andWhere("TRIM(".$alias.".".$field.")+0 ".$opt." :filter_".$alias.'_'.$field)
            ->setParameter('filter_'.$alias.'_'.$field, $value);
        return true;
    }

    public function numberRange(string $field, array $filter_data, ?string $alias=null): bool {
        if (!isset($filter_data['from']) or !isset($filter_data['to'])) return false;
        $mod = $filter_data['mod'] ?? 'eq';
        $from = $filter_data['from'] ?? '';
        $to = $filter_data['to'] ?? '';
        $alias = $alias ?? $this->alias;
        $this->qb->andWhere(($mod === 'neq' ? 'NOT ' : '').
            "(TRIM(".$alias.".".$field.")+0 >= :filter_".$alias.'_'.$field."_from".
            " AND TRIM(".$alias.".".$field.")+0 <= :filter_".$alias.'_'.$field."_to)")
            ->setParameter('filter_'.$alias.'_'.$field.'_from', $from)
            ->setParameter('filter_'.$alias.'_'.$field.'_to', $to);
        return true;
    }

    public function datetime(string $field, array $filter_data, ?string $alias=null): bool {
        if (!isset($filter_data['val'])) return false;
        $mod = $filter_data['mod'] ?? 'eq';
        $value = $filter_data['val'] ?? '';
        $alias = $alias ?? $this->alias;
        $opt = $this->mod_compare[$mod] ?? '=';
        $this->qb->andWhere($alias.".".$field." ".$opt." :filter_".$alias.'_'.$field)
            ->setParameter('filter_'.$alias.'_'.$field, new \DateTime($value));
        return true;
    }

    public function dateRange(string $field, array $filter_data, ?string $alias=null): bool {
        if (!isset($filter_data['since']) or !isset($filter_data['until'])) return false;
        $mod = $filter_data['mod'] ?? 'eq';
        $since = $filter_data['since'] ?? '';
        $until = $filter_data['until'] ?? '';
        $alias = $alias ?? $this->alias;
        $this->qb->andWhere(($mod === 'neq' ? 'NOT ' : '').
            "(".$alias.".".$field." >= :filter_".$alias.'_'.$field."_since".
            " AND ".$alias.".".$field." <= :filter_".$alias.'_'.$field."_until)")
            ->setParameter('filter_'.$alias.'_'.$field.'_since', new \DateTime($since))
            ->setParameter('filter_'.$alias.'_'.$field.'_until', new \DateTime($until));
        return true;
    }

    public function dateRangeFixed(string $field, array $filter_data, ?string $alias=null): bool {
        if (!isset($filter_data['val'])) return false;
        $mod = $filter_data['mod'] ?? 'eq';
        $value = $filter_data['val'] ?? '';
        $alias = $alias ?? $this->alias;

        $filter = "";
        if ($value === 'today') {
            $filter = "DATE_DIFF(CURRENT_DATE() , ".$alias.".".$field.") = 0";
        } elseif ($value === 'week') {
            $filter= "DATE_DIFF(CURRENT_DATE() , ".$alias.".".$field.") <= 7".
                " AND DATE_DIFF(CURRENT_DATE() , ".$alias.".".$field.") >= 0";
        } elseif ($value === 'fweek') {
            $filter= "DATE_DIFF(CURRENT_DATE() , ".$alias.".".$field.") >= -7".
                " AND DATE_DIFF(CURRENT_DATE() , ".$alias.".".$field.") <  0";
        } elseif ($value === 'curweek') {
            $filter =   $alias.".".$field." >= '".(new \DateTime('monday this week'))->format('Y-m-d')."'".
                " AND ".$alias.".".$field." <= '".(new \DateTime('sunday this week'))->format('Y-m-d')."'";
        } elseif ($value === 'month') {
            $filter =   $alias.".".$field." >= '".(new \DateTime('-1 month'))->format('Y-m-d')."'".
                " AND ".$alias.".".$field." <= CURRENT_DATE()";
        } elseif ($value === 'fmonth') {
            $filter =   $alias.".".$field." <= '".(new \DateTime('+1 month'))->format('Y-m-d')."'".
                " AND ".$alias.".".$field." >  CURRENT_DATE()";
        } elseif ($value === 'curmonth') {
            $filter =   $alias.".".$field." >= '".(new \DateTime('first day of this month'))->format('Y-m-d')."'".
                " AND ".$alias.".".$field." <= '".(new \DateTime('last day of this month'))->format('Y-m-d')."'";
        } elseif ($value === 'quarter') {
            $filter =   $alias.".".$field." >= '".(new \DateTime('-3 month'))->format('Y-m-d')."'".
                " AND ".$alias.".".$field." <= CURRENT_DATE()";
        } elseif ($value === 'fquarter') {
            $filter =   $alias.".".$field." <= '".(new \DateTime('+3 month'))->format('Y-m-d')."'".
                " AND ".$alias.".".$field." >  CURRENT_DATE()";
        } elseif ($value === 'curquarter') {
            $filter =   $alias.".".$field." >= '".
                (new \DateTime("first day of -".((date('n')%3)-1)." month midnight"))->format('Y-m-d')."'".
                " AND ".$alias.".".$field." <= '".
                (new \DateTime("last day of +".(3-(date('n')%3))." month midnight"))->format('Y-m-d')."'";
        } elseif ($value === 'halfyear') {
            $filter =   $alias.".".$field." >= '".(new \DateTime('-6 month'))->format('Y-m-d')."'".
                " AND ".$alias.".".$field." <= CURRENT_DATE()";
        } elseif ($value === 'ninemonth') {
            $filter =   $alias.".".$field." >= '".(new \DateTime('-9 month'))->format('Y-m-d')."'".
                " AND ".$alias.".".$field." <= CURRENT_DATE()";
        } elseif ($value === 'year') {
            $filter =   $alias.".".$field." >= '".(new \DateTime('-1 year'))->format('Y-m-d')."'".
                " AND ".$alias.".".$field." <= CURRENT_DATE()";
        } elseif ($value === 'fyear') {
            $filter =   $alias.".".$field." <= '".(new \DateTime('+1 year'))->format('Y-m-d')."'".
                " AND ".$alias.".".$field." >  CURRENT_DATE()";
        } elseif ($value === 'curyear') {
            $filter =   $alias.".".$field." >= '".(new \DateTime())->format('Y')."-01-01'".
                " AND ".$alias.".".$field." <= '".(new \DateTime())->format('Y')."-12-31'";
        }
        if ($filter) {
            $this->qb->andWhere(($mod === 'neq' ? 'NOT ' : '')."(".$filter.")");
            return true;
        } else {
            return false;
        }
    }

    public function boolean(string $field, array $filter_data, ?string $alias=null): bool {
        $alias = $alias ?? $this->alias;
        if (!isset($filter_data['val'])
            or ($filter_data['val']!=='y' and $filter_data['val']!=='n')) return false;
        $this->qb->andWhere($alias.".".$field." = ".($filter_data['val']==='y' ? '1' : '0'));
        return true;
    }

    public function single(string $field, array $filter_data, ?string $alias=null): bool {
        $alias = $alias ?? $this->alias;
        if (!isset($filter_data['val'])) return false;
        $mod = $filter_data['mod'] ?? 'eq';
        $value = $filter_data['val'] ?? '';
        $this->qb->andWhere($alias.".".$field.(($mod === 'eq') ? ' = ' : ' != ').":filter_".$alias.'_'.$field)
            ->setParameter('filter_'.$alias.'_'.$field, $value);
        return true;
    }

    public function multiple(string $field, array $filter_data, ?string $alias=null): bool {
        $value = $filter_data['val'] ?? [];
        $alias = $alias ?? $this->alias;
        if (empty($value)) return false;
        $method = (isset($filter_data['mod']) and $filter_data['mod'] === 'ncon') ? 'notIn' : 'in';
        $this->qb->andWhere($this->qb->expr()->$method($alias.".".$field, $value));
        return true;
    }

    public function multipleEntity(string $field, array $filter_data, ?string $alias=null): bool {
        $value = $filter_data['val'] ?? [];
        if (empty($value)) return false;
        $att = $this->repo->attSettings();
        $f_params = $att[$field];
        $f_field = 'id';
        $this->qb->andWhere($this->qb->expr()->in($field.'.'.$f_field, $value));
        return true;
    }

    public function jsonList(string $field, array $filter_data, ?string $alias=null): bool {
        $value = $filter_data['val'] ?? [];
        $alias = $alias ?? $this->alias;
        if (empty($value)) return false;
        $search_col_param = '';
        foreach ($value as $k=>$v) {
            $search_col_param .= "UPPER(".$alias.".".$field.") LIKE UPPER(:filter_".$alias."_".$field."_".$k.") OR ";
        }
        $search_col_param = substr($search_col_param, 0, -4);
        $this->qb->andWhere($search_col_param);
        foreach ($value as $k=>$v) {
            $this->qb->setParameter('filter_'.$alias.'_'.$field.'_'.$k, '%'.$v.'%');
        }
        return true;
    }
}
