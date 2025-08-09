<?php
declare(strict_types=1);

namespace Bga\Games\DeadMenTellNoTales;

use BgaUserException;
use Exception;

class DMTNT_Battle
{
    private Game $game;

    public function __construct($game)
    {
        $this->game = $game;
    }
    public function addBattleLocation(string $xy, string $nextState = 'playerTurn')
    {
        if (sizeof($this->game->gameData->get('battleLocations')) >= 0) {
            $this->game->gameData->set('battleLocationState', $nextState);
        }
        $this->game->gameData->set('battleLocations', array_unique([...$this->game->gameData->get('battleLocations'), $xy]));
    }
    public function checkBattleLocation(string $xy)
    {
        $enemies = $this->game->getEnemiesByLocation(false, array_values($this->game->map->getXY($xy)));
        if (sizeof($enemies) == 0) {
            $this->game->gameData->set('battleLocations', array_diff($this->game->gameData->get('battleLocations'), [$xy]));
            if (sizeof($this->game->gameData->get('battleLocations')) == 0) {
                $this->game->nextState($this->game->gameData->get('battleLocationState'));
            }
        }
    }
    public function startBattle(int $targetId, ?string $characterId = null, ?string $nextState = 'playerTurn')
    {
        $this->game->character->activateCharacter($characterId ?? $this->game->character->getTurnCharacterId());

        $battle = $this->game->gameData->getBattleData();
        $enemies = $this->game->getEnemies($battle['includeAdjacent'], $characterId);
        $battle = [
            'includeAdjacent' => $battle['includeAdjacent'] || false,
            'target' => array_values(
                array_filter($enemies, function ($d) use ($targetId) {
                    return $d['id'] == $targetId;
                })
            )[0],
            'characterId' => $characterId ?? $this->game->character->getTurnCharacterId(),
            'nextState' => $nextState,
        ];
        $character = $this->game->character->getCharacterData($battle['characterId']);
        $tokens = array_count_values(
            array_map(function ($d) {
                return $d['treasure'];
            }, $character['tokenItems'])
        );
        if (array_key_exists('treasure', $tokens)) {
            $this->game->drop(
                $character['id'],
                array_values(
                    array_filter($character['tokenItems'], function ($d) {
                        return $d['treasure'] == 'treasure';
                    })
                )[0]['id']
            );
        }
        $data = [
            'attack' => $this->game->rollBattleDie(clienttranslate('Attack'), $battle['characterId']),
        ];
        $this->game->hooks->onGetAttack($data);
        $result = '';
        if ($battle['target']['battle'] <= $data['attack']) {
            // Win without need for strength
            $result = 'win';
        } elseif ($character['tempStrength'] + (array_key_exists('cutlass', $tokens) ? min($tokens['cutlass'], 4) : 0) == 0) {
            $result = 'lose';
        }
        $this->game->gameData->set('battle', [
            ...$battle,
            'pos' => $battle['target']['pos'],
            'attack' => $data['attack'],
            'result' => $result,
        ]);
        if ($result) {
            $this->transitionToPostBattle();
        } else {
            $this->game->nextState('battle');
        }
    }
    public function actBattleSelection(int $targetId)
    {
        $this->startBattle($targetId);
    }
    public function actUseStrength()
    {
        $battle = $this->game->gameData->getBattleData();
        $character = $this->game->character->getCharacterData($battle['characterId']);
        $tokens = array_count_values(
            array_map(function ($d) {
                return $d['treasure'];
            }, $character['tokenItems'])
        );
        $battle['attack'] += $character['tempStrength'] + (array_key_exists('cutlass', $tokens) ? min($tokens['cutlass'], 4) : 0);
        $this->game->character->updateCharacterData($battle['characterId'], function (&$data) {
            $data['tempStrength'] = 0;
        });
        $this->game->gameData->set('battle', [...$battle, 'result' => $battle['target']['battle'] <= $battle['attack'] ? 'win' : 'lose']);
        $this->transitionToPostBattle();
    }
    public function actDontUseStrength()
    {
        $this->game->gameData->set('battle', [...$this->game->gameData->getBattleData(), 'result' => 'lose']);
        $this->transitionToPostBattle();
    }
    public function stBattleSelection()
    {
        $battle = $this->game->gameData->getBattleData();
        var_dump($battle['includeAdjacent'], $battle['characterId']);
        $enemies = $this->game->getEnemies($battle['includeAdjacent'], $battle['characterId']);
        if (sizeof($enemies) == 0) {
            $this->game->nextState(array_key_exists('nextState', $battle) ? $battle['nextState'] : 'playerTurn');
        }
    }
    public function argBattleSelection()
    {
        $battle = $this->game->gameData->getBattleData();
        $enemies = $this->game->getEnemies($battle['includeAdjacent'], $battle['characterId']);
        $hasCrew = array_count_values(
            array_map(
                function ($d) {
                    return $d['suffix'];
                },
                array_filter($enemies, function ($d) {
                    return $d['type'] != 'guard';
                })
            )
        );

        $targets = array_filter($enemies, function ($d) use ($hasCrew) {
            return array_key_exists($d['suffix'], $hasCrew) && $hasCrew[$d['suffix']] > 0 ? $d['type'] != 'guard' : $d['type'] == 'guard';
        });
        $result = [
            'resolving' => $this->game->actInterrupt->isStateResolving(),
            'character_name' => $this->game->getCharacterHTML($battle['characterId']),
            'activeTurnPlayerId' => 0,
            'canUndo' => $this->game->undo->canUndo(),
            'actions' =>
                sizeof($enemies) > 0
                    ? array_map(function ($d) {
                        return [
                            'action' => 'actBattleSelection',
                            'type' => 'action',
                            'targetId' => $d['id'],
                            'targetName' => $d['enemyName'],
                            'targetDie' => $d['battle'],
                            'suffix_name' => array_key_exists('suffix', $d) ? $d['suffix'] : '',
                        ];
                    }, array_values($targets))
                    : [],
        ];

        $this->game->getAllPlayers($result);
        $this->game->getTiles($result);
        return $result;
    }
    public function argBattle()
    {
        $battle = $this->game->gameData->getBattleData();
        $character = $this->game->character->getCharacterData($battle['characterId']);
        $willDie = $character['fatigue'] + max($battle['target']['battle'] - $battle['attack'], 0) >= 16;
        $tokens = array_count_values(
            array_map(function ($d) {
                return $d['treasure'];
            }, $character['tokenItems'])
        );
        $result = [
            'resolving' => $this->game->actInterrupt->isStateResolving(),
            'character_name' => $this->game->getCharacterHTML($battle['characterId']),
            'activeTurnPlayerId' => 0,
            'attack' => $battle['attack'],
            'defense' => $battle['target']['battle'],
            'actions' => $battle['result']
                ? []
                : [
                    [
                        'action' => 'actUseStrength',
                        'type' => 'action',
                        'attack' => $battle['attack'],
                        'defense' => $battle['target']['battle'],
                        'tempStrength' =>
                            $character['tempStrength'] + (array_key_exists('cutlass', $tokens) ? min($tokens['cutlass'], 4) : 0),
                    ],
                    [
                        'action' => 'actDontUseStrength',
                        'type' => 'action',
                        'willDie' => $willDie,
                    ],
                ],
        ];
        $this->game->getAllPlayers($result);
        $this->game->getTiles($result);
        return $result;
    }
    public function transitionToPostBattle()
    {
        $battle = $this->game->gameData->getBattleData();
        $resultRoll = 0;
        $isGuard = $battle['target']['type'] == 'guard';
        [$x, $y] = array_key_exists('pos', $battle) ? $battle['pos'] : $this->game->getCharacterPos($battle['characterId']);
        if ($battle['result'] == 'win') {
            $this->game->gameData->set('battle', [...$battle, 'includeAdjacent' => false]);
            $this->game->incStat(1, 'crew_eliminated', $this->game->character->getCharacterData($battle['characterId'])['playerId']);

            $isCaptain = $battle['target']['type'] == 'captain';
            $tokenPositions = $this->game->gameData->get('tokenPositions');
            if ($isCaptain) {
                $tokenPositions[$this->game->map->xy($x, $y)] = array_values(
                    array_filter($tokenPositions[$this->game->map->xy($x, $y)], function ($token) {
                        return !str_contains($token['token'], 'captain');
                    })
                );
                $this->game->decks->shuffleInCard('bag', 'captain-4', 'discard', false);
                $this->game->decks->shuffleInCard('bag', 'captain-8', 'discard', false);
                $this->game->gameData->set('tokenPositions', $tokenPositions);
            } else {
                $tokenPositions[$this->game->map->xy($x, $y)] = array_map(function ($token) use ($battle) {
                    if ($token['id'] == $battle['target']['id']) {
                        $token['isTreasure'] = true;
                    }
                    return $token;
                }, $tokenPositions[$this->game->map->xy($x, $y)]);
            }
            $this->game->gameData->set('tokenPositions', $tokenPositions);
            $this->game->markChanged('map');
            // $this->game->gameData->set('battle', [...$battle, 'resultRoll' => $resultRoll]);
        } elseif ($battle['result'] == 'lose') {
            if (!$battle['includeAdjacent']) {
                $this->game->eventLog(clienttranslate('${character_name} gained ${count} fatigue'), [
                    'count' => $battle['target']['battle'] - $battle['attack'],
                    'character_name' => $this->game->getCharacterHTML($battle['characterId']),
                ]);
                $this->game->character->adjustActiveFatigue($battle['target']['battle'] - $battle['attack']);
                // Check if the character is dead
                $battle = $this->game->gameData->getBattleData();
                if (!$isGuard && (!array_key_exists('death', $battle) || !$battle['death'])) {
                    $this->game->gameData->set('battle', [...$battle, 'includeAdjacent' => false]);
                    $moveList = $this->game->map->getValidAdjacentTiles($x, $y);
                    $currentTile = $this->game->map->getTileByXY($x, $y);
                    $hasValidMove = sizeof(
                        array_filter($moveList, function ($tile) use ($currentTile) {
                            return $this->game->map->checkIfCanMove($currentTile, $tile);
                        })
                    );
                    if (sizeof($this->game->map->getValidAdjacentTiles(...$this->game->getCharacterPos($battle['characterId']))) > 0) {
                        while ($resultRoll == 0 || (($resultRoll == 3 || $resultRoll == 4) && !$hasValidMove)) {
                            $resultRoll = $this->game->rollBattleDie(clienttranslate('Post Battle'), $battle['characterId']);
                        }
                    }
                }
            }
        }
        $this->game->gameData->set('battle', [...$battle, 'resultRoll' => $resultRoll]);
        if (!array_key_exists('death', $battle) || !$battle['death']) {
            $this->game->nextState('postBattle');
        }
    }
    public function actBattleAgain()
    {
        $battle = $this->game->gameData->getBattleData();
        $this->startBattle((int) $battle['target']['id']);
    }
    public function actMakeThemFlee()
    {
        $battle = $this->game->gameData->getBattleData();
        [$x, $y] = array_key_exists('pos', $battle) ? $battle['pos'] : $this->game->getCharacterPos($battle['characterId']);
        $targetTiles = toId($this->game->map->getValidAdjacentTiles($x, $y));
        $tokenPositions = $this->game->gameData->get('tokenPositions');
        $crewToken = array_values(
            array_filter($tokenPositions[$this->game->map->xy($x, $y)], function ($token) use ($battle) {
                return $token['id'] == $battle['target']['id'];
            })
        )[0];
        unset($crewToken['treasure']);
        unset($crewToken['isTreasure']);
        $this->game->selectionStates->initiateState(
            'crewMovement',
            [
                'movePositions' => $targetTiles,
                'id' => 'moveCrew',
                'crew' => $crewToken,
                'currentPosId' => $this->game->map->xy($x, $y),
            ],
            $battle['characterId'],
            false,
            'battleSelection'
        );
    }
    public function actRetreat()
    {
        $battle = $this->game->gameData->getBattleData();
        if (sizeof($this->game->map->calculateMoves(false)['fatigueList']) > 0) {
            $this->game->selectionStates->initiateState(
                'characterMovement',
                [
                    'id' => 'characterMovement',
                    'characterId' => $battle['characterId'],
                    'moves' => $this->game->map->calculateMoves(false)['fatigueList'],
                    'title' => clienttranslate('Select where to move'),
                ],
                $battle['characterId'],
                false,
                'battleSelection'
            );
        }
    }
    public function stPostBattle()
    {
        $battle = $this->game->gameData->getBattleData();
        if ($battle['includeAdjacent']) {
            $this->game->gameData->set('battle', [...$battle, 'includeAdjacent' => false]);
            $this->game->nextState(array_key_exists('nextState', $battle) ? $battle['nextState'] : 'playerTurn');
            return;
        }
        $isGuard = $battle['target']['type'] == 'guard';
        if (sizeof($this->game->map->getValidAdjacentTiles(...$this->game->getCharacterPos($battle['characterId']))) == 0) {
            $this->game->eventLog(clienttranslate('There is nowhere to move, battling again'));
            $this->startBattle((int) $battle['target']['id']);
        } else {
            if ($isGuard) {
                if ($battle['result'] == 'win') {
                    $this->game->nextState('battleSelection');
                }
                $this->game->completeAction(false);
            } else {
                if ($battle['resultRoll'] == 0) {
                    $this->game->nextState('battleSelection');
                } elseif ($battle['resultRoll'] <= 2) {
                    $this->game->actMakeThemFlee();
                } elseif ($battle['resultRoll'] <= 4) {
                    $this->game->actRetreat();
                } elseif ($battle['resultRoll'] == 5) {
                    $this->startBattle((int) $battle['target']['id']);
                }
            }
        }
    }
    public function argPostBattle()
    {
        $battle = $this->game->gameData->getBattleData();
        $isGuard = $battle['target']['type'] == 'guard';
        $canMove = sizeof($this->game->map->calculateMoves(false)['fatigueList']) > 0;
        $result = [
            'resolving' => $this->game->actInterrupt->isStateResolving(),
            'character_name' => $this->game->getCharacterHTML($battle['characterId']),
            'activeTurnPlayerId' => 0,
            'actions' =>
                $isGuard && $battle['result'] !== 'win'
                    ? [
                        [
                            'action' => 'actBattleAgain',
                            'type' => 'action',
                        ],
                        ...$canMove
                            ? [
                                [
                                    'action' => 'actRetreat',
                                    'type' => 'action',
                                ],
                            ]
                            : [],
                    ]
                    : ($battle['resultRoll'] == 6
                        ? [
                            [
                                'action' => 'actBattleAgain',
                                'type' => 'action',
                            ],
                            ...$canMove
                                ? [
                                    [
                                        'action' => 'actRetreat',
                                        'type' => 'action',
                                    ],
                                ]
                                : [],
                            [
                                'action' => 'actMakeThemFlee',
                                'type' => 'action',
                            ],
                        ]
                        : []),
        ];
        $this->game->getAllPlayers($result);
        $this->game->getTiles($result);
        return $result;
    }
}
