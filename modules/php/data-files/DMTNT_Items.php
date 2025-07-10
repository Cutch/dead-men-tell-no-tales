<?php
namespace Bga\Games\DeadMenTellNoTales;

use Bga\Games\DeadMenTellNoTales\Game;
use BgaUserException;

if (!function_exists('getUsePerTurn')) {
    function getUsePerTurn(string $itemId, Game $game)
    {
        $turnUseItems = $game->gameData->get('turnUseItems');
        return array_key_exists($itemId, $turnUseItems) ? $turnUseItems[$itemId] : 0;
    }
    function usePerTurn(string $itemId, Game $game)
    {
        $turnUseItems = $game->gameData->get('turnUseItems');
        $turnUseItems[$itemId] = array_key_exists($itemId, $turnUseItems) ? $turnUseItems[$itemId] + 1 : 1;
        $game->gameData->set('turnUseItems', $turnUseItems);
    }
    function subtractPerTurn(string $itemId, Game $game)
    {
        $turnUseItems = $game->gameData->get('turnUseItems');
        $turnUseItems[$itemId] = array_key_exists($itemId, $turnUseItems) ? $turnUseItems[$itemId] - 1 : 0;
        $game->gameData->set('turnUseItems', $turnUseItems);
        $game->markChanged('token');
        $game->markChanged('player');
    }
    function resetPerTurn(Game $game)
    {
        $game->gameData->set('turnUseItems', []);
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
            'blanket' => [
                'type' => 'item',
                'name' => clienttranslate('Blanket'),
                'actions' => 1,
                // If with flynn, can be used on adjacent rooms
                // Lower fire die by 2 once per turn
            ],
            'compass' => [
                'type' => 'item',
                'name' => clienttranslate('Compass'),
                'actions' => 0,
                'onGetActionCost' => function (Game $game, $char, &$data) {
                    if ($data['action'] == 'actMove' && getUsePerTurn('compass', $game)) {
                        $data['actions'] = 0;
                        usePerTurn('compass', $game);
                    }
                },
            ],
            'dagger' => [
                'type' => 'item',
                'name' => clienttranslate('Dagger'),
                'actions' => 1,
                'onGetActionCost' => function (Game $game, $char, &$data) {
                    if ($data['action'] == 'actEliminateDeckhand' && getUsePerTurn('dagger', $game)) {
                        $data['actions'] = 0;
                        usePerTurn('dagger', $game);
                    }
                },
            ],
            'pistol' => [
                'type' => 'item',
                'name' => clienttranslate('Pistol'),
                'actions' => 1,
                // Attack from adjacent rooms
            ],
            'rum' => [
                'type' => 'item',
                'name' => clienttranslate('Rum'),
                'actions' => 0,
                'onGetActionCost' => function (Game $game, $char, &$data) {
                    if ($data['action'] == 'actRest' && getUsePerTurn('rum', $game)) {
                        $data['actions'] = 0;
                        usePerTurn('rum', $game);
                    }
                },
            ],
            'sword' => [
                'type' => 'item',
                'name' => clienttranslate('Sword'),
                'actions' => 0,
                'onGetAttack' => function (Game $game, $item, &$data) {
                    $data['attack']++;
                },
            ],
        ];
        return $data;
    }
}
