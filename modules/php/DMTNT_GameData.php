<?php
/**
 *------
 * BGA framework: Gregory Isabelli & Emmanuel Colin & BoardGameArena
 * DontLetItDie implementation : © Cutch <Your email address here>
 *
 * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
 * See http://en.boardgamearena.com/#!doc/Studio for more information.
 * -----
 *
 * Game.php
 *
 * This is the main file for your game logic.
 *
 * In this PHP file, you are going to defines the rules of the game.
 */
declare(strict_types=1);

namespace Bga\Games\DeadMenTellNoTales;

use BgaUserException;
use Exception;

use function PHPSTORM_META\type;

class DMTNT_GameData
{
    private Game $game;
    private array $cachedGameData = [];
    private static $defaults = [
        'round' => 1,
        'expansion' => 0,
        'difficulty' => 0,
        'turnUseItems' => [],
        'pendingStates' => [],
        'state' => [],
        'tokenItems' => [],
        'turnOrder' => [],
        'turnNo' => 0,
        'interruptState' => [],
        'activateCharacters' => [],
        'actInterruptState' => [],
        'characterPositions' => [],
        'tokenPositions' => [],
        'explosions' => 0,
        'newTile' => [],
        'newTileCount' => 0,
        'tempActions' => 0,
    ];
    public function __construct(Game $game)
    {
        $this->game = $game;
        $this->reload();
    }
    public function reload(): void
    {
        $this->cachedGameData = [...self::$defaults, ...$this->game->globals->getAll()];
    }
    public function set($name, $value)
    {
        $this->game->globals->set($name, $value);
        $this->cachedGameData[$name] = $value;
    }
    public function get(string $name): mixed
    {
        $value = array_key_exists($name, $this->cachedGameData) ? $this->cachedGameData[$name] : null;
        return $value;
    }
    public function getAll(...$names): array
    {
        if (sizeof($names) == 0) {
            return $this->cachedGameData;
        }
        return array_filter(
            $this->cachedGameData,
            function ($key) use ($names) {
                return in_array($key, $names);
            },
            ARRAY_FILTER_USE_KEY
        );
    }
    public function getAllMultiActiveCharacterIds(): array
    {
        return $this->get('activateCharacters');
    }
    public function getAllMultiActiveCharacter(): array
    {
        $activateCharacters = $this->getAllMultiActiveCharacterIds();
        return array_map(function ($c) {
            return $this->game->character->getCharacterData($c);
        }, $activateCharacters);
    }
    public function setAllMultiActiveCharacter()
    {
        $turnOrder = $this->game->gameData->get('turnOrder');
        $turnOrder = array_values(array_filter($turnOrder));
        foreach ($turnOrder as $k => $id) {
            $this->addMultiActiveCharacter($id);
        }
    }
    // Used for zombie turns
    public function resetMultiActiveCharacter(): bool
    {
        $activateCharacters = $this->getAllMultiActiveCharacterIds();

        $activePlayerIds = array_unique(
            array_map(function ($c) {
                return $this->game->character->getCharacterData($c)['playerId'];
            }, $activateCharacters)
        );
        if (sizeof($activePlayerIds) == 0) {
            $this->game->character->setSubmittingCharacter(null);
        }
        return $this->game->gamestate->setPlayersMultiactive($activePlayerIds, 'playerTurn', true);
    }
    public function addMultiActiveCharacter(string $characterId, bool $exclusive = false): bool
    {
        $activateCharacters = $this->getAllMultiActiveCharacterIds();
        if ($exclusive) {
            $activateCharacters = array_filter($activateCharacters, function ($d) use ($characterId) {
                return $d == $characterId;
            });
        }
        if (!in_array($characterId, $activateCharacters)) {
            array_push($activateCharacters, $characterId);
            $this->game->giveExtraTime($this->game->character->getCharacterData($characterId)['playerId']);
        }
        $this->set('activateCharacters', $activateCharacters);

        $activePlayerIds = array_unique(
            array_map(function ($c) {
                return $this->game->character->getCharacterData($c)['playerId'];
            }, $activateCharacters)
        );
        $this->game->log('state 1', $activePlayerIds, 'playerTurn');
        if (sizeof($activePlayerIds) == 0) {
            $this->game->character->setSubmittingCharacter(null);
        }
        return $this->game->gamestate->setPlayersMultiactive($activePlayerIds, 'playerTurn', true);
    }
    public function setMultiActiveCharacter(array $characterIds, bool $exclusive = false): bool
    {
        $activateCharacters = $this->getAllMultiActiveCharacterIds();
        if ($exclusive) {
            $activateCharacters = array_filter($activateCharacters, function ($d) use ($characterIds) {
                return in_array($d, $characterIds);
            });
        }
        foreach ($characterIds as $k => $characterId) {
            if (!in_array($characterId, $activateCharacters)) {
                array_push($activateCharacters, $characterId);
                $this->game->giveExtraTime($this->game->character->getCharacterData($characterId)['playerId']);
            }
        }
        $this->set('activateCharacters', $activateCharacters);

        $activePlayerIds = array_unique(
            array_map(function ($c) {
                return $this->game->character->getCharacterData($c)['playerId'];
            }, $activateCharacters)
        );

        if (sizeof($activePlayerIds) == 0) {
            $this->game->character->setSubmittingCharacter(null);
        }
        return $this->game->gamestate->setPlayersMultiactive($activePlayerIds, 'playerTurn', true);
    }
    public function removeMultiActiveCharacter(string $characterId, string $state): bool
    {
        $activateCharacters = $this->getAllMultiActiveCharacterIds();
        if (in_array($characterId, $activateCharacters)) {
            $activateCharacters = array_diff($activateCharacters, [$characterId]);
        } else {
            return false;
        }
        $this->set('activateCharacters', $activateCharacters);

        $activePlayerIds = array_unique(
            array_map(function ($c) {
                return $this->game->character->getCharacterData($c)['playerId'];
            }, $activateCharacters)
        );
        $this->game->log('state 2', $activePlayerIds, $state);
        if (sizeof($activePlayerIds) == 0) {
            $this->game->character->setSubmittingCharacter(null);
        }
        return $this->game->gamestate->setPlayersMultiactive($activePlayerIds, $state, true);
    }
    public function setup()
    {
        foreach (self::$defaults as $k => $v) {
            $this->game->globals->set($k, $v);
        }
        $this->reload();
    }
}
