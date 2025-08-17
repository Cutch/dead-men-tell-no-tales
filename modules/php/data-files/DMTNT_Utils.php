<?php
namespace Bga\Games\DeadMenTellNoTales;

use Exception;

if (!function_exists('addId')) {
    function array_clone($array)
    {
        return array_map(function ($element) {
            return is_array($element) ? array_clone($element) : (is_object($element) ? clone $element : $element);
        }, $array);
    }
    function clamp($current, $min, $max)
    {
        return max($min, min($max, $current));
    }
    function addId($data)
    {
        array_walk($data, function (&$v, $k) {
            $v['id'] = $k;
            if (array_key_exists('skills', $v)) {
                $array = [];
                array_walk($v['skills'], function ($iv, $ik) use ($k, &$array, $v) {
                    $keyName = $k . $ik;
                    if ($v['type'] == 'character') {
                        $array[$keyName] = ['id' => $keyName, 'characterId' => $v['id'], ...$iv];
                    } elseif ($v['type'] == 'deck') {
                        $array[$keyName] = ['id' => $keyName, 'cardId' => $v['id'], ...$iv];
                    } else {
                        $array[$keyName] = ['id' => $keyName, ...$iv];
                    }
                    $array[$keyName] = [
                        ...$array[$keyName],
                        'parentId' => $v['id'],
                        'parentName' => array_key_exists('name', $v) ? $v['name'] : null,
                    ];
                });
                $v['skills'] = $array;
            }
            if (array_key_exists('track', $v)) {
                array_walk($v['track'], function (&$v, $k) {
                    $v['id'] = $k;
                });
            }
        });

        return $data;
    }
    function array_unique_nested(array $data, string $key)
    {
        return array_values(
            array_reduce(
                $data,
                function ($accumulator, $item) use ($key) {
                    $id = $item[$key]; // Assuming 'id' is the inner key you want to make unique
                    if (!isset($accumulator[$id])) {
                        $accumulator[$id] = $item;
                    }
                    return $accumulator;
                },
                []
            )
        );
    }
    function array_unique_nested_recursive(array $data, string $key)
    {
        $accumulator = [];
        array_walk($data, function (&$items, $k) use ($key, &$accumulator) {
            for ($i = 0; $i < sizeof($items); $i++) {
                $item = $items[$i];
                $id = $item[$key];
                if (isset($accumulator[$id])) {
                    $i--;
                    array_splice($items, $i, 1);
                }
                $accumulator[$id] = true;
            }
        });

        return $data;
    }
    function array_orderby()
    {
        $args = func_get_args();
        $data = array_shift($args);
        foreach ($args as $n => $field) {
            if (is_string($field)) {
                $tmp = [];
                foreach ($data as $key => $row) {
                    $tmp[$key] = $row[$field] ?? '0';
                }
                $args[$n] = $tmp;
            }
        }
        $args[] = &$data;
        call_user_func_array('array_multisort', $args);
        return array_pop($args);
    }
    function array_merge_count(...$arrays)
    {
        $build = [];
        foreach ($arrays as $array) {
            foreach ($array as $k => $v) {
                if (array_key_exists($k, $build)) {
                    $build[$k] += $v;
                } else {
                    $build[$k] = $v;
                }
            }
        }
        return $build;
    }
    function notifyTextButton(array $obj): string
    {
        $name = $obj['name'];
        $dataId = $obj['dataId'];
        $dataType = $obj['dataType'];
        if (!array_search($dataType, ['character', 'item', 'revenge', 'card', 'tile'])) {
            throw new Exception('Bad dataType');
        }
        return "<span class=\"dmtnt__log-button\" data-id=\"$dataId\" data-type=\"$dataType\">$name</span>";
    }
    function notifyButtons($arr): string
    {
        return join(
            '',
            array_map(function ($obj) {
                return notifyTextButton($obj);
            }, $arr)
        );
    }
    // Only works with mysql 8
    // function buildSelectQuery(array $rows)
    // {
    //     $rows = array_values($rows);
    //     $keys = [];
    //     $i = 0;
    //     foreach ($rows[0] as $key => $values) {
    //         array_push($keys, "column_{$i} as $key");
    //         $i++;
    //     }
    //     $values = [];
    //     foreach ($rows as $row) {
    //         $v = [];
    //         foreach ($row as $value) {
    //             if ($value == null) {
    //                 array_push($v, 'NULL');
    //             } else {
    //                 array_push($v, "'{$value}'");
    //             }
    //         }
    //         array_push($values, 'ROW(' . implode(',', $v) . ')');
    //     }
    //     $keys = implode(',', $keys);
    //     $values = implode(',', $values);
    //     return "SELECT $keys FROM (VALUES $values) AS generated_rows";
    // }
    function buildSelectQuery(array $rows)
    {
        $rows = array_values($rows);
        $values = [];
        $rowI = 0;
        foreach ($rows as $row) {
            $v = [];
            foreach ($row as $value) {
                if ($value == null) {
                    array_push($v, 'NULL');
                } else {
                    array_push($v, "'{$value}'");
                }
            }
            if ($rowI === 0) {
                $i = 0;
                foreach ($rows[0] as $key => $x) {
                    $v[$i] = $v[$i] . " AS $key";
                    $i++;
                }
            }
            array_push($values, 'SELECT ' . implode(',', $v));
            $rowI++;
        }
        return implode(' UNION ALL ', $values);
    }
    function buildInsertQuery(string $table, array $rows)
    {
        $keys = [];
        foreach ($rows[0] as $key => $value) {
            array_push($keys, "`{$key}`");
        }
        $values = [];
        foreach ($rows as $row) {
            $v = [];
            foreach ($row as $value) {
                if ($value == null) {
                    array_push($v, 'NULL');
                } else {
                    array_push($v, "'{$value}'");
                }
            }
            array_push($values, '(' . implode(',', $v) . ')');
        }
        $keys = implode(',', $keys);
        $values = implode(',', $values);
        return "INSERT INTO `$table` ($keys) VALUES $values";
    }
    function flatten(array $array)
    {
        $return = [];
        array_walk_recursive($array, function ($a) use (&$return) {
            $return[] = $a;
        });
        return $return;
    }
    function toId(array $array)
    {
        return array_values(
            array_map(function ($d) {
                return $d['id'];
            }, $array)
        );
    }
}
