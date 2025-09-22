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
    public function getNextBattle(): ?string
    {
        $characters = $this->game->character->getAllCharacterData(false);
        $battleXY = null;
        $this->game->map->iterateMap(function ($tile) use ($characters, &$battleXY) {
            if ($tile['escape'] == 0 && !$battleXY) {
                $xy = $this->game->map->xy($tile['x'], $tile['y']);
                $enemies = $this->game->getEnemiesByLocation(false, [$tile['x'], $tile['y']]);
                if (sizeof($enemies) > 0) {
                    foreach ($characters as $character) {
                        if ($this->game->map->xy(...$character['pos']) === $xy) {
                            $battleXY = $xy;
                        }
                    }
                }
            }
        });
        return $battleXY;
    }
    public function getBattleState(): int
    {
        $battleXY = $this->getNextBattle();
        if ($battleXY) {
            if (!$this->game->gameData->get('battleLocationState')) {
                return 1; // In Starting battle
            }
            return 2; // In Battle
        }
        return 0; // No battle
    }
    public function getBattlePhase(): ?string
    {
        return $this->game->gameData->get('battleLocationState');
    }
    public function battleLocation(string $nextState): int
    {
        $battleState = $this->getBattleState();
        if ($battleState === 1) {
            $this->game->gameData->set('battleLocationState', $nextState);
            $this->game->nextState('startCharacterBattleSelection');
        }
        return $battleState;
    }

    public function stStartCharacterBattleSelection()
    {
        $battleXY = $this->getNextBattle();
        if (!$battleXY) {
            if ($this->game->gameData->get('battleLocationState') === 'playerTurn') {
                $this->game->character->activateCharacter($this->game->character->getTurnCharacterId());
            }
            $this->game->nextState($this->game->gameData->get('battleLocationState'));
            $this->game->gameData->set('battleLocationState', null);

            $this->game->gameData->set('battle', []);
            return;
        }

        $characterIds = array_keys(
            array_filter($this->game->gameData->get('characterPositions'), function ($xy) use ($battleXY) {
                return $this->game->map->xy(...$xy) === $battleXY;
            })
        );

        if (sizeof($characterIds) == 1) {
            $this->game->gameData->set('battle', [...$this->game->gameData->get('battle'), 'characterId' => $characterIds[0]]);
            $this->game->character->activateCharacter($characterIds[0]);
            $this->game->nextState('battleSelection');
        } else {
            $playerIds = [];
            foreach ($characterIds as $charId) {
                $charData = $this->game->character->getCharacterData($charId);
                $playerIds[$charId] = $charData['playerId'];
            }
            $mustSelect = sizeof(array_unique(array_values($playerIds))) == 1;
            $this->game->gameData->set('characterBattleSelection', [
                'playerIds' => $playerIds,
                'mustSelect' => $mustSelect,
            ]);
            $this->game->nextState('characterBattleSelection');
        }
    }
    public function stCharacterBattleSelection()
    {
        $playerIds = $this->game->gameData->get('characterBattleSelection')['playerIds'];
        if (sizeof($playerIds) > 0) {
            $this->game->gameData->setMultiActivePlayer(array_unique(array_values($playerIds)));
        }
    }
    public function actFightMe(string $characterId)
    {
        $this->game->gameData->set('battle', [...$this->game->gameData->get('battle'), 'characterId' => $characterId]);
        $this->game->character->activateCharacter($characterId);
        $this->game->nextState('battleSelection');
    }
    public function actDontFight()
    {
        $playerIds = $this->game->gameData->get('characterBattleSelection')['playerIds'];
        $playerIds = array_unique(array_values($playerIds));
        $playerId = $this->game->getCurrentPlayer();

        if (sizeof($this->game->gamestate->getActivePlayerList()) == 1) {
            if (sizeof($playerIds) == 0) {
                throw new Exception('No other players to fight');
            } elseif (sizeof($playerIds) > 1) {
                $this->game->gameData->setMultiActivePlayer(array_diff($playerIds, [$playerId]));
            }
        } else {
            $this->game->gamestate->setPlayerNonMultiactive($playerId, '');
        }
    }
    public function argCharacterBattleSelection()
    {
        $playerIds = $this->game->gameData->get('characterBattleSelection')['playerIds'];
        $playerIds = array_unique(array_values($playerIds));
        $result = [
            'actions' => [],
            'character_name' => $this->game->getCharacterHTML(),
            'activeTurnPlayerId' => 0,
            'canSkipFight' => sizeof($playerIds) > 1,
            'characterBattleSelection' => $this->game->gameData->get('characterBattleSelection'),
        ];
        $this->game->getDecks($result);
        $this->game->getAllPlayers($result);
        return $result;
    }

    public function startBattle(int $targetId, ?string $characterId = null, ?string $nextState = 'playerTurn')
    {
        $characterId = $characterId ?? $this->game->character->getTurnCharacterId();
        $this->game->character->activateCharacter($characterId);

        $battle = $this->game->gameData->getBattleData();
        $enemies = $this->game->getEnemies($battle['includeAdjacent'], $characterId);
        $battle = [
            'includeAdjacent' => $battle['includeAdjacent'] || false,
            'target' => array_values(
                array_filter($enemies, function ($d) use ($targetId) {
                    return $d['id'] == $targetId;
                })
            )[0],
            'characterId' => $characterId,
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
        $battle = $this->game->gameData->getBattleData();
        $this->startBattle($targetId, $battle['characterId'], array_key_exists('nextState', $battle) ? $battle['nextState'] : 'playerTurn');
        $this->game->completeAction();
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
            'character_name' => $this->game->getCharacterHTML($battle['characterId']),
            'activeTurnPlayerId' => 0,
            'canUndo' => $this->game->undo->canUndo(),
            'actions' =>
                sizeof($enemies) > 0
                    ? [
                        ...array_map(function ($d) {
                            return [
                                'action' => 'actBattleSelection',
                                'type' => 'action',
                                'targetId' => $d['id'],
                                'targetName' => $d['enemyName'],
                                'targetDie' => $d['battle'],
                                'suffix_name' => array_key_exists('suffix', $d) ? $d['suffix'] : '',
                            ];
                        }, array_values($targets)),
                        ...$battle['characterId'] === 'garrett' && !$battle['includeAdjacent']
                            ? array_map(
                                function ($d) {
                                    return [
                                        'action' => 'actMakeThemFlee',
                                        'type' => 'action',
                                        'targetId' => $d['id'],
                                        'targetName' => $d['enemyName'],
                                        'targetDie' => $d['battle'],
                                        'suffix_name' => array_key_exists('suffix', $d) ? $d['suffix'] : '',
                                    ];
                                },
                                array_filter(array_values($targets), function ($d) {
                                    return $d['type'] != 'guard';
                                })
                            )
                            : [],
                    ]
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
            $tokenPositions = $this->game->getTokenPositions();
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
                $this->game->character->adjustFatigue($battle['characterId'], $battle['target']['battle'] - $battle['attack']);
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
        $this->game->eventLog(clienttranslate('${character_name} battles again'), [
            'character_name' => $this->game->getCharacterHTML($battle['characterId']),
        ]);
        $this->startBattle((int) $battle['target']['id'], $battle['characterId'], $battle['nextState']);
        $this->game->completeAction();
    }
    public function actMakeThemFlee(?string $targetId = null)
    {
        $battle = $this->game->gameData->getBattleData();
        $this->game->eventLog(clienttranslate('${character_name} makes the crew retreat'), [
            'character_name' => $this->game->getCharacterHTML($battle['characterId']),
        ]);
        $tokenPositions = $this->game->getTokenPositions();

        $x = 0;
        $y = 0;
        foreach ($tokenPositions as $xy => $tokens) {
            foreach ($tokens as $token) {
                if ($token['id'] == ($targetId ?? $battle['target']['id'])) {
                    $crewToken = $token;
                    [$x, $y] = array_values($this->game->map->getXY($xy));
                    break 2;
                }
            }
        }
        $targetTiles = toId($this->game->map->getValidAdjacentTiles($x, $y));
        unset($crewToken['treasure']);
        unset($crewToken['isTreasure']);
        $this->game->selectionStates->initiateState(
            'crewMovement',
            [
                'movePositions' => $targetTiles,
                'id' => 'moveCrew',
                'crew' => $crewToken,
                'currentPosId' => $xy,
                'currentState' => $this->game->gamestate->state(true, false, true)['name'],
            ],
            $battle['characterId'],
            false,
            'startCharacterBattleSelection',
            null,
            false,
            false,
            true
        );
    }
    public function actRetreat()
    {
        $battle = $this->game->gameData->getBattleData();
        if (sizeof($this->game->map->calculateMoves(false, $battle['characterId'])['fatigueList']) > 0) {
            $this->game->selectionStates->initiateState(
                'characterMovement',
                [
                    'id' => 'characterMovement',
                    'characterId' => $battle['characterId'],
                    'moves' => $this->game->map->calculateMoves(false, $battle['characterId'])['fatigueList'],
                    'title' => clienttranslate('Select where to move'),
                ],
                $battle['characterId'],
                false,
                'startCharacterBattleSelection'
            );
        }
        $this->game->completeAction();
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
            $this->startBattle((int) $battle['target']['id'], $battle['characterId'], $battle['nextState']);
        } else {
            if ($isGuard) {
                if ($battle['result'] == 'win') {
                    $this->game->nextState('startCharacterBattleSelection');
                }
                $this->game->completeAction(false);
            } else {
                if ($battle['resultRoll'] == 0) {
                    $this->game->nextState('startCharacterBattleSelection');
                } elseif ($battle['resultRoll'] <= 2) {
                    $this->game->actMakeThemFlee();
                } elseif ($battle['resultRoll'] <= 4) {
                    $this->game->actRetreat();
                } elseif ($battle['resultRoll'] == 5) {
                    $this->game->eventLog(clienttranslate('${character_name} battles again'), [
                        'character_name' => $this->game->getCharacterHTML($battle['characterId']),
                    ]);
                    $this->startBattle((int) $battle['target']['id'], $battle['characterId'], $battle['nextState']);
                }
            }
        }
    }
    public function argPostBattle()
    {
        $battle = $this->game->gameData->getBattleData();
        $isGuard = $battle['target']['type'] == 'guard';
        $canMove = sizeof($this->game->map->calculateMoves(false, $battle['characterId'])['fatigueList']) > 0;
        $result = [
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
