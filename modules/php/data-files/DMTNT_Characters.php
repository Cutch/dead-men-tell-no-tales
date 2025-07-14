<?php
namespace Bga\Games\DeadMenTellNoTales;

use Bga\Games\DeadMenTellNoTales\Game;
use BgaUserException;
class DMTNT_CharactersData
{
    public function getData(): array
    {
        return [
            'lamore' => [
                'type' => 'character',
                'actions' => '6',
                'name' => 'Lydia Lamore',
                'color' => '#89357d',
            ],
            'garrett' => [
                'type' => 'character',
                'actions' => '5',
                'name' => 'Black Gus Garrett',
                'color' => '#3c464c',
                // Garret can run and force deckhands to flee
                'onMovePre' => function (Game $game, $char, &$data) {
                    if ($char['isActive']) {
                        $tile = $data['tile'];
                        $moves = $game->map->calculateMoves();
                        if (array_key_exists($tile['id'], $moves['paths'])) {
                            $paths = $moves['paths'][$tile['id']];
                            if (sizeof($paths) >= 2) {
                                $fatigueList = [];
                                foreach ($paths as $path) {
                                    $fatigueList[$path['id']] = $path['cost'];
                                }
                                $game->selectionStates->initiateState(
                                    'characterMovement',
                                    [
                                        'id' => 'gusMovement',
                                        'characterId' => $game->character->getTurnCharacterId(),
                                        'moves' => $fatigueList,
                                        'title' => clienttranslate('Which path do you take'),
                                    ],
                                    $game->character->getTurnCharacterId(),
                                    false,
                                    'playerTurn',
                                    clienttranslate('Which room do you pass through?'),
                                    true
                                );
                                $data['interrupt'] = true;
                            } elseif (sizeof($paths) == 1) {
                                $data['path'] = $paths[0]['id'];
                            }
                        }
                    }
                },
                'onMoveSelection' => function (Game $game, $char, &$data) {
                    $selectionState = $game->selectionStates->getState('characterMovement');
                    if ($selectionState['id'] == 'gusMovement') {
                        $state = $game->actInterrupt->getState('actMove');
                        $pathTile = $game->map->getTileByXY($data['x'], $data['y']);
                        $state['data']['path'] = $pathTile['id'];
                        $game->actInterrupt->setState('actMove', $state);
                    }
                },
                'onMoveFinalize' => function (Game $game, $char, &$data) {
                    if ($char['isActive']) {
                        $makeCrewFlee = function (Game $game, $targetTile, $tokenPositions, $pathTile) {
                            $targetTiles = toId($game->map->getValidAdjacentTiles($pathTile['x'], $pathTile['y']));
                            $xy = $game->map->xy($pathTile['x'], $pathTile['y']);
                            if (array_key_exists($xy, $tokenPositions)) {
                                $crew = [];
                                array_walk($tokenPositions[$xy], function ($token) use (&$crew, $xy, $game) {
                                    if (!$token['isTreasure']) {
                                        $deckType = $game->data->getTreasure()[$token['token']]['deckType'];
                                        if ($deckType === 'crew' || $deckType === 'captain') {
                                            $crew[] = $token;
                                        }
                                    }
                                });
                                foreach ($crew as $crewToken) {
                                    $game->selectionStates->initiateState(
                                        'crewMovement',
                                        [
                                            'movePositions' => $targetTiles,
                                            'id' => 'moveCrew',
                                            'crew' => $crewToken,
                                            'currentPosId' => $game->map->xy($pathTile['x'], $pathTile['y']),
                                            'targetTile' => $targetTile,
                                        ],
                                        $game->character->getTurnCharacterId(),
                                        true,
                                        'playerTurn',
                                        clienttranslate('Make them flee'),
                                        true
                                    );
                                }
                            }
                        };
                        $targetTile = $game->map->getTileByXY($data['x'], $data['y']);
                        $tokenPositions = $game->gameData->get('tokenPositions');

                        if (array_key_exists('path', $data)) {
                            $pathTile = $game->map->getTileById($data['path']);
                            $makeCrewFlee($game, $targetTile, $tokenPositions, $pathTile);
                        }
                        $pathTile = $game->map->getTileByXY($data['x'], $data['y']);
                        $makeCrewFlee($game, $targetTile, $tokenPositions, $pathTile);
                    }
                },
            ],
            'flynn' => [
                'type' => 'character',
                'actions' => '5',
                'name' => 'Crimson Flynn',
                'color' => '#cd402a',
                'onCalculateFires' => function (Game $game, $char, &$data) {
                    if ($char['isActive']) {
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
            ],
            'whitebeard' => [
                'type' => 'character',
                'actions' => '5',
                'name' => 'Whitebeard',
                'color' => '#ece9e8',
                'onCalculateMoves' => function (Game $game, $char, &$data) {
                    if ($char['isActive']) {
                        $fatigueList = &$data['fatigueList'];
                        array_walk($fatigueList, function (&$cost, $k) {
                            $cost = max($cost - 1, 0);
                        });
                    }
                },
            ],
            'jade' => [
                'type' => 'character',
                'actions' => '5',
                'name' => 'Jade',
                'color' => '#4a9746',
                'onGetDeckhandTargetCount' => function (Game $game, $char, &$data) {
                    if ($char['isActive']) {
                        $data['count'] = 2;
                    }
                },
            ],
            'titian' => [
                'type' => 'character',
                'actions' => '5',
                'name' => 'Five-Fingered Titian',
                'color' => '#dc9d29',
                'skills' => [
                    'skill1' => [
                        'type' => 'skill',
                        'name' => clienttranslate('Pick 1'),
                        'state' => ['interrupt'],
                        'interruptState' => ['revenge'],
                        'onDrawRevenge' => function (Game $game, $skill, &$data) {
                            $game->actInterrupt->addSkillInterrupt($skill);
                        },
                        'onUseSkill' => function (Game $game, $skill, &$data) {
                            if ($data['skillId'] == $skill['id']) {
                                $existingData = $game->actInterrupt->getState('actDraw');
                                if (array_key_exists('data', $existingData)) {
                                    $deck = $existingData['data']['deck'];
                                    $card1 = $existingData['data']['card'];
                                    $card2 = $game->decks->pickCard($deck);
                                    $game->incStat(1, 'cards_drawn', $game->character->getSubmittingCharacter()['playerId']);
                                    $data['interrupt'] = true;
                                    $game->selectionStates->initiateState(
                                        'cardSelection',
                                        [
                                            'cards' => [$card1, $card2],
                                            'id' => $skill['id'],
                                        ],
                                        $game->character->getTurnCharacterId(),
                                        false
                                    );
                                }
                            }
                        },
                        'onCardSelection' => function (Game $game, $skill, &$data) {
                            $state = $game->selectionStates->getState('cardSelection');
                            if ($state && $state['id'] == $skill['id']) {
                                $discardCard = array_values(
                                    array_filter($state['cards'], function ($card) use ($data) {
                                        return $card['id'] != $data['cardId'];
                                    })
                                )[0];
                                $game->cardDrawEvent($discardCard, $discardCard['deck']);

                                $drawState = $game->actInterrupt->getState('actDraw');
                                $drawState['data']['card'] = $game->decks->getCard($data['cardId']);
                                $game->actInterrupt->setState('actDraw', $drawState);
                                $game->actInterrupt->actInterrupt($skill['id']);
                                $data['nextState'] = false;
                            }
                        },
                        'requires' => function (Game $game, $skill) {
                            $char = $game->character->getCharacterData($skill['characterId']);
                            return $char['isActive'];
                        },
                    ],
                ],
            ],
            'fallen' => [
                'type' => 'character',
                'actions' => '5',
                'name' => 'Cobalt Fallen',
                'color' => '#008cb9',
                'onCalculateMovesHasTreasure' => function (Game $game, $char, &$data) {
                    if ($char['isActive']) {
                        $data['hasTreasure'] = false;
                    }
                },
            ],
        ];
    }
}
