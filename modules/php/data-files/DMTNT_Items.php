<?php
namespace Bga\Games\DeadMenTellNoTales;

use Bga\Games\DeadMenTellNoTales\Game;
use BgaUserException;

if (!function_exists('getUsePerDay')) {
    function getUsePerDay(string $itemId, Game $game)
    {
        $dailyUseItems = $game->gameData->get('dailyUseItems');
        return array_key_exists($itemId, $dailyUseItems) ? $dailyUseItems[$itemId] : 0;
    }
    function usePerDay(string $itemId, Game $game)
    {
        $dailyUseItems = $game->gameData->get('dailyUseItems');
        $dailyUseItems[$itemId] = array_key_exists($itemId, $dailyUseItems) ? $dailyUseItems[$itemId] + 1 : 1;
        $game->gameData->set('dailyUseItems', $dailyUseItems);
    }
    function subtractPerDay(string $itemId, Game $game)
    {
        $dailyUseItems = $game->gameData->get('dailyUseItems');
        $dailyUseItems[$itemId] = array_key_exists($itemId, $dailyUseItems) ? $dailyUseItems[$itemId] - 1 : 0;
        $game->gameData->set('dailyUseItems', $dailyUseItems);
        $game->markChanged('token');
        $game->markChanged('player');
    }
    function resetPerDay(Game $game)
    {
        $game->gameData->set('dailyUseItems', []);
        $game->markChanged('token');
        $game->markChanged('player');
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
    function clearItemSkills(&$skills, $itemId)
    {
        array_walk($skills, function ($v, $k) use (&$skills, $itemId) {
            if ($v['itemId'] == $itemId) {
                unset($skills[$k]);
            }
        });
    }
}
class DMTNT_ItemsData
{
    public function getData(): array
    {
        $data = [
            'bucket' => [
                'type' => 'item',
                'name' => clienttranslate('Bucket'),
                'actions' => 0,
                'skills' => [
                    'skill1' => [
                        'type' => 'item-skill',
                        'name' => clienttranslate('Lower Adjacent Fire Die'),
                        'state' => ['playerTurn'],
                        'perTurn' => 1,
                    ],
                ],
            ],
        ];
        return $data;
    }
}
