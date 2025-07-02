<?php
namespace Bga\Games\DeadMenTellNoTales;

use Bga\Games\DeadMenTellNoTales\Game;
use BgaUserException;

if (!function_exists('getUsePerTurn')) {
    function getUsePerTurn(string $itemId, Game $game)
    {
        $dailyUseItems = $game->gameData->get('dailyUseItems');
        return array_key_exists($itemId, $dailyUseItems) ? $dailyUseItems[$itemId] : 0;
    }
    function usePerTurn(string $itemId, Game $game)
    {
        $dailyUseItems = $game->gameData->get('dailyUseItems');
        $dailyUseItems[$itemId] = array_key_exists($itemId, $dailyUseItems) ? $dailyUseItems[$itemId] + 1 : 1;
        $game->gameData->set('dailyUseItems', $dailyUseItems);
    }
    function subtractPerTurn(string $itemId, Game $game)
    {
        $dailyUseItems = $game->gameData->get('dailyUseItems');
        $dailyUseItems[$itemId] = array_key_exists($itemId, $dailyUseItems) ? $dailyUseItems[$itemId] - 1 : 0;
        $game->gameData->set('dailyUseItems', $dailyUseItems);
        $game->markChanged('token');
        $game->markChanged('player');
    }
    function resetPerTurn(Game $game)
    {
        $game->gameData->set('dailyUseItems', []);
        $game->markChanged('token');
        $game->markChanged('player');
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
