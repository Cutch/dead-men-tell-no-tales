<?php
declare(strict_types=1);

namespace Bga\Games\DeadMenTellNoTales;

use BgaUserException;
use PDO;

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
        // return $this->xyMap[$this->xy($x, $y)];
    }
    // public function hasTileById($id): bool
    // {
    //     return array_key_exists($id, $this->cachedMap);
    // }
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
        });
        // array_walk($this->cachedMap, function ($v, $k) {
        //     $d = &$this->cachedMap[$k];
        // $d['connections'] = $this->getAdjacentTiles($d['x'], $d['y']);
        // });
    }
    public function getAdjacentTiles($x, $y): array
    {
        $tiles = [];
        $pos = [[0, -1], [-1, 0], [1, 0], [0, 1]];
        array_walk($pos, function ($v) use (&$tiles, $x, $y) {
            $nx = $v[0];
            $ny = $v[1];
            $key = $this->xy($x + $nx, $y + $ny);
            if (array_key_exists($key, $this->xyMap)) {
                $tiles[] = $this->xyMap[$key];
            }
        });
        return $tiles;
    }
    public function getValidAdjacentTiles($x, $y): array
    {
        $currentTile = $this->xyMap[$this->xy($x, $y)];
        return array_filter($this->getAdjacentTiles($x, $y), function ($tile) use ($currentTile) {
            return !$currentTile || $this->testTouchPoints($currentTile, $tile);
        });
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
            return array_key_exists('starter', $data) && $data['starter'] == true;
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
                }
            }
        }
    }
    public function calculateMoves(): array
    {
        [$x, $y] = $this->game->getCharacterPos($this->game->character->getTurnCharacterId());
        $currentFire = 0;
        $currentTile = null;
        $key = $this->xy($x, $y);
        $moveList = $this->getAdjacentTiles($x, $y);
        $moveIds = toId($moveList);
        if (array_key_exists($key, $this->xyMap)) {
            $currentTile = $this->xyMap[$key];
            $currentFire = $currentTile['fire'];
        }
        $hasTreasure = false;
        $data = ['hasTreasure' => $hasTreasure];
        $this->game->hooks->onCalculateMovesHasTreasure($data);
        $hasTreasure = $data['hasTreasure'];
        $badFatigueValues = $this->convertFatigueToDie();
        $fatigueList = [];
        array_walk($moveList, function ($firstTile) use (
            $currentTile,
            $moveIds,
            $hasTreasure,
            $currentFire,
            $badFatigueValues,
            &$fatigueList
        ) {
            $fire = $firstTile['fire'];
            $deckhand = $firstTile['deckhand'];
            if (($currentTile && !$this->testTouchPoints($currentTile, $firstTile)) || $fire >= $badFatigueValues || $deckhand >= 3) {
                return;
            }
            $x = $firstTile['x'];
            $y = $firstTile['y'];
            $id = $firstTile['id'];
            if ($hasTreasure) {
                $fatigueList[$id] = $currentFire + $fire;
            } else {
                $fatigueList[$id] = max($fire - $currentFire, 0);
            }
            // TODO skip being able to run to adjacent if enemy exists, except garret
            $tempList = $this->getAdjacentTiles($x, $y);
            foreach ($tempList as $tempTile) {
                $id = $tempTile['id'];
                if (
                    in_array($id, $moveIds) ||
                    !$this->testTouchPoints($firstTile, $tempTile) ||
                    $tempTile['fire'] >= $badFatigueValues ||
                    $tempTile['deckhand'] >= 3
                ) {
                    continue;
                }
                if ($hasTreasure) {
                    $f = $currentFire + $tempTile['fire'] + $fire + 2;
                    if (array_key_exists($id, $fatigueList)) {
                        $fatigueList[$id] = min($f, $fatigueList[$id]);
                    } else {
                        $fatigueList[$id] = $f;
                    }
                } else {
                    $f = max($fire - $currentFire, 0);
                    $f = max($tempTile['fire'] - $fire, 0) + $f;
                    if (array_key_exists($id, $fatigueList)) {
                        $fatigueList[$id] = min($f + 2, $fatigueList[$id]);
                    } else {
                        $fatigueList[$id] = $f + 2;
                    }
                }
            }
        });
        $data = ['fatigueList' => $fatigueList, 'currentTile' => $currentTile, 'x' => $x, 'y' => $y];

        $this->game->hooks->onCalculateMoves($data);

        return $data['fatigueList'];
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
        //  INSERT INTO map (id, x, y, rotate, fire, fire_color, deckhand, has_trapdoor, explosion) VALUES (&#039;tile001&#039;, 0, 0, 0, 4, &#039;yellow&#039;, 0, , NULL)
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
    public function decreaseFire($x, $y): void
    {
        $tile = &$this->getTileByXY($x, $y);
        $this->game->log($tile['fire']);
        $tile['fire'] = max($tile['fire'] - 1, 0);
        $tile = &$this->getTileByXY($x, $y);
        $this->game->log($tile['fire']);
        $this->game->log($this->getTileById($tile['id'])['fire']);
        $tileId = $tile['id'];
        $tile['fire'] = max($tile['fire'] - 1, 0);
        $this->game::DbQuery("UPDATE `map` SET fire=GREATEST(fire-1,0) WHERE id='$tileId'");
        // $this->updateXYMap();
        $this->game->markChanged('map');
    }
    public function checkExplosion(array &$tile)
    {
        if (!$tile['destroyed'] && $tile['fire'] === 6) {
            $map['destroyed'] = 1;
            $adjacentTiles = $this->getValidAdjacentTiles($tile['x'], $tile['y']);
            foreach ($adjacentTiles as $aTile) {
                $aTile = &$this->getTileById($aTile['id']);
                $aTile['fire'] = min($aTile['fire'] + 1, 6);
                $this->checkExplosion($aTile);
            }
        }
        if (!$tile['exploded'] && array_key_exists('explosion', $tile) && $tile['explosion'] > 0 && $tile['fire'] === $tile['explosion']) {
            $map['exploded'] = 1;
            $adjacentTiles = $this->getValidAdjacentTiles($tile['x'], $tile['y']);
            $directions = [];
            $start = $tile['rotate'] + 2; // All kegs start at rotation 2
            foreach ($adjacentTiles as $aTile) {
                $directions[$this->getTileDirection($tile, $aTile)] = &$this->getTileById($aTile['id']);
            }
            foreach (range($start, $start + 4) as $i) {
                if (array_key_exists($i, $directions)) {
                    $directions[$i]['fire'] = min($directions[$i]['fire'] + 1, 6);
                    $this->checkExplosion($directions[$i]);
                }
            }
        }
    }
    public function increaseFire($roll, $color): void
    {
        $this->iterateMap(function ($tile) use ($color, $roll) {
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
        array_walk($this->cachedMap, function (&$map, &$total) {
            if ($map['has_trapdoor']) {
                $map['deckhand']++;
            }
            $total += $map['deckhand'];
        });
        $this->game::DbQuery('UPDATE `map` SET deckhand=deckhand+1 WHERE has_trapdoor');
        // $this->updateXYMap();
        $this->game->markChanged('map');
        if ($total > 30) {
            $this->game->lose();
        }
    }
    // public function iterateConnections(array $tiles, $ignoreList): array
    // {

    //     $queue = [];
    //     foreach ($tile['connections'] as $tile) {
    //         $queue[] = $tile['connections']
    //     }
    // }
    public function _findTreeLevel(array $tree, array $nodes, int &$currentLevel): array
    {
        return array_merge(
            ...array_map(function ($n) use (&$currentLevel, $tree) {
                $currentLevel = $n['level'] - 1;
                return array_map(function ($id) use ($tree) {
                    return $tree[$id];
                }, $n['parents']);
            }, $nodes)
        );
    }
    public function findTreeLevel(array $tree, array $nodes, int $level): array
    {
        $currentLevel = 99;
        while ($currentLevel != $level && sizeof($nodes) !== 0) {
            $nodes = $this->_findTreeLevel($tree, $nodes, $currentLevel);
        }
        return $nodes;
    }
    public function crewMove(): void
    {
        $crew = [];
        $tokenPositions = $this->game->gameData->get('tokenPositions');
        array_walk($tokenPositions, function ($tokens, $xy) use (&$crew) {
            foreach ($tokens as $token) {
                if (!$token['isTreasure']) {
                    if ($this->game->data->getTreasure()[$token['token']]['deckType'] === 'crew') {
                        $crew[] = ['currentPos' => $xy, 'token' => $token];
                    }
                }
            }
        });
        $characterPositionIds = array_values(
            array_map(
                function ($d) {
                    return $this->xy($d[0], $d[1]);
                },
                array_filter($this->game->gameData->get('characterPositions'), function ($v) {
                    return sizeof($v) == 2;
                })
            )
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
            while (sizeof($visited) < sizeof($this->cachedMap) && !$found && sizeof($tiles) !== 0) {
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
                    $children = array_filter($this->getAdjacentTiles($parent['x'], $parent['y']), function ($child) use ($currentVisited) {
                        return !in_array($child['id'], $currentVisited);
                    });
                    array_push($nextTiles, ...$children);
                    $hasChildren = $hasChildren || sizeof($children) > 0;
                    foreach ($children as $child) {
                        $id = $child['id'];
                        $xyId = $this->xy($child['x'], $child['y']);
                        array_push($visited, $id);
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
                    $this->game->selectionStates->initiateState(
                        'crewSelection',
                        [
                            'movePositions' => $targetTilesIds,
                            'id' => 'moveCrew',
                            'crewId' => $crewToken['id'],
                        ],
                        $this->game->character->getTurnCharacterId(),
                        false,
                        'drawRevengeCard'
                    );
                } elseif (sizeof($targetTilesIds) === 1) {
                    $targetPosId = $targetTilesIds[0];
                    $tokenPositions = $this->game->gameData->get('tokenPositions');
                    $tokenPositions[$currentPosId] = array_filter($tokenPositions[$currentPosId], function ($d) use ($crewToken) {
                        return $d['id'] == $crewToken['id'];
                    });
                    if (!array_key_exists($targetPosId, $tokenPositions)) {
                        $tokenPositions[$targetPosId] = [];
                    }
                    $tokenPositions[$targetPosId][] = $token['token'];
                    // $tokenPositions[$currentPosId]
                    $this->game->gameData->set('tokenPositions', $tokenPositions);
                }
            }
        }
        $this->game->markChanged('map');
    }
    public function spreadDeckhand(): void
    {
        $this->iterateMap(function ($tile) {
            if ($tile['has_trapdoor']) {
                $currentDeckhand = $tile['deckhand'];
                $adjacentTiles = $this->getValidAdjacentTiles($tile['x'], $tile['y']);
                foreach ($adjacentTiles as $aTile) {
                    $aTile = &$this->getTileById($aTile['id']);
                    if ($currentDeckhand > $aTile['deckhand']) {
                        $aTile['deckhand']++;
                    }
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
            $this->game->lose();
        }
    }
    public function decreaseDeckhand(int $x, int $y): void
    {
        $tile = $this->getTileByXY($x, $y);
        $tile['deckhand'] = max($tile['deckhand'] - 1, 0);
        $tileId = $tile['id'];
        $this->game::DbQuery("UPDATE `map` SET deckhand=GREATEST(deckhand-1,0) WHERE id='$tileId'");
        // $this->updateXYMap();
        $this->game->markChanged('map');
    }
    public function costToMove(): int|null
    {
        return null;
    }
}
