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
                'color' => '#282b2d',
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
                        $state = $game->actInterrupt->getState('_actMove');
                        $pathTile = $game->map->getTileByXY($data['x'], $data['y']);
                        $state['data']['path'] = $pathTile['id'];
                        $game->actInterrupt->setState('_actMove', $state);
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
                                            'currentState' => $game->gamestate->state(true, false, true)['name'],
                                        ],
                                        $game->character->getTurnCharacterId(),
                                        false,
                                        'playerTurn',
                                        clienttranslate('Make them flee'),
                                        true
                                    );
                                }
                            }
                        };
                        $targetTile = $game->map->getTileByXY($data['x'], $data['y']);
                        $tokenPositions = $game->getTokenPositions();

                        if (array_key_exists('path', $data)) {
                            $pathTile = $game->map->getTileById($data['path']);
                            $makeCrewFlee($game, $targetTile, $tokenPositions, $pathTile);
                        }
                        // $pathTile = $game->map->getTileByXY($data['x'], $data['y']);
                        // $makeCrewFlee($game, $targetTile, $tokenPositions, $pathTile);
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
                        $data['addAdjacentTiles']($data);
                    }
                },
            ],
            'whitebeard' => [
                'type' => 'character',
                'actions' => '5',
                'name' => 'Whitebeard',
                'color' => '#8a8a8a',
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
                'color' => '#dd8127',
                'onDrawRevengeCardPre' => function (Game $game, $char, &$data) {
                    if ($char['isActive']) {
                        $card1 = $data['card'];
                        $game->decks->removeFromDeck('revenge', $card1['id']);
                        $card2 = $game->decks->pickCard('revenge', 'hand');
                        $game->decks->addBackToDeck('revenge', $card1['id']);
                        $game->decks->addBackToDeck('revenge', $card2['id']);

                        $game->eventLog(clienttranslate('${character_name} drew ${buttons}'), [
                            'buttons' => notifyButtons([
                                ['name' => $game->decks->getDeckName('revenge'), 'dataId' => $card1['id'], 'dataType' => 'revenge'],
                                ['name' => $game->decks->getDeckName('revenge'), 'dataId' => $card2['id'], 'dataType' => 'revenge'],
                            ]),
                            'character_name' => $game->getCharacterHTML($char['id']),
                        ]);

                        $game->cardDrawEvent($card2, 'revenge');
                        $data['interrupt'] = true;
                        $game->selectionStates->initiateState(
                            'cardSelection',
                            [
                                'cards' => [$card1, $card2],
                                'id' => 'revengeCardSelection',
                            ],
                            $game->character->getTurnCharacterId(),
                            false,
                            'drawRevengeCard',
                            null,
                            true
                        );
                    }
                },
                'onCardSelection' => function (Game $game, $char, &$data) {
                    $state = $game->selectionStates->getState('cardSelection');
                    if ($state && $state['id'] == 'revengeCardSelection') {
                        $discardCard = array_values(
                            array_filter($state['cards'], function ($card) use ($data) {
                                return $card['id'] != $data['cardId'];
                            })
                        )[0];
                        $chosenCard = array_values(
                            array_filter($state['cards'], function ($card) use ($data) {
                                return $card['id'] == $data['cardId'];
                            })
                        )[0];
                        $game->decks->placeCardOnTop('revenge', $discardCard['id']);

                        $state = $game->actInterrupt->getState('stDrawRevengeCard');
                        $state['data']['card'] = $chosenCard;
                        $game->actInterrupt->setState('stDrawRevengeCard', $state);
                    }
                },
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
