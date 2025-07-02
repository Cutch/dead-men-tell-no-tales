<?php
declare(strict_types=1);

namespace Bga\Games\DeadMenTellNoTales;

use BgaUserException;

class DMTNT_Map
{
    private Game $game;
    private array $cachedMap = [];
    public function __construct(Game $game)
    {
        $this->reloadCache();
    }
    public function setup()
    {
        $starters = array_filter($this->game->data->getDecks(), function ($d) {
            return array_key_exists('starter', $d) && $d['starter'];
        });
        array_walk($starters, function ($starter, $id) {
            $this->placeMap(
                $id,
                $starter['x'],
                $starter['y'],
                0,
                rand(1, 6),
                $starter['fire_color'],
                $starter['has_trapdoor'] ? 1 : 0,
                $starter['has_trapdoor'],
                null
            );
        });
    }
    public function reloadCache()
    {
        $this->cachedMap = $this->game->getCollectionFromDb(
            'SELECT map_id, x, y, rotate, fire, fire_color, has_trapdoor, deckhand, explosion, destroyed FROM `map`',
            true
        );
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
        int $deckhand,
        bool $hasTrapdoor,
        int|null $explosion = 0
    ) {
        if (!$explosion) {
            $explosion = 'NULL';
        }
        $this->game::DbQuery(
            "INSERT INTO map (map_id, x, y, rotate, fire, fire_color, deckhand, has_trapdoor, explosion) VALUES ('$tileId', $x, $y, $rotate, $fire, $fire_color, $deckhand, $hasTrapdoor, $explosion)"
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
    }
    public function increaseFire($roll, $color)
    {
        array_walk($this->cachedMap, function (&$map) use ($color) {
            if ($map['fire_color'] === $color || $color === 'both') {
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
        $this->game->markChanged('map');
    }
    public function increaseDeckhand()
    {
        array_walk($this->cachedMap, function (&$map) {
            if ($map['has_trapdoor']) {
                $map['deckhand']++;
            }
        });
        $this->game::DbQuery('UPDATE `map` SET deckhand=deckhand+1 WHERE has_trapdoor');
        $this->game->markChanged('map');
    }
    public function costToMove(): int|null
    {
        return null;
    }
}
