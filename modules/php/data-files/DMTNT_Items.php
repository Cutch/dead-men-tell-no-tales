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
                'onCalculateFires' => function (Game $game, $item, &$data) {
                    if ($item['isActive'] && getUsePerTurn('bucket', $game) == 0) {
                        $moveList = $game->map->getValidAdjacentTiles($data['x'], $data['y']);
                        $currentTile = $data['currentTile'];
                        array_walk($moveList, function ($firstTile) use ($currentTile, &$data, $game) {
                            if ($currentTile && !$game->map->testTouchPoints($currentTile, $firstTile)) {
                                return;
                            }
                            if ($firstTile['fire'] > 0) {
                                $data['fireList'][] = $firstTile['id'];
                            }
                        });
                    }
                },
                'onFightFire' => function (Game $game, $item, &$data) {
                    if ($item['isActive']) {
                        $moveList = $game->map->getAdjacentTiles(...$game->getCharacterPos($item['characterId']));
                        if (
                            sizeof(
                                array_filter($moveList, function ($tile) use ($data) {
                                    return $tile['x'] == $data['x'] && $tile['y'] == $data['y'];
                                })
                            ) > 0
                        ) {
                            usePerTurn('bucket', $game);
                        }
                    }
                },
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
                'onGetActionCost' => function (Game $game, $item, &$data) {
                    if ($item['isActive'] && $data['action'] == 'actMove' && getUsePerTurn('compass', $game) == 0) {
                        $data['actions'] = 0;
                    }
                },
                'onMove' => function (Game $game, $item, &$data) {
                    if ($item['isActive'] && getUsePerTurn('compass', $game) == 0) {
                        usePerTurn('compass', $game);
                    }
                },
            ],
            'dagger' => [
                'type' => 'item',
                'name' => clienttranslate('Dagger'),
                'actions' => 1,
                'onGetActionCost' => function (Game $game, $item, &$data) {
                    if ($item['isActive'] && $data['action'] == 'actEliminateDeckhand' && getUsePerTurn('dagger', $game) == 0) {
                        $data['actions'] = 0;
                    }
                },
                'onEliminateDeckhands' => function (Game $game, $item, &$data) {
                    if ($item['isActive'] && getUsePerTurn('dagger', $game) == 0) {
                        usePerTurn('dagger', $game);
                    }
                },
            ],
            'pistol' => [
                'type' => 'item',
                'name' => clienttranslate('Pistol'),
                'actions' => 1,
                // Attack from adjacent rooms
                'skills' => [
                    'skill1' => [
                        'type' => 'skill',
                        'state' => ['playerTurn'],
                        'name' => clienttranslate('Pistol Attack'),
                        'actions' => 1,
                        'onUse' => function (Game $game, $skill, &$data) {
                            $game->gameData->set('battle', ['includeAdjacent' => true]);
                            usePerTurn('pistol', $game);
                            $game->nextState('battleSelection');
                        },
                        'requires' => function (Game $game, $skill) {
                            $char = $game->character->getCharacterData($skill['characterId']);
                            return $char['isActive'] && sizeof($game->getEnemies(true)) > 0 && getUsePerTurn('pistol', $game) == 0;
                        },
                    ],
                ],
            ],
            'rum' => [
                'type' => 'item',
                'name' => clienttranslate('Rum'),
                'actions' => 0,
                'onGetActionCost' => function (Game $game, $item, &$data) {
                    if ($item['isActive'] && $data['action'] == 'actRest' && getUsePerTurn('rum', $game) == 0) {
                        $data['actions'] = 0;
                    }
                },
                'onRest' => function (Game $game, $item, &$data) {
                    if ($item['isActive'] && getUsePerTurn('rum', $game) == 0) {
                        usePerTurn('rum', $game);
                    }
                },
            ],
            'sword' => [
                'type' => 'item',
                'name' => clienttranslate('Sword'),
                'actions' => 0,
                'onGetAttack' => function (Game $game, $item, &$data) {
                    if ($item['isActive']) {
                        $data['attack']++;
                    }
                },
            ],
        ];
        return $data;
    }
}
