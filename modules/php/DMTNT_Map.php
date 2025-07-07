<?php
declare(strict_types=1);

namespace Bga\Games\DeadMenTellNoTales;

use BgaUserException;

class DMTNT_Map
{
    private Game $game;
    private array $cachedMap = [];
    private array $xyMap = [];
    public function __construct(Game $game)
    {
        $this->game = $game;
        $this->reloadCache();
    }
    public function xy($x, $y)
    {
        return "{$x}x{$y}";
    }
    public function &getTileByXY($x, $y): array|null
    {
        $key = $this->xy($x, $y);
        if (array_key_exists($key, $this->xyMap)) {
            return $this->xyMap[$this->xy($x, $y)];
        }
        return null;
    }
    public function &getTileById($id): array
    {
        return $this->cachedMap[$id];
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
        });
    }
    public function getAdjacentTiles($x, $y): array
    {
        $tiles = [];
        $pos = [[0, 1], [0, -1], [1, 0], [-1, 0]];
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
        $x = $tile2['x'] - $tile1['x'];
        $y = $tile2['y'] - $tile1['y'];
        $tileDirection = $this->xyToRotation($x, $y);
        $inverseTileDirection = $this->inverseRotation($tileDirection);
        return in_array($tileDirection, $this->getAdjustedTouchPoints($tile1)) &&
            in_array($inverseTileDirection, $this->getAdjustedTouchPoints($tile2));
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

        $this->game->decks->discardCards('tile', function ($data) {
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
            array_push($moveIds, $currentTile['id']);
        }
        $hasTreasure = false;
        $data = ['hasTreasure' => $hasTreasure];
        $this->game->hooks->onCalculateMovesHasTreasure($data);
        $hasTreasure = $data['$hasTreasure'];
        $fatigueList = [];
        array_walk($moveList, function ($firstTile) use ($currentTile, $moveIds, $hasTreasure, $currentFire, &$fatigueList) {
            if ($currentTile && !$this->testTouchPoints($currentTile, $firstTile)) {
                return;
            }
            $x = $firstTile['x'];
            $y = $firstTile['y'];
            $fire = $firstTile['fire'];
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
                if (in_array($id, $moveIds) || !$this->testTouchPoints($firstTile, $tempTile)) {
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

        return $fatigueList;
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
                $currentTile[] = $currentTile['id'];
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
            'destroyed' => false,
            'escape' => $escape,
        ];
        $this->updateXYMap();
    }
    public function getFire($x, $y)
    {
        return $this->xyMap[$this->xy($x, $y)]['fire'];
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
    public function increaseFire($roll, $color): void
    {
        array_walk($this->cachedMap, function (&$map) use ($color, $roll) {
            if (($map['fire_color'] === $color || $color === 'both') && $map['fire'] == $roll) {
                $map['fire']++;
            }
        });
        $this->game::DbQuery("UPDATE `map` SET fire=fire+1 WHERE (fire_color = '$color' OR $color = 'both') AND NOT escape");
        array_walk($this->cachedMap, function (&$map) use ($color) {
            if (!$map['destroyed'] || $map['fire'] === 6) {
                $map['destroyed'] = 1;
            }
        });
        $this->game::DbQuery('UPDATE `map` SET destroyed=1 WHERE fire=6');
        $this->updateXYMap();
        $this->game->markChanged('map');
    }
    public function increaseDeckhand(): void
    {
        array_walk($this->cachedMap, function (&$map) {
            if ($map['has_trapdoor']) {
                $map['deckhand']++;
            }
        });
        $this->game::DbQuery('UPDATE `map` SET deckhand=deckhand+1 WHERE has_trapdoor');
        // $this->updateXYMap();
        $this->game->markChanged('map');
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
