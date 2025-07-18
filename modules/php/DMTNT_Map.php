<?php
declare(strict_types=1);

namespace Bga\Games\DeadMenTellNoTales;

use BgaUserException;

class DMTNT_Map
{
    private Game $game;
    private array $cachedMap = [];
    private array $xyMap = [];
    private array $minMax = ['minX' => 0, 'maxX' => 0, 'minY' => 0, 'maxY' => 0];
    public function __construct(Game $game)
    {
        $this->game = $game;
        $this->reloadCache();
    }
    public function directionToName(int $direction): string
    {
        $directionNames = [clienttranslate('Up'), clienttranslate('Right'), clienttranslate('Down'), clienttranslate('Left')];

        return $directionNames[$direction];
    }
    public function xy($x, $y)
    {
        return "{$x}x{$y}";
    }
    public function hasTileByXY($x, $y): bool
    {
        $key = $this->xy($x, $y);
        return array_key_exists($key, $this->xyMap);
    }
    public function &getTileByXY($x, $y): array
    {
        return $this->xyMap[$this->xy($x, $y)];
    }
    public function &getTileById($id): array
    {
        return $this->cachedMap[$id];
    }
    public function tileKeyToXYKey($id): string
    {
        $tile = $this->cachedMap[$id];
        return $this->xy($tile['x'], $tile['y']);
    }
    public function getXY($key)
    {
        [$x, $y] = explode('x', $key);
        return ['x' => $x, 'y' => $y];
    }
    public function updateXYMap()
    {
        $this->xyMap = [];
        array_walk($this->cachedMap, function ($v, $k) {
            $d = &$this->cachedMap[$k];
            $this->xyMap[$this->xy($d['x'], $d['y'])] = &$d;
            $this->minMax['minX'] = min($this->minMax['minX'], $d['x']);
            $this->minMax['maxX'] = max($this->minMax['maxX'], $d['x']);
            $this->minMax['minY'] = min($this->minMax['minY'], $d['y']);
            $this->minMax['maxY'] = max($this->minMax['maxY'], $d['y']);
            unset($d);
        });
    }
    public function getAdjacentTiles($x, $y, ?string $toTileId = null): array
    {
        $tiles = [];
        $pos = [[0, -1], [-1, 0], [1, 0], [0, 1]];
        array_walk($pos, function ($v) use (&$tiles, $toTileId, $x, $y) {
            $nx = $v[0];
            $ny = $v[1];
            $key = $this->xy($x + $nx, $y + $ny);
            if (array_key_exists($key, $this->xyMap)) {
                if ($toTileId && $this->xyMap[$key] === $toTileId) {
                    $tiles[] = $this->xyMap[$key];
                } else {
                    $tiles[] = $this->xyMap[$key];
                }
            }
        });
        return $tiles;
    }
    public function getEmptyAdjacentTiles($x, $y): array
    {
        $currentTile = $this->xyMap[$this->xy($x, $y)];
        $tiles = [];
        $pos = [[0, -1], [-1, 0], [1, 0], [0, 1]];
        if ($currentTile['destroyed'] == 1) {
            return [];
        }
        array_walk($pos, function ($v) use (&$tiles, $currentTile, $x, $y) {
            $nx = $v[0];
            $ny = $v[1];
            $emptyPosition = ['x' => $x + $nx, 'y' => $y + $ny];
            $key = $this->xy(...$emptyPosition);
            if (!array_key_exists($key, $this->xyMap)) {
                $tileDirection = $this->getTileDirection($currentTile, $emptyPosition);
                if (in_array($tileDirection, $this->getAdjustedTouchPoints($currentTile))) {
                    $emptyPosition['id'] = $key;
                    $tiles[] = $emptyPosition;
                }
            }
        });
        return $tiles;
    }
    public function getAllEmptyTiles(): array
    {
        return array_unique_nested(
            array_merge(
                ...array_values(
                    array_map(function ($tile) {
                        if ($tile['destroyed'] == 1) {
                            return [];
                        }
                        return $this->getEmptyAdjacentTiles($tile['x'], $tile['y']);
                    }, $this->cachedMap)
                )
            ),
            'id'
        );
    }
    public function getValidAdjacentTiles($x, $y): array
    {
        $currentTile = $this->xyMap[$this->xy($x, $y)];
        return array_values(
            array_filter($this->getAdjacentTiles($x, $y), function ($tile) use ($currentTile) {
                return !$currentTile || ($this->testTouchPoints($currentTile, $tile) && $tile['destroyed'] == 0);
            })
        );
    }
    public function getTouchPoints(string $id)
    {
        return $this->game->data->getTile()[$id]['touchPoints'];
    }
    private function getAdjustedTouchPoints(array $tile): array
    {
        $rotate = $tile['rotate'];
        return array_map(function (int $r) use ($rotate) {
            return ($rotate + $r) % 4;
        }, $this->getTouchPoints($tile['id']));
    }
    public function testTouchPoints(array $tile1, array $tile2): bool
    {
        $tileDirection = $this->getTileDirection($tile1, $tile2);
        $inverseTileDirection = $this->inverseRotation($tileDirection);
        return in_array($tileDirection, $this->getAdjustedTouchPoints($tile1)) &&
            in_array($inverseTileDirection, $this->getAdjustedTouchPoints($tile2));
    }
    public function testHasDoor(array $tile1, array $tile2): bool
    {
        $tileDirection = $this->getTileDirection($tile1, $tile2);
        $inverseTileDirection = $this->inverseRotation($tileDirection);
        return in_array($tileDirection, $this->getAdjustedTouchPoints($tile1)) ||
            in_array($inverseTileDirection, $this->getAdjustedTouchPoints($tile2));
    }
    public function getTileDirection(array $tile1, array $tile2): int
    {
        $x = $tile2['x'] - $tile1['x'];
        $y = $tile2['y'] - $tile1['y'];
        return $this->xyToRotation($x, $y);
    }
    public function xyToRotation(int $x, int $y): int
    {
        if ($x === 1) {
            return 1;
        } elseif ($x === -1) {
            return 3;
        } elseif ($y === 1) {
            return 0;
        }
        return 2;
    }
    public function inverseRotation(int $r): int
    {
        return ($r + 2) % 4;
    }
    public function setup()
    {
        $starters = array_filter($this->game->data->getDecks(), function ($d) {
            return array_key_exists('starter', $d) && $d['starter'];
        });
        array_walk($starters, function ($starter, $id) {
            $this->placeMap(
                $id,
                $starter['startX'],
                $starter['startY'],
                0,
                $id === 'tracker' ? 0 : rand(1, 5),
                $starter['color'],
                array_key_exists('startsWith', $starter) && $starter['startsWith'] === 'trapdoor' ? 1 : 0,
                array_key_exists('startsWith', $starter) && $starter['startsWith'] === 'trapdoor' ? 1 : 0,
                null,
                $id === 'tracker' ? 1 : 0
            );
        });

        $this->game->decks->discardCards('tile', function ($data, $card) {
            return (array_key_exists('starter', $data) && $data['starter'] == true) || $data['id'] == 'dinghy';
        });
    }
    public function reloadCache()
    {
        $this->cachedMap = $this->game->getCollectionFromDb('SELECT * FROM `map`', false);
        $this->updateXYMap();
    }
    public function getMap()
    {
        return $this->cachedMap;
    }
    public function iterateMap($callback): void
    {
        foreach (range($this->minMax['minX'], $this->minMax['maxX']) as $x) {
            foreach (range($this->minMax['minY'], $this->minMax['maxY']) as $y) {
                if ($this->hasTileByXY($x, $y)) {
                    $tile = &$this->getTileByXY($x, $y);
                    $callback($tile);
                    unset($tile);
                }
            }
        }
    }
    public function checkIfCanMove($currentTile, $tile): bool
    {
        $badFatigueValues = $this->convertFatigueToDie();
        $fire = $tile['fire'];
        $deckhand = $tile['deckhand'];
        return !(
            ($currentTile && !$this->testTouchPoints($currentTile, $tile)) ||
            $fire >= $badFatigueValues ||
            $deckhand >= 3 ||
            $tile['destroyed'] == 1
        );
    }
    public function calculateMoves(bool $canRun = true): array
    {
        $canRun = $canRun && !$this->game->actions->hasTreasure();
        [$x, $y] = $this->game->getCharacterPos($this->game->character->getTurnCharacterId());
        $currentFire = 0;
        $currentTiles = null;
        $key = $this->xy($x, $y);
        $moveList = $this->getAdjacentTiles($x, $y);
        $moveIds = toId($moveList);
        if (array_key_exists($key, $this->xyMap)) {
            if ($this->xyMap[$key]['escape'] == 1) {
                $currentTiles = array_values(
                    array_filter($this->cachedMap, function ($d) {
                        return $d['escape'] == 1;
                    })
                );
                $moveList = [];
                array_walk($currentTiles, function ($tile) use (&$moveList) {
                    array_push($moveList, ...$this->getAdjacentTiles($tile['x'], $tile['y']));
                });
                $moveList = array_unique_nested($moveList, 'id');
                $moveIds = toId($moveList);
            } else {
                $currentTiles = [$this->xyMap[$key]];
                $currentFire = $currentTiles[0]['fire'];
            }
        }
        $data = ['hasTreasure' => $this->game->actions->hasTreasure()];
        $this->game->hooks->onCalculateMovesHasTreasure($data);
        $hasTreasure = $data['hasTreasure'];
        $fatigueList = [];
        $paths = [];
        array_walk($moveList, function ($firstTile) use (
            &$paths,
            $canRun,
            $currentTiles,
            $moveIds,
            $hasTreasure,
            $currentFire,
            &$fatigueList
        ) {
            if (
                sizeof(
                    array_filter($currentTiles, function ($currentTile) use ($firstTile) {
                        return $this->checkIfCanMove($currentTile, $firstTile);
                    })
                ) === 0
            ) {
                return;
            }
            $fire = $firstTile['fire'];
            $x = $firstTile['x'];
            $y = $firstTile['y'];
            $id = $firstTile['id'];
            if ($hasTreasure) {
                $fatigueList[$id] = (int) $fire;
            } else {
                $fatigueList[$id] = (int) max($fire - $currentFire, 0);
            }
            if ($canRun) {
                // TODO skip being able to run to adjacent if enemy exists, except garret
                $tempList = $this->getAdjacentTiles($x, $y);
                foreach ($tempList as $tempTile) {
                    $id = $tempTile['id'];
                    if (in_array($id, $moveIds) || !$this->checkIfCanMove($firstTile, $tempTile)) {
                        continue;
                    }
                    if ($hasTreasure) {
                        $f = $tempTile['fire'] + $fire + 2;
                        if (array_key_exists($id, $fatigueList)) {
                            $fatigueList[$id] = (int) min($f, $fatigueList[$id]);
                        } else {
                            $fatigueList[$id] = (int) $f;
                        }
                    } else {
                        $f = max($fire - $currentFire, 0);
                        $f = max($tempTile['fire'] - $fire, 0) + $f + 2;
                        if (array_key_exists($id, $fatigueList)) {
                            $fatigueList[$id] = (int) min($f, $fatigueList[$id]);
                        } else {
                            $fatigueList[$id] = (int) $f;
                        }
                    }
                    if (!array_key_exists($id, $paths)) {
                        $paths[$id] = [];
                    }
                    $paths[$id][] = ['id' => $firstTile['id'], 'cost' => $f];
                }
            }
        });
        $data = ['fatigueList' => $fatigueList, 'paths' => $paths, 'currentTiles' => $currentTiles, 'x' => $x, 'y' => $y];

        $this->game->hooks->onCalculateMoves($data);

        return $data;
    }
    public function isStranded(): bool
    {
        [$x, $y] = $this->game->getCharacterPos($this->game->character->getTurnCharacterId());
        $key = $this->xy($x, $y);
        if (!array_key_exists($key, $this->xyMap)) {
            return false;
        }
        $currentTile = $this->xyMap[$key];
        if ($currentTile['escape'] == 1) {
            return false;
        }
        $moveList = $this->getValidAdjacentTiles($x, $y);
        $moveIds = toId($moveList);
        $limit = 0;
        while (sizeof($moveList) > 0 && $limit < 30) {
            $tile = array_shift($moveList);
            $limit++;
            foreach ($this->getValidAdjacentTiles($tile['x'], $tile['y']) as $newTile) {
                if (!in_array($newTile['id'], $moveIds)) {
                    array_push($moveIds, $newTile['id']);
                    array_push($moveList, $newTile);
                }
            }
            if ($tile['escape'] == 1) {
                return false;
            }
        }

        return true;
    }
    public function convertFatigueToDie(): int
    {
        $fatigue = $this->game->character->getTurnCharacter()['fatigue'];
        if ($fatigue >= 14) {
            return 2;
        } elseif ($fatigue >= 12) {
            return 3;
        } elseif ($fatigue >= 9) {
            return 4;
        } elseif ($fatigue >= 5) {
            return 5;
        }
        return 7;
    }
    public function calculateFires(): array
    {
        [$x, $y] = $this->game->getCharacterPos($this->game->character->getTurnCharacterId());
        $currentTile = null;
        $key = $this->xy($x, $y);
        $fireList = [];
        if (array_key_exists($key, $this->xyMap)) {
            $currentTile = $this->xyMap[$key];
            if ($currentTile['fire'] > 0) {
                $fireList[] = $currentTile['id'];
            }
        }

        $data = ['fireList' => $fireList, 'currentTile' => $currentTile, 'x' => $x, 'y' => $y];
        $this->game->hooks->onCalculateFires($data);
        return $data['fireList'];
    }
    public function placeMap(
        string $tileId,
        int $x,
        int $y,
        int $rotate,
        int $fire,
        string $fire_color,
        int $deckhand = 0,
        int $hasTrapdoor = 0,
        int|null $explosion = 0,
        int $escape = 0
    ): void {
        if (!$explosion) {
            $explosion = 'NULL';
        }
        $this->game::DbQuery(
            "INSERT INTO map (id, x, y, rotate, fire, fire_color, deckhand, has_trapdoor, explosion, escape) VALUES ('$tileId', $x, $y, $rotate, $fire, '$fire_color', $deckhand, $hasTrapdoor, $explosion, $escape)"
        );
        $this->cachedMap[$tileId] = [
            'id' => $tileId,
            'x' => $x,
            'y' => $y,
            'rotate' => $rotate,
            'fire' => $fire,
            'fire_color' => $fire_color,
            'deckhand' => $deckhand,
            'has_trapdoor' => $hasTrapdoor,
            'explosion' => $explosion,
            'exploded' => false,
            'destroyed' => false,
            'escape' => $escape,
        ];
        $this->game->gameData->set('lastPlacedTileId', $tileId);
        $this->updateXYMap();
    }
    public function getFire($x, $y)
    {
        return $this->xyMap[$this->xy($x, $y)]['fire'];
    }
    public function saveMapChanges()
    {
        $rows = array_map(function ($map) {
            return [
                'id' => $map['id'],
                'fire' => $map['fire'],
                'destroyed' => $map['destroyed'],
                'deckhand' => $map['deckhand'],
                'explosion' => $map['explosion'],
                'exploded' => $map['exploded'],
            ];
        }, $this->cachedMap);
        $dataTable = buildSelectQuery($rows);
        $query = <<<EOD
UPDATE map
INNER JOIN ($dataTable) AS d ON map.id = d.id
SET map.fire = d.fire, map.destroyed = d.destroyed, map.deckhand = d.deckhand, map.explosion = d.explosion, map.exploded = d.exploded;
EOD;
        $this->game::DbQuery($query);
    }
    public function decreaseFire($x, $y, $by): void
    {
        $tile = &$this->getTileByXY($x, $y);
        $tileId = $tile['id'];
        $tile['fire'] = max($tile['fire'] - $by, 0);
        $this->game::DbQuery("UPDATE `map` SET fire=GREATEST(fire - $by,0) WHERE id='$tileId'");
        // $this->updateXYMap();
        $this->game->markChanged('map');
    }
    public function checkExplosion(array &$tile)
    {
        if ($tile['destroyed'] == 0 && $tile['fire'] === 6) {
            $tile['destroyed'] = 1;
            $tile['deckhand'] = 0;
            $this->game->incStat(1, 'rooms_lost');
            $this->game->incStat(1, 'explosions');
            $this->game->gameData->set('explosions', $this->game->gameData->get('explosions') + 1);
            if ($this->game->gameData->get('explosions') == 7) {
                $this->game->lose('explosion');
            }
            $tileXY = $this->xy($tile['x'], $tile['y']);
            // Increase fire in adjacent tiles
            $adjacentTiles = $this->getValidAdjacentTiles($tile['x'], $tile['y']);
            foreach ($adjacentTiles as $aTile) {
                $aTile = &$this->getTileById($aTile['id']);
                if ($aTile['escape'] != 1) {
                    $aTile['fire'] = min($aTile['fire'] + 1, 6);
                    $this->checkExplosion($aTile);
                }
                unset($aTile);
            }

            // Check for and kill players
            $deadCharacters = array_keys(
                array_filter($this->game->gameData->get('characterPositions'), function ($xy) use ($tileXY) {
                    return $this->xy(...$xy) === $tileXY;
                })
            );
            foreach ($deadCharacters as $deadCharacter) {
                $this->game->death($deadCharacter);
            }

            // Remove tokens from location, must come after character death if they drop
            $tokenPositions = $this->game->gameData->get('tokenPositions');
            if (array_key_exists($tileXY, $tokenPositions)) {
                $this->game->gameData->set('destroyedTokens', [
                    ...$this->game->gameData->get('destroyedTokens'),
                    ...$tokenPositions[$tileXY],
                ]);
                $tokenPositions[$tileXY] = [];
                $this->game->gameData->set('tokenPositions', $tokenPositions);
                // Check for token loss condition
                $destroyedTokenCounts = array_count_values(
                    array_map(function ($d) {
                        return $d['treasure'];
                    }, $this->game->gameData->get('destroyedTokens'))
                );
                if (array_key_exists('treasure', $destroyedTokenCounts)) {
                    if (6 - $destroyedTokenCounts['treasure'] < $this->game->getTreasuresNeeded()) {
                        $this->game->lose('treasure');
                    }
                }
            }
        }
        if (
            $tile['exploded'] == 0 &&
            array_key_exists('explosion', $tile) &&
            $tile['explosion'] > 0 &&
            $tile['fire'] == $tile['explosion']
        ) {
            $tile['exploded'] = 1;
            $this->game->incStat(1, 'explosions');
            // Advance the tracker
            $this->game->gameData->set('explosions', $this->game->gameData->get('explosions') + 1);
            if ($this->game->gameData->get('explosions') == 7) {
                $this->game->lose('explosion');
            }

            $adjacentTiles = $this->getValidAdjacentTiles($tile['x'], $tile['y']);
            $directions = [];
            $start = $tile['rotate'] + 2; // All kegs start at rotation 2
            foreach ($adjacentTiles as $aTile) {
                if ($aTile['escape'] != 1) {
                    $directions[$this->getTileDirection($tile, $aTile)] = &$this->getTileById($aTile['id']);
                }
            }
            foreach (range($start, $start + 4) as $i) {
                if (array_key_exists($i, $directions)) {
                    $directions[$i]['fire'] = min($directions[$i]['fire'] + 1, 6);
                    $this->checkExplosion($directions[$i]);
                    break;
                }
            }
        }
    }
    public function increaseFire($roll, $color): void
    {
        $this->iterateMap(function (&$tile) use ($color, $roll) {
            if (($tile['fire_color'] === $color || $color === 'both') && $tile['fire'] == $roll) {
                $tile['fire'] = min($tile['fire'] + 1, 6);
            }
            $this->checkExplosion($tile);
        });
        $this->saveMapChanges();
        $this->game->markChanged('map');
    }
    public function increaseDeckhand(): void
    {
        $total = 0;
        array_walk($this->cachedMap, function (&$map) use (&$total) {
            if ($map['has_trapdoor']) {
                $map['deckhand']++;
            }
            $total += $map['deckhand'];
        });
        $this->game::DbQuery('UPDATE `map` SET deckhand=deckhand+1 WHERE has_trapdoor');
        $this->game->markChanged('map');
        if ($total > 30) {
            $this->game->lose('deckhand');
        }
    }
    public function _findTreeLevel(array $tree, array $nodes, int $currentLevel): array
    {
        return array_unique_nested(
            array_filter(
                array_merge(
                    ...array_map(function ($n) use ($tree) {
                        return array_map(function ($id) use ($tree) {
                            return $tree[$id];
                        }, $n['parents']);
                    }, $nodes)
                ),
                function ($node) use ($currentLevel) {
                    return $node['level'] == $currentLevel;
                }
            ),
            'id'
        );
    }
    public function findTreeLevel(array $tree, array $nodes, int $level): array
    {
        if (sizeof($nodes) > 0) {
            $currentLevel = max(
                array_map(function ($d) {
                    return $d['level'];
                }, $nodes)
            );
            while ($currentLevel != $level && sizeof($nodes) !== 0) {
                $currentLevel--;
                $nodes = $this->_findTreeLevel($tree, $nodes, $currentLevel);
            }
            $nodes = array_filter($nodes, function ($n) {
                return $this->getTileById($n['id'])['escape'] != 1;
            });
        }
        return $nodes;
    }
    public function getCrew(): array
    {
        $crew = [];
        $tokenPositions = $this->game->gameData->get('tokenPositions');
        array_walk($tokenPositions, function ($tokens, $xy) use (&$crew) {
            foreach ($tokens as $token) {
                if (!$token['isTreasure']) {
                    $deckType = $this->game->data->getTreasure()[$token['token']]['deckType'];
                    if ($deckType === 'crew' || $deckType === 'captain') {
                        $crew[] = ['currentPos' => $xy, 'token' => $token];
                    }
                }
            }
        });
        return $crew;
    }
    public function crewMove(): bool
    {
        $crew = $this->getCrew();
        $characterPositionIds = array_values(
            array_map(function ($name) {
                return $this->xy(...$this->game->getCharacterPos($name));
            }, $this->game->gameData->get('turnOrder'))
        );
        $nextState = true;
        $tileCount = sizeof(
            array_filter($this->cachedMap, function ($d) {
                return $d['destroyed'] != 1;
            })
        );
        foreach ($crew as $token) {
            $currentPosId = $token['currentPos'];
            $currentPos = $this->getXY($token['currentPos']);
            $startTile = $this->getTileByXY($currentPos['x'], $currentPos['y']);
            $tiles = [$startTile];
            $visited = [$startTile['id']];
            $tree = [];
            $found = false;
            $level = 1;
            while (sizeof($visited) < $tileCount && !$found && sizeof($tiles) !== 0) {
                $currentVisited = [...$visited];
                $hasChildren = false;
                $nextTiles = [];
                array_walk($tiles, function ($parent) use (
                    $currentVisited,
                    &$visited,
                    &$tree,
                    $characterPositionIds,
                    &$found,
                    &$hasChildren,
                    &$nextTiles,
                    $level
                ) {
                    $children = array_filter($this->getValidAdjacentTiles($parent['x'], $parent['y']), function ($child) use (
                        $currentVisited
                    ) {
                        return !in_array($child['id'], $currentVisited);
                    });
                    array_push($nextTiles, ...$children);
                    $hasChildren = $hasChildren || sizeof($children) > 0;
                    foreach ($children as $child) {
                        $id = $child['id'];
                        $xyId = $this->xy($child['x'], $child['y']);
                        if (!in_array($id, $visited)) {
                            array_push($visited, $id);
                        }
                        if (!array_key_exists($id, $tree)) {
                            $isChar = in_array($xyId, $characterPositionIds);
                            $tree[$id] = ['parents' => [], 'id' => $id, 'xyId' => $xyId, 'hasChar' => $isChar, 'level' => $level];
                            if ($isChar) {
                                $found = true;
                            }
                        }
                        if (!in_array($parent['id'], $tree[$id]['parents'])) {
                            array_push($tree[$id]['parents'], $parent['id']);
                        }
                    }
                });
                $tiles = $nextTiles;
                $level++;
            }
            if ($found) {
                $characterTiles = array_values(
                    array_filter($tree, function ($d) {
                        return $d['hasChar'];
                    })
                );
                $targetTiles = $this->findTreeLevel($tree, $characterTiles, 1);
                $targetTilesIds = array_unique(
                    array_map(function ($d) {
                        return $d['xyId'];
                    }, $targetTiles)
                );
                $crewToken = $token['token'];
                if (sizeof($targetTilesIds) > 1) {
                    unset($crewToken['treasure']);
                    unset($crewToken['isTreasure']);
                    $this->game->selectionStates->initiateState(
                        'crewMovement',
                        [
                            'movePositions' => toId($targetTiles),
                            'id' => 'moveCrew',
                            'crew' => $crewToken,
                            'currentPosId' => $currentPosId,
                        ],
                        $this->game->character->getTurnCharacterId(),
                        false,
                        'nextCharacter'
                    );
                    $nextState = false;
                } elseif (sizeof($targetTilesIds) === 1) {
                    $targetPosId = $targetTilesIds[0];
                    $tokenPositions = $this->game->gameData->get('tokenPositions');
                    $tokenPositions[$currentPosId] = array_values(
                        array_filter($tokenPositions[$currentPosId], function ($d) use ($crewToken) {
                            return $d['id'] !== $crewToken['id'];
                        })
                    );
                    if (!array_key_exists($targetPosId, $tokenPositions)) {
                        $tokenPositions[$targetPosId] = [];
                    }
                    $tokenPositions[$targetPosId][] = $token['token'];
                    $this->game->gameData->set('tokenPositions', $tokenPositions);
                }
            }
        }
        $this->game->markChanged('map');
        return $nextState;
    }
    public function spreadDeckhand(): void
    {
        $this->iterateMap(function ($tile) {
            if ($tile['has_trapdoor']) {
                $currentDeckhand = $tile['deckhand'];
                $adjacentTiles = $this->getValidAdjacentTiles($tile['x'], $tile['y']);
                foreach ($adjacentTiles as $aTile) {
                    $aTile = $this->getTileById($aTile['id']);
                    if ($currentDeckhand > $aTile['deckhand'] && $aTile['escape'] != 1) {
                        $aTile['deckhand']++;
                    }
                    unset($aTile);
                }
            }
        });
        $this->saveMapChanges();
        $total = 0;
        array_walk($this->cachedMap, function ($map) use (&$total) {
            $total += $map['deckhand'];
        });
        $this->game->markChanged('map');
        if ($total > 30) {
            $this->game->lose('deckhand');
        }
    }
    public function decreaseDeckhand(int $x, int $y): void
    {
        $tile = &$this->getTileByXY($x, $y);
        $tile['deckhand'] = max($tile['deckhand'] - 1, 0);
        $tileId = $tile['id'];
        $this->game::DbQuery("UPDATE `map` SET deckhand=GREATEST(deckhand-1,0) WHERE id='$tileId'");
        $this->game->markChanged('map');
    }
}
