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
        return "$x$y";
    }
    public function updateXYMap()
    {
        $this->xyMap = [];
        array_walk($this->cachedMap, function ($v, $k) {
            $d = $this->cachedMap[$k];
            $this->xyMap[$this->xy($d['x'], $d['y'])] = &$d;
        });
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
                rand(1, 6),
                $starter['color'],
                array_key_exists('startsWith', $starter) && $starter['startsWith'] === 'trapdoor' ? 1 : 0,
                array_key_exists('startsWith', $starter) && $starter['startsWith'] === 'trapdoor' ? 1 : 0,
                null
            );
        });
    }
    public function reloadCache()
    {
        $this->cachedMap = $this->game->getCollectionFromDb(
            'SELECT map_id, x, y, rotate, fire, fire_color, has_trapdoor, deckhand, explosion, destroyed FROM `map`',
            false
        );
        $this->updateXYMap();
    }
    public function getMap()
    {
        return $this->cachedMap;
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
        int|null $explosion = 0
    ): void {
        if (!$explosion) {
            $explosion = 'NULL';
        }
        //  INSERT INTO map (map_id, x, y, rotate, fire, fire_color, deckhand, has_trapdoor, explosion) VALUES (&#039;tile001&#039;, 0, 0, 0, 4, &#039;yellow&#039;, 0, , NULL)
        $this->game::DbQuery(
            "INSERT INTO map (map_id, x, y, rotate, fire, fire_color, deckhand, has_trapdoor, explosion) VALUES ('$tileId', $x, $y, $rotate, $fire, '$fire_color', $deckhand, $hasTrapdoor, $explosion)"
        );
        $this->cachedMap[$tileId] = [
            'map_id' => $tileId,
            'x' => $x,
            'y' => $y,
            'rotate' => $rotate,
            'fire' => $fire,
            'fire_color' => $fire_color,
            'deckhand' => $deckhand,
            'has_trapdoor' => $hasTrapdoor,
            'explosion' => $explosion,
            'destroyed' => false,
        ];
        $this->updateXYMap();
    }
    public function getFire($x, $y)
    {
        return $this->xyMap[$this->xy($x, $y)]['fire'];
    }
    public function decreaseFire($x, $y): void
    {
        $this->xyMap[$this->xy($x, $y)]['fire']--;
        $this->updateXYMap();
        $this->game->markChanged('map');
    }
    public function increaseFire($roll, $color): void
    {
        array_walk($this->cachedMap, function (&$map) use ($color, $roll) {
            if (($map['fire_color'] === $color || $color === 'both') && $map['fire'] == $roll) {
                $map['fire']++;
            }
        });
        $this->game::DbQuery("UPDATE `map` SET fire=fire+1 WHERE fire_color = '$color' OR $color = 'both'");
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
        $this->updateXYMap();
        $this->game->markChanged('map');
    }
    public function costToMove(): int|null
    {
        return null;
    }
}
