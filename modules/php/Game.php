<?php
/**
 *------
 * BGA framework: Gregory Isabelli & Emmanuel Colin & BoardGameArena
 * DeadMenTellNoTales implementation : © Cutch <Your email address here>
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

use Bga\GameFramework\Actions\CheckAction;
use Bga\GameFramework\Actions\Types\JsonParam;

use BgaUserException;
use ErrorException;
use Exception;
set_error_handler(function ($severity, $message, $file, $line) {
    if (error_reporting() & $severity) {
        throw new ErrorException($message, 0, $severity, $file, $line);
    }
});
require_once APP_GAMEMODULE_PATH . 'module/table/table.game.php';
include_once dirname(__DIR__) . '/php/DMTNT_Battle.php';
include_once dirname(__DIR__) . '/php/DMTNT_Data.php';
include_once dirname(__DIR__) . '/php/DMTNT_Actions.php';
include_once dirname(__DIR__) . '/php/DMTNT_CharacterSelection.php';
include_once dirname(__DIR__) . '/php/DMTNT_Character.php';
include_once dirname(__DIR__) . '/php/DMTNT_GameData.php';
include_once dirname(__DIR__) . '/php/DMTNT_SelectionStates.php';
include_once dirname(__DIR__) . '/php/DMTNT_Undo.php';
require_once dirname(__DIR__) . '/php/data-files/DMTNT_Utils.php';
require_once dirname(__DIR__) . '/php/data-files/DMTNT_Characters.php';
require_once dirname(__DIR__) . '/php/data-files/DMTNT_RevengeDeck.php';
require_once dirname(__DIR__) . '/php/data-files/DMTNT_Items.php';
require_once dirname(__DIR__) . '/php/data-files/DMTNT_Tile.php';
class Game extends \Table
{
    public DMTNT_Character $character;
    public DMTNT_Actions $actions;
    private DMTNT_CharacterSelection $characterSelection;
    public DMTNT_Battle $battle;
    public DMTNT_Data $data;
    public DMTNT_Decks $decks;
    public DMTNT_Map $map;
    public DMTNT_GameData $gameData;
    public DMTNT_Hooks $hooks;
    // public DMTNT_Encounter $encounter;
    // public DMTNT_ItemTrade $itemTrade;
    public DMTNT_ActInterrupt $actInterrupt;
    public DMTNT_SelectionStates $selectionStates;
    public DMTNT_Undo $undo;
    public static array $expansionList = ['base', 'kraken'];
    /**
     * Your global variables labels:
     *
     * Here, you can assign labels to global variables you are using for this game. You can use any number of global
     * variables with IDs between 10 and 99. If your game has options (variants), you also have to associate here a
     * label to the corresponding ID in `gameoptions.inc.php`.
     *
     * NOTE: afterward, you can get/set the global variables with `getGameStateValue`, `gameData->set` or
     * `setGameStateValue` functions.
     */
    public function __construct()
    {
        parent::__construct();

        $this->initGameStateLabels([
            'expansion' => 100,
            'difficulty' => 101,
            'captainFromm' => 102,
            'random' => 103,
            'soloCount' => 104,
            'doubleCount' => 105,
            'powderKegExplosion' => 106,
        ]);
        $this->gameData = new DMTNT_GameData($this);
        $this->actions = new DMTNT_Actions($this);
        $this->battle = new DMTNT_Battle($this);
        $this->data = new DMTNT_Data($this);
        $this->decks = new DMTNT_Decks($this);
        $this->map = new DMTNT_Map($this);
        $this->character = new DMTNT_Character($this);
        $this->characterSelection = new DMTNT_CharacterSelection($this);
        $this->hooks = new DMTNT_Hooks($this);
        // $this->encounter = new DMTNT_Encounter($this);
        // $this->itemTrade = new DMTNT_ItemTrade($this);
        $this->actInterrupt = new DMTNT_ActInterrupt($this);
        $this->selectionStates = new DMTNT_SelectionStates($this);
        $this->undo = new DMTNT_Undo($this);
        // automatically complete notification args when needed
        $this->notify->addDecorator(function (string $message, array $args) {
            $args['gamestate'] = ['name' => $this->gamestate->state(true, false, true)['name']];
            if (!array_key_exists('character_name', $args) && str_contains($message, '${character_name}')) {
                $args['character_name'] = $this->getCharacterHTML();
            }
            if (!array_key_exists('player_name', $args) && str_contains($message, '${player_name}')) {
                if (array_key_exists('playerId', $args)) {
                    $args['player_name'] = $this->getPlayerNameById($args['playerId']);
                } elseif (array_key_exists('characterId', $args)) {
                    $playerId = (int) $this->character->getCharacterData($args['characterId'])['playerId'];
                    $args['player_name'] = $this->getPlayerNameById($playerId);
                } elseif (array_key_exists('character_name', $args)) {
                    $playerId = (int) $this->character->getCharacterData($args['character_name'])['playerId'];
                    $args['player_name'] = $this->getPlayerNameById($playerId);
                } else {
                    $playerId = (int) $this->getActivePlayerId();
                    $args['player_name'] = $this->getPlayerNameById($playerId);
                }
            }
            if (!array_key_exists('character_name', $args) && $this->character->getTurnCharacterId()) {
                $args['character_name'] = $this->getCharacterHTML();
            }
            return $args;
        });
    }
    public function getTokenPositions(): array
    {
        $tokenPositions = array_unique_nested_recursive($this->gameData->get('tokenPositions'), 'id');
        return $tokenPositions;
    }
    public function getTreasuresNeeded(): int
    {
        $characterCount = sizeof($this->character->getAllCharacterIds());
        if ($characterCount <= 3) {
            $treasuresNeeded = [4, 5, 6];
            return $treasuresNeeded[$this->gameData->get('difficulty')];
        } else {
            $treasuresNeeded = [5, 6, 6];
            return $treasuresNeeded[$this->gameData->get('difficulty')];
        }
    }
    public function hasAllTreasure(): bool
    {
        $treasureCount = $this->gameData->get('treasures');
        return $treasureCount >= $this->getTreasuresNeeded();
    }
    public function checkWin(): void
    {
        $characterCount = sizeof($this->character->getAllCharacterIds());
        $escaped =
            sizeof(
                array_filter(array_values($this->gameData->get('characterPositions')), function ($xy) {
                    $tile = $this->map->getTileByXY(...$xy);
                    return !array_key_exists('escape', $tile) || $tile['escape'] == 0;
                })
            ) == 0;
        if (!$escaped) {
            return;
        }
        if ($characterCount >= 4) {
            $crewCount = sizeof($this->map->getCrew());
            if ($this->gameData->get('difficulty') === 2 && $crewCount > 0) {
                return;
            }
        }
        if (!$this->hasAllTreasure()) {
            return;
        }
        $this->win();
    }
    public function actAbandonShip()
    {
        $this->death($this->character->getTurnCharacterId());
        if ($this->gamestate->state(true, false, true)['name'] == 'playerTurn') {
            $this->endTurn();
        }
    }
    public function actUndo()
    {
        $this->undo->actUndo();
    }
    public function getVersion(): int
    {
        if (!array_key_exists(300, $this->gamestate->table_globals)) {
            $this->gamestate->reloadState();
        }
        return intval($this->gamestate->table_globals[300]);
    }
    protected function initTable(): void
    {
        $this->undo->loadInitialState();
    }
    public function nextState(string $transition)
    {
        if ($this->getBgaEnvironment() == 'studio') {
            $this->log('Transition to \'' . $transition . '\'');
        }
        $this->gamestate->nextState($transition);
    }
    public function notify(...$arg)
    {
        if ($this->getBgaEnvironment() == 'studio') {
            $this->log('notify', ...$arg);
        }
        $this->notify->all(...$arg);
    }
    public function notify_player($playerId, ...$arg)
    {
        if ($this->getBgaEnvironment() == 'studio') {
            $this->log('notify player', $playerId, ...$arg);
        }
        $this->notify->player($playerId, ...$arg);
    }
    public function getCharacterHTML(?string $id = null)
    {
        if ($id) {
            $char = $this->character->getCharacterData($id);
        } else {
            $char = $this->character->getSubmittingCharacter();
        }
        $name = $char['name'];
        $playerName = $this->getPlayerNameById($char['playerId']);
        $playerColor = $char['player_color'];
        return "<!--PNS--><span class=\"playername\" style=\"color:#$playerColor;\">$name ($playerName)</span><!--PNE-->";
    }
    public function initDeck($type = 'card')
    {
        $deck = $this->getNew('module.common.deck');
        $deck->autoreshuffle = true;
        $deck->init($type);
        return $deck;
    }
    public function getCurrentPlayer(bool $bReturnNullIfNotLogged = false): int
    {
        return (int) parent::getCurrentPlayerId($bReturnNullIfNotLogged);
    }
    public function getFromDB(string $str)
    {
        return $this->getObjectFromDB($str);
    }
    public function eventLog($message = '', $arg = [])
    {
        $this->notify('notify', $message, $arg);
    }
    public function getRemainingCharacters()
    {
        return array_diff(
            toId($this->data->getCharacters()),
            $this->gameData->get('deadCharacters'),
            $this->character->getAllCharacterIds()
        );
    }
    public function death(string $characterId)
    {
        if ($this->hasAllTreasure()) {
            $this->lose('untimely');
        }
        $this->eventLog(clienttranslate('${character_name} has died'), [
            'character_name' => $this->getCharacterHTML($characterId),
        ]);

        $characterPositions = $this->gameData->get('characterPositions');
        unset($characterPositions[$characterId]);
        $this->gameData->set('characterPositions', $characterPositions);
        $character = $this->character->getCharacterData($characterId);
        $item = $character['item'];
        if (
            sizeof($this->data->getCharacters()) ===
            sizeof($this->gameData->get('deadCharacters')) + sizeof($this->character->getAllCharacterIds())
        ) {
            $this->lose('noPirates');
        }

        $this->gameData->set('deadCharacters', [...$this->gameData->get('deadCharacters'), $character['id']]);
        $this->gameData->set('destroyedItems', [...$this->gameData->get('destroyedItems'), $item['itemId']]);

        // Drop Treasures
        $treasures = array_filter($character['tokenItems'], function ($d) {
            return $d['treasure'] === 'treasure';
        });
        [$x, $y] = $this->getCharacterPos($characterId);
        $xyId = $this->map->xy($x, $y);
        $tokenPositions = $this->getTokenPositions();
        if (!array_key_exists($xyId, $tokenPositions)) {
            $tokenPositions[$xyId] = [];
        }
        foreach ($treasures as $token) {
            $tokenPositions[$xyId][] = $token;
        }
        $tokenPositions[$xyId] = array_values($tokenPositions[$xyId]);
        $this->gameData->set('tokenPositions', $tokenPositions);

        $remainingCharacters = array_values($this->getRemainingCharacters());
        if ($this->gameData->get('randomSelection')) {
            shuffle($remainingCharacters);
            $newCharacterId = $remainingCharacters[0];
            $this->character->swapToCharacter($character['id'], $newCharacterId);

            $this->eventLog(clienttranslate('${character_name} has joined the expedition'), [
                'character_name' => $this->getCharacterHTML($newCharacterId),
            ]);
            $this->markChanged('player');
            $this->markChanged('map');
            $this->markChanged('token');
            $this->markChanged('actions');
            $this->markRandomness();
            if ($this->battle->getBattlePhase()) {
                if ($this->battle->getBattlePhase() == 'playerTurn') {
                    $this->endTurn();
                } elseif (sizeof($this->selectionStates->getPendingStates()) == 0) {
                    $this->nextState('startCharacterBattleSelection');
                }
            } else {
                $this->endTurn();
            }
        } else {
            $this->selectionStates->initiateState(
                'characterSelection',
                [
                    'selectableCharacters' => $remainingCharacters,
                    'currentCharacter' => $character['id'],
                    'id' => 'death',
                ],
                $this->character->getTurnCharacterId(),
                false,
                $this->gamestate->state(true, false, true)['name'] == 'drawRevengeCard' ? 'nextCharacter' : false
            );
        }
    }
    public function cardDrawEvent($card, $deck, $arg = [])
    {
        $gameData = [];
        $this->getDecks($gameData);
        $result = [
            'card' => $card,
            'deck' => $deck,
            'character_name' => $this->getCharacterHTML(),
            'gameData' => $gameData,
            ...$arg,
        ];
        $this->notify('cardDrawn', '', $result);
    }
    public function rollBattleDie(string $action, string $characterName): int
    {
        $this->markRandomness();
        $value = bga_rand(1, 6);
        $notificationSent = false;
        $data = [
            'value' => $value,
        ];
        $data['sendNotification'] = function () use ($value, $characterName, &$notificationSent, $action) {
            $this->notify('rollBattleDie', clienttranslate('${character_name} rolled a ${value} (${action})'), [
                'i18n' => ['action'],
                'value' => $value,
                'character_name' => $this->getCharacterHTML($characterName),
                'characterId' => $characterName,
                'roll' => $value,
                'action' => $action,
            ]);
            $notificationSent = true;
        };
        $this->hooks->onRollDie($data);
        $data['value'] = max($data['value'], 1);
        if (!$notificationSent) {
            $data['sendNotification']();
        }
        return $data['value'];
    }
    public function addExtraTime(?int $extraTime = null)
    {
        $this->giveExtraTime($this->getCurrentPlayer(), $extraTime);
    }
    public function actCharacterClicked(
        ?string $character1 = null,
        ?string $character2 = null,
        ?string $character3 = null,
        ?string $character4 = null,
        ?string $character5 = null
    ): void {
        $this->characterSelection->actCharacterClicked($character1, $character2, $character3, $character4, $character5);
        $this->completeAction(false);
    }
    public function actChooseCharacters(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->addExtraTime();
        }
        $this->characterSelection->actChooseCharacters();
        $this->completeAction(false);
    }
    public function getDeckhandTargetCount(): int
    {
        $data = ['count' => 1];
        $this->hooks->onGetDeckhandTargetCount($data);
        return $data['count'];
    }
    public function getCharacterPos(string $id): array
    {
        $characterPositions = $this->gameData->get('characterPositions');
        return array_key_exists($id, $characterPositions) ? $characterPositions[$id] : [0, -1];
    }

    public function setCharacterPos(string $id, int $x, int $y): void
    {
        $this->gameData->set('characterPositions', [...$this->gameData->get('characterPositions'), $id => [$x, $y]]);
        $this->markChanged('player');
    }
    public function getTilePlacementLocations($isDinghy = false): array
    {
        $validLocations = [];
        $newTile = $this->gameData->get('newTile');
        if ($isDinghy) {
            $lastTile = $this->map->getTileById($this->gameData->get('lastPlacedTileId'));
            $validLocations = $this->map->getEmptyAdjacentTiles($lastTile['x'], $lastTile['y']);
        } else {
            $validLocations = $this->map->getAllEmptyTiles();
        }
        $validLocations = array_filter($validLocations, function ($position) use ($newTile, $validLocations) {
            $newTile['x'] = $position['x'];
            $newTile['y'] = $position['y'];
            $tiles = null;
            if ($newTile['id'] === 'dinghy') {
                $tiles = $this->map->getAdjacentTiles($position['x'], $position['y'], $this->gameData->get('lastPlacedTileId'));
            } else {
                $tiles = $this->map->getAdjacentTiles($position['x'], $position['y']);
            }
            foreach (range(0, 3) as $rotate) {
                $newTile['rotate'] = $rotate;
                $all = true;
                $any = false;
                array_walk($tiles, function ($tile) use ($newTile, &$all, &$any) {
                    $touches = $this->map->testTouchPoints($tile, $newTile);
                    $all = $all && ($touches || !$this->map->testHasDoor($tile, $newTile) || $tile['destroyed'] == 1);
                    $any = $any || $touches;
                });
                if ($newTile['id'] === 'dinghy') {
                    $response = $any;
                } else {
                    $response = $any && $all;
                }
                unset($all, $any);
                if ($response) {
                    return $response;
                }
            }
            return false;
        });
        return array_unique_nested($validLocations, 'id');
    }
    public function actPlaceTile(?int $x, ?int $y, ?int $rotate): void
    {
        if ($x === null || $y === null) {
            throw new BgaUserException(clienttranslate('Select a location'));
        }
        $newTile = $this->gameData->get('newTile');
        $newTile['x'] = $x;
        $newTile['y'] = $y;
        $newTile['rotate'] = $rotate;
        $tiles = null;
        if ($newTile['id'] === 'dinghy') {
            $tiles = $this->map->getAdjacentTiles($x, $y, $this->gameData->get('lastPlacedTileId'));
        } else {
            $tiles = $this->map->getAdjacentTiles($x, $y);
        }
        if (sizeof($tiles) === 0 || $y < 0) {
            throw new BgaUserException(clienttranslate('Tile can\'t be placed there'));
        }
        $all = true;
        $any = false;
        array_walk($tiles, function ($tile) use ($newTile, &$all, &$any) {
            $touches = $this->map->testTouchPoints($tile, $newTile);
            $all = $all && ($touches || !$this->map->testHasDoor($tile, $newTile) || $tile['destroyed'] == 1);
            $any = $any || $touches;
        });
        if (!$any) {
            throw new BgaUserException(clienttranslate('Tile must connect to a door'));
        }
        if ($newTile['id'] !== 'dinghy') {
            if (!$all) {
                throw new BgaUserException(clienttranslate('All doors must connect'));
            }
        }
        $newTileData = $this->data->getTile()[$newTile['id']];

        if ($newTile['id'] === 'dinghy') {
            $this->map->placeMap($newTile['id'], $x, $y, $rotate, 0, 'both', 0, 0, 0, 1);
        } else {
            // Get tokens
            $trapdoor = false;
            if ($this->decks->getDeck('bag')->getCardOnTop('deck')) {
                $card = $this->decks->pickCardWithoutLookup('bag');
                if ($card['type_arg'] === 'trapdoor') {
                    $trapdoor = true;
                } else {
                    $tokens = [$this->getTokenData($card)];
                    if (str_contains($card['type_arg'], 'captain')) {
                        // TODO check this for captain
                        $this->decks->discardCards('bag', 'discard', function ($data, $card) {
                            return str_contains($card['type_arg'], 'captain');
                        });
                        $card2 = $this->decks->pickCardWithoutLookup('bag');
                        if ($card2['type_arg'] === 'trapdoor') {
                            $trapdoor = true;
                        } else {
                            $tokens[] = $this->getTokenData($card2);
                        }
                    }
                    $this->gameData->set('tokenPositions', [...$this->getTokenPositions(), $this->map->xy($x, $y) => $tokens]);
                }
            }
            $this->map->placeMap(
                $newTile['id'],
                $x,
                $y,
                $rotate,
                $newTileData['fire'],
                $newTileData['color'],
                $trapdoor ? 1 : 0,
                $trapdoor ? 1 : 0,
                array_key_exists('barrel', $newTileData) ? $newTileData['barrel'] : 0
            );
        }

        $this->eventLog(clienttranslate('${character_name} placed ${buttons}'), [
            'buttons' => notifyButtons([
                [
                    'name' => $newTile['id'] === 'dinghy' ? clienttranslate('Dinghy') : $this->decks->getDeckName('tile'),
                    'dataId' => $newTile['id'],
                    'dataType' => 'tile',
                ],
            ]),
            'character_name' => $this->getCharacterHTML(),
        ]);
        $this->nextState('finalizeTile');
        $this->completeAction(false);
    }
    public function getTokenData(array $card)
    {
        $d = explode('_', $card['type_arg']);
        $token = $d[0];
        $treasure = array_key_exists(1, $d) ? $d[1] : '';
        return ['token' => $token, 'treasure' => $treasure, 'id' => $card['id'], 'isTreasure' => false];
    }
    public function argPlaceTile()
    {
        $result = [
            'actions' => [
                [
                    'action' => 'actPlaceTile',
                    'type' => 'action',
                ],
            ],
            'lastPlacedTileId' => $this->gameData->get('lastPlacedTileId'),
            'character_name' => $this->getCharacterHTML(),
            'newTile' => $this->gameData->get('newTile'),
            'validLocations' => $this->gameData->get('newTile')
                ? $this->getTilePlacementLocations($this->gameData->get('newTile')['id'] === 'dinghy')
                : [],
        ];
        $this->getTiles($result);
        return $result;
    }
    public function argFinalizeTile()
    {
        $result = [
            'actions' => [],
            'character_name' => $this->getCharacterHTML(),
        ];
        $this->getTiles($result);
        return $result;
    }
    public function argBasic()
    {
        $result = [
            'actions' => [],
            'character_name' => $this->getCharacterHTML(),
        ];
        $this->getTiles($result);
        return $result;
    }

    public function stInitializeTile()
    {
        if ($this->decks->getDeck('tile')->getCardOnTop('deck')) {
            if ($this->gameData->get('newTile') == null) {
                $card = $this->decks->pickCard('tile');
                $this->gameData->set('newTile', $card);
                $this->gameData->set('newTileCount', $this->gameData->get('newTileCount') + 1);
            }
            if (sizeof($this->getTilePlacementLocations(false)) > 0) {
                $this->nextState('placeTile');
            } else {
                $this->lose('trapped');
            }
        } elseif (!$this->gameData->get('dinghyChecked')) {
            $this->gameData->set('dinghyChecked', true);

            if (sizeof($this->getTilePlacementLocations(true)) > 0) {
                $this->decks->shuffleInCard('tile', 'dinghy', 'hand', false);
                $this->decks->shuffleInCard('tile', 'dinghy', 'discard', false); // TODO remove for new games

                $card = $this->decks->pickCard('tile');
                $this->gameData->set('newTile', $card);
                $this->gameData->set('newTileCount', $this->gameData->get('newTileCount') + 1);
                $this->nextState('placeTile');
            } else {
                $this->gameData->set('newTile', null);
                $this->gameData->set('newTileCount', 0);
                $this->nextState('playerTurn');
            }
        } else {
            $this->gameData->set('newTile', null);
            $this->gameData->set('newTileCount', 0);
            $this->nextState('playerTurn');
        }
    }

    public function stFinalizeTile()
    {
        if ($this->gameData->get('round') == 1 && $this->gameData->get('newTileCount') < 2) {
            $this->gameData->set('newTile', null);
            $this->nextState('initializeTile');
        } elseif (!$this->decks->getDeck('tile')->getCardOnTop('deck') && !$this->gameData->get('dinghyChecked')) {
            $this->nextState('initializeTile');
        } else {
            $this->gameData->set('newTile', null);
            $this->gameData->set('newTileCount', 0);
            $this->nextState('playerTurn');
        }
    }
    public function getTokens(): array
    {
        $xy = $this->getCharacterPos($this->character->getTurnCharacterId());
        $xyId = $this->map->xy(...$xy);
        $tokenPositions = $this->getTokenPositions();
        if (array_key_exists($xyId, $tokenPositions)) {
            return array_values(
                array_map(
                    function ($d) {
                        $data = $this->data->getTreasure()[$d['token']];
                        return [
                            'name' => $d['token'],
                            'tokenName' => $data['name'],
                            'id' => $d['id'],
                            'type' => $data['deckType'],
                            'battle' => array_key_exists('fatigue', $data) ? $data['fatigue'] : 0,
                        ];
                    },
                    array_filter($tokenPositions[$xyId], function ($d) {
                        return $d['isTreasure'];
                    })
                )
            );
        }
        return [];
    }
    public function getEnemies(bool $includeAdjacent = false, ?string $characterId = null): array
    {
        $xy = $this->getCharacterPos($characterId ?? $this->character->getTurnCharacterId());
        return $this->getEnemiesByLocation($includeAdjacent, $xy);
    }
    public function getEnemiesByLocation(bool $includeAdjacent, array $xy): array
    {
        $tokenPositions = $this->getTokenPositions();
        $xyIds = [$this->map->xy(...$xy)];
        if ($includeAdjacent) {
            if ($this->map->isEscapeTile($this->map->xy(...$xy))) {
                $tiles = $this->map->getEscapeTiles();
                foreach ($tiles as $tile) {
                    $xyIds = array_merge(
                        $xyIds,
                        array_map(function ($tile) {
                            return $this->map->xy($tile['x'], $tile['y']);
                        }, $this->map->getValidAdjacentTiles($tile['x'], $tile['y']))
                    );
                }
            } else {
                $xyIds = array_merge(
                    $xyIds,
                    array_map(function ($tile) {
                        return $this->map->xy($tile['x'], $tile['y']);
                    }, $this->map->getValidAdjacentTiles(...$xy))
                );
            }
        }
        $enemies = [];
        foreach ($xyIds as $xyId) {
            if (array_key_exists($xyId, $tokenPositions)) {
                $enemies = array_merge(
                    $enemies,
                    array_values(
                        array_map(
                            function ($d) use ($xyId) {
                                $pos = $this->map->getXY($xyId);
                                return [...$d, 'pos' => ['x' => $pos['x'], 'y' => $pos['y']]];
                            },
                            array_filter($tokenPositions[$xyId], function ($d) {
                                return !$d['isTreasure'];
                            })
                        )
                    )
                );
            }
        }
        return array_values(
            array_map(function ($d) use ($xy, $includeAdjacent) {
                $data = $this->data->getTreasure()[$d['token']];
                $direction = $this->map->getTileDirection(['x' => $xy[0], 'y' => $xy[1]], $d['pos']);
                return [
                    'name' => $d['token'],
                    'enemyName' => $data['name'],
                    'id' => $d['id'],
                    'type' => $data['deckType'],
                    'battle' => $data['battle'],
                    'pos' => [$d['pos']['x'], $d['pos']['y']],
                    'suffix' => $includeAdjacent ? $this->map->directionToName($direction) : '',
                ];
            }, $enemies)
        );
        return [];
    }
    public function stPlayerState()
    {
        $this->gameData->set('battleLocationState', null);
    }
    public function getUnequippedItems(): array
    {
        $equippedItems = array_filter(
            array_merge(
                array_map(function ($d) {
                    return $d['item'];
                }, $this->character->getAllCharacterData(true))
            )
        );
        $items = [...$this->data->getItems()];
        array_walk($equippedItems, function ($d) use (&$items) {
            unset($items[$d['id']]);
        });
        return array_values(array_diff(array_values(toId($items)), $this->gameData->get('destroyedItems')));
    }
    public function actSwapItem(): void
    {
        $equippedItems = array_map(
            function ($d) {
                return ['id' => $d['item']['id'], 'characterId' => $d['id'], 'isActive' => $d['isActive']];
            },
            array_filter($this->character->getAllCharacterData(true), function ($d) {
                return $d['item'] && !$d['isActive'];
            })
        );

        $items = $this->getUnequippedItems();
        $items = array_map(function ($d) {
            return ['id' => $d];
        }, $items);

        $this->selectionStates->initiateState(
            'itemSelection',
            [
                'items' => [...$items, ...array_filter($equippedItems)],
                'id' => 'actSwapItem',
                'swap' => 'init',
            ],
            $this->character->getTurnCharacterId(),
            true
        );
        $this->completeAction();
    }
    public function actMoveCrew(?int $x, ?int $y): void
    {
        $this->selectionStates->actMoveCrew($x, $y);
    }
    public function actMoveSelection(?int $x, ?int $y): void
    {
        $this->selectionStates->actMoveSelection($x, $y);
    }
    public function actSelectCard(?string $cardId): void
    {
        $this->selectionStates->actSelectCard($cardId);
    }
    public function actMove(?int $x, ?int $y): void
    {
        $this->_actMove('actMove', $x, $y);
    }
    public function _actMove(string $functionName, ?int $x, ?int $y, ?string $characterId = null): void
    {
        $character = $characterId ? $this->character->getCharacterData($characterId) : $this->character->getTurnCharacter();
        $this->actInterrupt->interruptableFunction(
            __FUNCTION__,
            func_get_args(),
            [$this->hooks, 'onMove'],
            function (Game $_this) use ($x, $y, $character, $functionName) {
                if ($x === null || $y === null) {
                    throw new BgaUserException(clienttranslate('Select a location'));
                }
                $moves = $this->map->calculateMoves(true, $character['id'])['fatigueList'];
                $tile = $this->map->getTileByXY($x, $y);
                $fatigue = (int) $moves[$tile['id']];
                if ($character['fatigue'] + $fatigue >= $character['maxFatigue']) {
                    throw new BgaUserException(clienttranslate('Not enough fatigue'));
                }
                return [
                    'x' => $x,
                    'y' => $y,
                    'fatigue' => $fatigue,
                    'character' => $character,
                    'tile' => $tile,
                    'currentFunctionName' => $functionName,
                ];
            },
            function (Game $_this, bool $finalizeInterrupt, $data, $character) use ($functionName) {
                $fatigue = $data['fatigue'];
                $x = $data['x'];
                $y = $data['y'];
                $character = $data['character'];
                $tile = $data['tile'];
                $this->character->adjustFatigue($character['id'], $fatigue);
                if ($functionName === 'actMove') {
                    $this->actions->spendActionCost('actMove');
                }
                $this->gameData->set('characterPositions', [...$this->gameData->get('characterPositions'), $character['id'] => [$x, $y]]);
                $this->markChanged('player');
                if (array_key_exists('escape', $tile) && $tile['escape'] == 1) {
                    $this->gameData->set('escaped', true);
                    $count = (int) ceil($this->character->getCharacterData($character['id'])['fatigue'] / 2);
                    $this->character->adjustFatigue($character['id'], -$count);
                    $this->eventLog(clienttranslate('${character_name} is taking a breather and recovered ${count} fatigue'), [
                        'usedActionId' => 'actMove',
                        'count' => $count,
                    ]);

                    if ($this->actions->hasTreasure()) {
                        $characterId = $character['id'];
                        $tokenItems = $this->gameData->get('tokenItems');
                        $tokenItems[$characterId] = array_values(
                            array_filter($tokenItems[$characterId], function ($d) {
                                return $d['treasure'] !== 'treasure';
                            })
                        );
                        $this->gameData->set('tokenItems', $tokenItems);
                        $this->gameData->set('treasures', $this->gameData->get('treasures') + 1);
                        $this->incStat(1, 'treasure_recovered');
                        $this->incStat(1, 'treasure_recovered', $character['playerId']);

                        $this->eventLog(clienttranslate('${character_name} looted a treasure'), [
                            'usedActionId' => 'actMove',
                        ]);
                        $this->markChanged('player');
                    }
                    $this->checkWin();
                } else {
                    $this->eventLog(clienttranslate('${character_name} moved'), [
                        'usedActionId' => 'actMove',
                    ]);
                }
                $this->undo->saveState();
                $this->hooks->onMoveFinalize($data);
                if ($this->gamestate->state(true, false, true)['name'] === 'playerTurn') {
                    $this->battle->battleLocation('playerTurn');
                    // Is already player turn so don't need to call nextState
                }
            }
        );
        $this->completeAction();
    }
    public function actEliminateDeckhand(#[JsonParam] array $data): void
    {
        if (!$data || sizeof($data) == 0) {
            throw new BgaUserException(clienttranslate('Must select a deckhand'));
        }
        if (sizeof($data) > $this->getDeckhandTargetCount()) {
            throw new BgaUserException(clienttranslate('Invalid Selection'));
        }
        foreach ($data as $deckhandTargets) {
            $this->map->decreaseDeckhand($deckhandTargets['x'], $deckhandTargets['y']);
        }
        $this->incStat(sizeof($data), 'deckhands_eliminated', $this->character->getTurnCharacter()['playerId']);
        $this->actions->spendActionCost('actEliminateDeckhand');
        $this->eventLog(clienttranslate('${character_name} eliminated ${count} deckhand(s)'), [
            'usedActionId' => 'actEliminateDeckhand',
            'count' => sizeof($data),
        ]);
        $hookData = [];
        $this->hooks->onEliminateDeckhands($hookData);
        $this->markChanged('map');
        $this->completeAction();
    }
    public function actIncreaseBattleStrength(): void
    {
        $this->actions->spendActionCost('actIncreaseBattleStrength');
        $this->character->updateCharacterData($this->character->getTurnCharacterId(), function (&$data) {
            $data['tempStrength']++;
        });
        $this->eventLog(clienttranslate('${character_name} increased their strength by 1'), [
            'usedActionId' => 'actIncreaseBattleStrength',
        ]);
        $this->completeAction();
    }

    public function actDrinkGrog(int $id): void
    {
        $characterId = $this->character->getTurnCharacterId();
        $tokenItems = $this->gameData->get('tokenItems');
        $key = array_search($id, toId($tokenItems[$characterId]));
        if ($key !== false) {
            $amount = $this->data->getTreasure()[$tokenItems[$characterId][$key]['treasure']]['fatigue'];
            unset($tokenItems[$characterId][$key]);
            $tokenItems[$characterId] = array_values($tokenItems[$characterId]);

            $this->gameData->set('tokenItems', $tokenItems);
            $this->actions->spendActionCost('actDrinkGrog');
            $this->character->adjustActiveFatigue(-$amount);
            $this->eventLog(clienttranslate('${character_name} drank grog and recovered ${count} fatigue'), [
                'usedActionId' => 'actDrinkGrog',
                'count' => $amount,
            ]);
            $hookData = [];
            $this->hooks->onDrinkGrog($hookData);
            $this->completeAction();
        } else {
            throw new BgaUserException(clienttranslate('Invalid Selection'));
        }
    }

    public function actPickupToken(string $item): void
    {
        $this->actions->spendActionCost('actPickupToken');

        $tokenPositions = $this->getTokenPositions();
        $characterId = $this->character->getTurnCharacterId();
        [$x, $y] = $this->getCharacterPos($characterId);
        $xyId = $this->map->xy($x, $y);
        $key = array_search(
            $item,
            array_map(function ($d) {
                return array_key_exists('treasure', $d) ? $d['treasure'] : '';
            }, $tokenPositions[$xyId])
        );
        if ($key !== false) {
            $token = $tokenPositions[$xyId][$key];
            unset($tokenPositions[$xyId][$key]);
            $tokenPositions[$xyId] = array_values($tokenPositions[$xyId]);
            $this->gameData->set('tokenPositions', $tokenPositions);

            $tokenItems = $this->gameData->get('tokenItems');
            if (!array_key_exists($characterId, $tokenItems)) {
                $tokenItems[$characterId] = [];
            }
            $tokenItems[$characterId][] = $token;
            $this->gameData->set('tokenItems', $tokenItems);
            $this->eventLog(clienttranslate('${character_name} picked up a ${item}'), [
                'i18n' => ['item'],
                'usedActionId' => 'actPickupToken',
                'item' => $this->data->getTreasure()[$item]['name'],
            ]);
            $this->markChanged('map');
            $this->markChanged('player');
            $this->completeAction();
        } else {
            throw new BgaUserException(clienttranslate('Invalid Selection'));
        }
    }

    public function drop(string $characterId, string $id): void
    {
        $tokenItems = $this->gameData->get('tokenItems');
        $key = array_search($id, toId($tokenItems[$characterId]));
        if ($key !== false) {
            $token = $tokenItems[$characterId][$key];
            $treasure = $this->data->getTreasure()[$token['treasure']];
            unset($tokenItems[$characterId][$key]);
            $tokenItems[$characterId] = array_values($tokenItems[$characterId]);
            $this->gameData->set('tokenItems', $tokenItems);

            [$x, $y] = $this->getCharacterPos($characterId);
            $xyId = $this->map->xy($x, $y);
            $tokenPositions = $this->getTokenPositions();
            if (!array_key_exists($xyId, $tokenPositions)) {
                $tokenPositions[$xyId] = [];
            }
            $tokenPositions[$xyId][] = $token;
            $tokenPositions[$xyId] = array_values($tokenPositions[$xyId]);
            $this->gameData->set('tokenPositions', $tokenPositions);

            $this->eventLog(clienttranslate('${character_name} dropped a ${item}'), [
                'i18n' => ['item'],
                'usedActionId' => 'actDrop',
                'item' => $treasure['name'],
                'character_name' => $this->getCharacterHTML($characterId),
            ]);
            $this->markChanged('map');
            $this->markChanged('player');
        } else {
            throw new BgaUserException(clienttranslate('Invalid Selection'));
        }
    }

    public function actDrop(string $id): void
    {
        $characterId = $this->character->getTurnCharacterId();

        $this->actions->spendActionCost('actDrop');
        $this->drop($characterId, $id);
        $this->completeAction();
    }

    public function actRest(): void
    {
        $this->actions->spendActionCost('actRest');
        $data = [];
        $this->hooks->onRest($data);
        $this->character->adjustActiveFatigue(-2);
        $this->eventLog(clienttranslate('${character_name} rested and recovered ${count} fatigue'), [
            'usedActionId' => 'actRest',
            'count' => 2,
        ]);
        $this->completeAction();
    }
    public function actFightFire(?int $x, ?int $y, ?int $by = 1): void
    {
        if ($x === null || $y === null) {
            throw new BgaUserException(clienttranslate('Select a location'));
        }
        $tileId = $this->map->getTileByXY($x, $y)['id'];
        $fires = $this->map->calculateFires();
        if (!in_array($tileId, $fires)) {
            throw new BgaUserException(clienttranslate('Invalid Selection'));
        }
        if ($by == 2) {
            usePerTurn('blanket', $this);
        }

        $data = [
            'x' => $x,
            'y' => $y,
        ];
        $this->hooks->onFightFire($data);
        $this->actions->spendActionCost('actFightFire');
        $this->map->decreaseFire($x, $y, $by);
        $this->eventLog(clienttranslate('${character_name} lowered a fire by ${count}'), [
            'usedActionId' => 'actFightFire',
            'count' => $by,
        ]);
        $this->completeAction();
    }
    public function actUseSkill(string $skillId, ?string $skillSecondaryId = null): void
    {
        if ($this->gamestate->state(true, false, true)['name'] == 'playerTurn') {
        }
        $this->actInterrupt->interruptableFunction(
            __FUNCTION__,
            func_get_args(),
            [$this->hooks, 'onUseSkill'],
            function (Game $_this) use ($skillId, $skillSecondaryId) {
                $_this->character->setSubmittingCharacter('actUseSkill', $skillId);
                // $this->character->addExtraTime();
                $_this->actions->validateCanRunAction('actUseSkill', $skillId);
                $res = $_this->character->getSkill($skillId);
                $skill = $res['skill'];
                $character = $res['character'];
                $_this->character->setSubmittingCharacter(null);
                return [
                    'skillId' => $skillId,
                    'skillSecondaryId' => $skillSecondaryId,
                    'skill' => $skill,
                    'character' => $character,
                    'turnCharacter' => $this->character->getTurnCharacter(),
                    'nextState' => $this->gamestate->state(true, false, true)['name'] == 'dayEvent' ? 'playerTurn' : false,
                ];
            },
            function (Game $_this, bool $finalizeInterrupt, $data) {
                $skill = $data['skill'];
                $character = $data['character'];
                $skillId = $data['skillId'];
                $skillSecondaryId = array_key_exists('skillSecondaryId', $data) ? $data['skillSecondaryId'] : null;
                $_this->hooks->reconnectHooks($skill, $_this->character->getSkill($skillId)['skill']);
                $_this->character->setSubmittingCharacter('actUseSkill', $skillId);
                $notificationSent = false;
                $skill['sendNotification'] = function () use (&$skill, $_this, &$notificationSent) {
                    $_this->notify('notify', clienttranslate('${character_name} used the skill ${skill_name}'), [
                        'i18n' => ['skill_name'],
                        'skill_name' => $skill['name'],
                        'usedActionId' => 'actUseSkill',
                        'usedActionName' => $skill['name'],
                    ]);
                    $notificationSent = true;
                };
                if ($_this->gamestate->state(true, false, true)['name'] == 'interrupt') {
                    // Only applies to skills from an interrupt state
                    if (!$notificationSent && (!$data || !array_key_exists('notify', $data) || $data['notify'] != false)) {
                        $skill['sendNotification']();
                    }
                    $_this->actInterrupt->actInterrupt($skillId, $skillSecondaryId);
                    $_this->actions->spendActionCost('actUseSkill', $skillId);
                    $_this->character->setSubmittingCharacter('actUseSkill', $skillId);
                }
                if (!array_key_exists('interruptState', $skill) || (in_array('interrupt', $skill['state']) && $finalizeInterrupt)) {
                    $result = array_key_exists('onUse', $skill) ? $skill['onUse']($this, $skill, $character) : null;
                    if (!$result || !array_key_exists('spendActionCost', $result) || $result['spendActionCost'] != false) {
                        $_this->actions->spendActionCost('actUseSkill', $skillId);
                    }
                    if (!$notificationSent && (!$result || !array_key_exists('notify', $result) || $result['notify'] != false)) {
                        $skill['sendNotification']();
                    }
                    if ($result && array_key_exists('nextState', $result)) {
                        $data['nextState'] = $result['nextState'];
                    }
                }
                $_this->character->setSubmittingCharacter(null);
                if ($data['nextState']) {
                    $this->nextState($data['nextState']);
                }
            }
        );
        $this->completeAction();
    }
    public function actEndTurn(): void
    {
        $this->gameData->set('battleLocationState', null);
        // TODO: Can't end turn early if sweltering
        // Notify all players about the choice to pass.
        // at the end of the action, move to the next state
        $this->endTurn();
        $this->completeAction(false);
    }
    #[CheckAction(false)]
    public function actForceSkip(): void
    {
        $this->gamestate->checkPossibleAction('actForceSkip');
        $stateName = $this->gamestate->state(true, false, true)['name'];
        if ($stateName == 'interrupt') {
            if (!$this->actInterrupt->onInterruptCancel(true)) {
                $this->nextState('playerTurn');
            }
            $this->completeAction();
        } elseif ($stateName == 'dinnerPhase') {
            $this->gamestate->unsetPrivateStateForAllPlayers();
            $this->nextState('nightPhase');
        }
    }
    #[CheckAction(false)]
    public function actUnBack(): void
    {
        $this->gamestate->checkPossibleAction('actUnBack');
        $stateName = $this->gamestate->state(true, false, true)['name'];
        if ($stateName == 'characterSelect') {
            $this->characterSelection->actUnBack();
        }
    }
    public function actDone(): void
    {
        // $this->character->addExtraTime();
        $saveState = true;
        $stateName = $this->gamestate->state(true, false, true)['name'];
        if ($stateName == 'battleSelection') {
            $this->nextState('playerTurn');
        } elseif ($stateName == 'tradePhase') {
            $this->nextState('playerTurn');
        } elseif ($stateName == 'interrupt') {
            if (!$this->actInterrupt->onInterruptCancel()) {
                $this->nextState('playerTurn');
            }
        }
        $this->completeAction($saveState);
    }
    public function actFightMe(string $characterId)
    {
        $this->battle->actFightMe($characterId);
    }
    public function actDontFight()
    {
        $this->battle->actDontFight();
    }
    public function argCharacterBattleSelection()
    {
        return $this->battle->argCharacterBattleSelection();
    }
    public function stCharacterBattleSelection()
    {
        $this->battle->stCharacterBattleSelection();
    }
    public function stStartCharacterBattleSelection()
    {
        $this->battle->stStartCharacterBattleSelection();
    }
    public function argDrawRevengeCard()
    {
        $result = [
            'character_name' => $this->getCharacterHTML(),
            'activeTurnPlayerId' => 0,
        ];
        $this->getDecks($result);
        return $result;
    }

    public function argNextCharacter()
    {
        $result = [
            'character_name' => $this->getCharacterHTML(),
            'activeTurnPlayerId' => 0,
        ];
        $this->getAllPlayers($result);
        $this->getTiles($result);
        return $result;
    }
    public function actBattleSelection(int $targetId)
    {
        $this->battle->actBattleSelection($targetId);
    }
    public function actUseStrength()
    {
        $this->battle->actUseStrength();
    }
    public function actDontUseStrength()
    {
        $this->battle->actDontUseStrength();
    }
    public function stBattleSelection()
    {
        $this->battle->stBattleSelection();
    }
    public function argBattleSelection()
    {
        return $this->battle->argBattleSelection();
    }
    public function argBattle()
    {
        return $this->battle->argBattle();
    }
    public function actBattleAgain()
    {
        $this->battle->actBattleAgain();
    }
    public function actMakeThemFlee(?string $targetId = null)
    {
        $this->battle->actMakeThemFlee($targetId);
    }
    public function actRetreat()
    {
        $this->battle->actRetreat();
    }
    public function stPostBattle()
    {
        $this->battle->stPostBattle();
    }
    public function argPostBattle()
    {
        return $this->battle->argPostBattle();
    }
    public function actCancel(): void
    {
        $this->selectionStates->actCancel();
    }
    public function argSelectionState(): array
    {
        return $this->selectionStates->argSelectionState();
    }
    public function actSelectItem(?string $itemId = null): void
    {
        $this->selectionStates->actSelectItem($itemId);
    }
    public function actSelectCharacter(?string $characterId = null): void
    {
        $this->selectionStates->actSelectCharacter($characterId);
    }
    public function crewMove(): void
    {
        $this->map->crewMove();
        if (sizeof($this->selectionStates->getPendingStates()) == 0) {
            $this->battle->battleLocation('nextCharacter');
        }
        $this->completeAction();
    }
    public function stDrawRevengeCard()
    {
        $currentState = $this->gamestate->state(true, false, true)['name'];
        $this->actInterrupt->interruptableFunction(
            __FUNCTION__,
            func_get_args(),
            [$this->hooks, 'onDrawRevengeCard'],
            function (Game $_this) {
                // deck,card
                $card = $this->decks->pickCard('revenge');
                $this->cardDrawEvent($card, 'revenge');
                return ['card' => $card, 'state' => 'revenge'];
            },
            function (Game $_this, bool $finalizeInterrupt, $data) use ($currentState) {
                $card = $data['card'];
                $_this->hooks->reconnectHooks($card, $_this->decks->getCard($card['id']));
                $nextState = true;
                $this->eventLog(clienttranslate('${buttons} ${color} ${number}s increase'), [
                    'buttons' => notifyButtons([
                        ['name' => $this->decks->getDeckName($card['deck']), 'dataId' => $card['id'], 'dataType' => 'revenge'],
                    ]),
                    'number' => $card['dice'],
                    'color' =>
                        $card['color'] === 'both'
                            ? clienttranslate('All')
                            : ($card['color'] === 'red'
                                ? clienttranslate('Red')
                                : clienttranslate('Yellow')),
                ]);
                if (array_key_exists('action', $card)) {
                    if ($card['action'] === 'deckhand-spread') {
                        $this->map->spreadDeckhand();
                        $this->eventLog(clienttranslate('${buttons} The deckhands spread'), [
                            'buttons' => notifyButtons([
                                ['name' => $this->decks->getDeckName($card['deck']), 'dataId' => $card['id'], 'dataType' => 'revenge'],
                            ]),
                        ]);
                    } elseif ($card['action'] === 'deckhand-spawn') {
                        $this->map->increaseDeckhand();
                        $this->eventLog(clienttranslate('${buttons} The deckhands spawn'), [
                            'buttons' => notifyButtons([
                                ['name' => $this->decks->getDeckName($card['deck']), 'dataId' => $card['id'], 'dataType' => 'revenge'],
                            ]),
                        ]);
                    } elseif ($card['action'] === 'crew-move') {
                        $this->map->crewMove();
                        $this->eventLog(clienttranslate('${buttons} The crew moves'), [
                            'buttons' => notifyButtons([
                                ['name' => $this->decks->getDeckName($card['deck']), 'dataId' => $card['id'], 'dataType' => 'revenge'],
                            ]),
                        ]);
                    }
                }
                $this->map->increaseFire($card['dice'], $card['color']);
                if (
                    sizeof(
                        array_filter($this->decks->listDeckDiscards(['revenge']), function ($d) {
                            return array_key_exists('dice', $d) && $d['dice'] == 5;
                        })
                    ) == 3
                ) {
                    $this->decks->shuffleInDiscard('revenge');
                }
                if ($currentState === $this->gamestate->state(true, false, true)['name']) {
                    if ($this->battle->battleLocation('nextCharacter') == 0) {
                        $this->nextState('nextCharacter');
                    }
                }
            }
        );
    }

    public function argSelectionCount(): array
    {
        return $this->characterSelection->argSelectionCount();
    }
    public function log(...$args)
    {
        $e = new \Exception();
        $stack = preg_split('/[\r\n]+/', $e->getTraceAsString());
        $stackString = join(
            PHP_EOL,
            array_map(
                function ($d) {
                    return '&nbsp;&nbsp;&nbsp;&nbsp;' .
                        preg_replace('/Bga\\\\Games\\\\[^\\\\]+\\\\/', '', preg_replace('/#.*modules\\/(.*\d\\)): (.*)/', '$1: $2', $d));
                },
                array_filter($stack, function ($d) {
                    return str_contains($d, '/games/');
                })
            )
        );
        // preg_match('/#0.*modules\/(.*\d\)):/', $e->getTraceAsString(), $m);  . (array_key_exists(1, $m) ? ' [' . $m[1] . ']' : '')
        if ($this->gamestate == null) {
            $this->trace('TRACE [__init] ' . json_encode($args) . PHP_EOL . $stackString);
        } else {
            $this->trace(
                'TRACE [' . $this->gamestate->state(true, false, true)['name'] . '] ' . json_encode($args) . PHP_EOL . $stackString
            );
        }
    }
    public function argPlayerState(): array
    {
        $result = [...$this->getArgsData()];

        $decksDiscards = $this->gameData->get('tempDeckDiscard');
        if ($decksDiscards) {
            unset($result['decksDiscards']);
            $this->gameData->set('tempDeckDiscard', null);
        }
        return $result;
    }
    public function argInterrupt(): array
    {
        return $this->actInterrupt->argInterrupt();
    }
    public function stInterrupt(): void
    {
        $this->actInterrupt->stInterrupt();
    }

    /**
     * Compute and return the current game progression.
     *
     * The number returned must be an integer between 0 and 100.
     *
     * This method is called each time we are in a game state with the "updateGameProgression" property set to true.
     *
     * @return int
     * @see ./states.inc.php
     */
    public function getGameProgression()
    {
        // Compute and return the game progression
        $treasureCount = $this->gameData->get('treasures');
        return max((sizeof($this->map->getMap()) / 26) * 0.75, $treasureCount / max($this->getTreasuresNeeded(), 1)) * 100;
    }
    public function endTurn()
    {
        $leftOverActions = $this->character->getTurnCharacter()['actions'];
        $this->character->adjustActiveActions(-20);
        if ($leftOverActions > 0) {
            $this->gameData->set('tempActions', $leftOverActions);
            $this->eventLog(clienttranslate('${character_name} ends their turn and passes ${count} action(s)'), [
                'usedActionId' => 'actEndTurn',
                'count' => $leftOverActions,
            ]);
        } else {
            $this->gameData->set('tempActions', 0);
            $this->eventLog(clienttranslate('${character_name} ends their turn'), [
                'usedActionId' => 'actEndTurn',
            ]);
        }

        $data = [
            'characterId' => $this->character->getTurnCharacterId(),
        ];
        $this->hooks->onEndTurn($data);
        $this->gameData->set('revengePhase', true);
        $this->gameData->set('actionsTaken', 0);
        $this->nextState('drawRevengeCard');
        $this->undo->clearUndoHistory();
    }
    public function stGameStart(): void
    {
        if ($this->gameData->get('randomSelection')) {
            $this->characterSelection->randomCharacters();
            $this->nextState('initializeTile');
        } else {
            $this->nextState('characterSelect');
        }
    }
    /**
     * The action method of state `nextCharacter` is called every time the current game state is set to `nextCharacter`.
     */
    public function stNextCharacter(): void
    {
        $this->incStat(1, 'turn_count');
        $this->gameData->set('revengePhase', false);
        $this->character->activateNextCharacter();
        $this->gameData->set('escaped', false);
        resetPerTurn($this);
        $this->nextState('initializeTile');
        $this->notify('playerTurn', clienttranslate('${character_name} begins their turn'), []);
    }
    public function stSelectCharacter()
    {
        $this->gamestate->setAllPlayersMultiactive();
        foreach ($this->gamestate->getActivePlayerList() as $key => $playerId) {
            $this->giveExtraTime((int) $playerId);
        }
    }
    public function win()
    {
        $eloMapping = [7, 10, 15];

        $score =
            $eloMapping[$this->gameData->get('difficulty')] +
            ($this->gameData->get('captainFromm') ? 2 : 0) -
            ($this->gameData->get('powderKegExplosion') ? 3 : 0);
        $this->DbQuery("UPDATE player SET player_score={$score} WHERE 1=1");
        $this->eventLog(clienttranslate('Win!'));
        $this->nextState('endGame');
    }
    public function lose(string $reason)
    {
        if ($reason === 'explosion') {
            $this->eventLog(clienttranslate('The ship exploded, the ship is lost'));
        } elseif ($reason === 'deckhand') {
            $this->eventLog(clienttranslate('The pirates are overwhelmed by deckhands, the expedition is lost'));
        } elseif ($reason === 'noPirates') {
            $this->eventLog(clienttranslate('There are no more replacement pirates, the expedition is lost'));
        } elseif ($reason === 'untimely') {
            $this->eventLog(clienttranslate('Untimely death, the other pirates give up'));
        } elseif ($reason === 'treasure') {
            $this->eventLog(clienttranslate('The treasure has been lost'));
        } elseif ($reason === 'trapped') {
            $this->eventLog(clienttranslate('The ship can\'t be searched, the expedition is lost'));
        }
        $this->DbQuery('UPDATE player SET player_score=0 WHERE 1=1');
        $this->nextState('endGame');
    }

    /**
     * Migrate database.
     *
     * You don't have to care about this until your game has been published on BGA. Once your game is on BGA, this
     * method is called everytime the system detects a game running with your old database scheme. In this case, if you
     * change your database scheme, you just have to apply the needed changes in order to update the game database and
     * allow the game to continue to run with your new version.
     *
     * @param int $from_version
     * @return void
     */
    public function upgradeTableDb($from_version)
    {
        if ($from_version <= 2507270000) {
            foreach ($this->character->getAllCharacterData(false) as $char) {
                if (!$char['item']) {
                    $items = array_map(function ($d) {
                        return ['id' => $d];
                    }, $this->getUnequippedItems());
                    $this->character->updateCharacterData($char['id'], function (&$data) use ($items) {
                        $data['item'] = $items[0]['id'];
                    });
                }
            }
        }
        //     // ! important ! Use DBPREFIX_<table_name> for all tables
        //     try {
        //         $sql = 'ALTER TABLE DBPREFIX_item ADD  `last_owner` varchar(10)';
        //         $this->applyDbUpgradeToAllDB($sql);
        //     } catch (Exception $e) {
        //     }
        // }
        //
        //       if ($from_version <= 1405061421)
        //       {
        //            // ! important ! Use DBPREFIX_<table_name> for all tables
        //
        //            $sql = "CREATE TABLE DBPREFIX_xxxxxxx ....";
        //            $this->applyDbUpgradeToAllDB( $sql );
        //       }
    }
    public function getAllPlayers(&$result): void
    {
        $stateName = $this->gamestate->state(true, false, true)['name'];
        if (!($stateName === 'characterSelect' || $stateName === 'gameSetup')) {
            $xy = $this->getCharacterPos($this->character->getTurnCharacterId());
            $result['currentPosition'] = $this->map->xy(...$xy);
            $result['activeCharacter'] = $this->character->getTurnCharacterId();
            $result['remainingCharacters'] = sizeof($this->getRemainingCharacters());
        }
        $result['characters'] = $this->character->getMarshallCharacters();
        $result['characterPositions'] = array_reduce(
            $result['characters'],
            function ($arr, $char) {
                $arr[$char['pos'][0] . 'x' . $char['pos'][1]][] = ['name' => $char['id'], 'id' => $char['id'], 'type' => 'character'];
                return $arr;
            },
            []
        );
        $result['players'] = $this->getCollectionFromDb('SELECT `player_id` `id`, player_no, `player_name` as `name` FROM `player`');
    }
    public function getDecks(&$result): void
    {
        $data = $this->decks->getDecksData();
        $result['decks'] = $data['decks'];
        $result['decksDiscards'] = $data['decksDiscards'];
    }
    public function getTiles(&$result): void
    {
        $result['tiles'] = array_map(function ($d) {
            unset($d['connections']);
            return $d;
        }, array_values($this->map->getMap()));
        $result['testSpreadDeckhand'] = $this->map->testSpreadDeckhand();
        $result['testIncreaseDeckhand'] = $this->map->testIncreaseDeckhand();

        $result['explosions'] = $this->gameData->get('explosions');
        $result['tokenPositions'] = array_map(function ($tokens) {
            return array_map(function ($d) {
                if ($d['isTreasure']) {
                    return ['name' => $d['treasure'], 'oldName' => $d['token'], 'id' => $d['id'], 'type' => 'treasure'];
                } else {
                    return ['name' => $d['token'], 'id' => $d['id'], 'type' => 'enemy'];
                }
            }, $tokens);
        }, $this->getTokenPositions());
        $this->getAllPlayers($result);
    }
    public function getItemData(&$result): void
    {
        $result['treasuresLooted'] = $this->gameData->get('treasures');
        $result['treasuresNeeded'] = $this->getTreasuresNeeded();
        $result['availableItems'] = $this->getUnequippedItems();
    }
    public function getGameData(&$result): void
    {
        $result['game'] = $this->gameData->getAll();
        // Need to remove these otherwise the response is too big
        foreach (array_keys($result['game']) as $key) {
            if (str_contains($key, 'State')) {
                unset($result['game'][$key]);
            }
        }

        unset($result['game']['state']);
        unset($result['game']['resources']);
        unset($result['game']['destroyedResources']);
    }
    public function getExpansion()
    {
        $expansionMapping = self::$expansionList;
        return $expansionMapping[$this->gameData->get('expansion')];
    }
    public function isValidExpansion(string $expansion)
    {
        $expansionI = array_search($this->getExpansion(), $this::$expansionList);
        if ($expansionI === false) {
            throw new Exception('Can\'t find expansion ' . $this->getExpansion() . ' in ' . json_encode($this::$expansionList));
        }
        $expansionList = $this::$expansionList;
        return array_search($expansion, $expansionList) <= $expansionI;
    }
    public function getDifficulty(): string
    {
        $difficultyMapping = ['normal', 'challenge', 'hard'];
        return $difficultyMapping[$this->gameData->get('difficulty')];
    }
    private array $changed = ['token' => false, 'player' => false, 'map' => false, 'actions' => false, 'playerColor' => false];
    public function markChanged(string $type)
    {
        if (!array_key_exists($type, $this->changed)) {
            throw new Exception('Mark missing key ' . $type);
        }
        $this->changed[$type] = true;
    }
    public function markRandomness()
    {
        $this->undo->clearUndoHistory();
    }
    public function completeAction(bool $saveState = true)
    {
        if ($saveState) {
            $this->undo->saveState();
        }
        if ($this->changed['token']) {
            $result = [];
            $this->getItemData($result);

            $this->notify('tokenUsed', '', ['gameData' => $result]);
        }
        $stateName = $this->gamestate->state(true, false, true)['name'];
        if ($this->changed['playerColor'] && $stateName === 'characterSelect') {
            $result = [];
            $this->getAllPlayers($result);

            $this->notify('updateCharacterData', '', ['gameData' => $result]);
        }
        if ($this->changed['player'] && !($stateName === 'characterSelect' || $stateName === 'gameSetup')) {
            $character = $this->character->getTurnCharacter(true);
            $result = [
                'activeCharacter' => $this->character->getTurnCharacterId(),
                'activePlayer' => $this->character->getTurnCharacterId(),
                'moves' => $this->map->calculateMoves()['fatigueList'],
                'fires' => $this->map->calculateFires(),
                'adjacentTiles' => $this->map->getValidAdjacentTiles(...$character['pos']),
            ];
            $this->getAllPlayers($result);
            $this->getItemData($result);

            $this->notify('updateCharacterData', '', ['gameData' => $result]);
        }
        if ($this->changed['map'] && !($stateName === 'characterSelect' || $stateName === 'gameSetup')) {
            $character = $this->character->getTurnCharacter(true);
            $result = [
                'activeCharacter' => $this->character->getTurnCharacterId(),
                'activePlayer' => $this->character->getTurnCharacterId(),
                'moves' => $this->map->calculateMoves()['fatigueList'],
                'fires' => $this->map->calculateFires(),
                'adjacentTiles' => $this->map->getValidAdjacentTiles(...$character['pos']),
            ];
            $this->getAllPlayers($result);
            $this->getTiles($result);
            $this->getItemData($result);
            $this->notify('updateMap', '', ['gameData' => $result]);
        }
        if (in_array($this->gamestate->state(true, false, true)['name'], ['playerTurn'])) {
            $character = $this->character->getTurnCharacter(true);
            $result = [
                'actions' => array_values($this->actions->getValidActions(true)),
                'actionCount' => $character['actions'] + ($character['isActive'] ? $this->gameData->get('tempActions') : 0),
                'tempActionCount' => $character['isActive'] ? $this->gameData->get('tempActions') : 0,
                'actionsTaken' => $this->gameData->get('actionsTaken'),
                'availableSkills' => $this->actions->getAvailableSkills(),
                'canUseBlanket' => getUsePerTurn('blanket', $this) == 0 && $character['item']['itemId'] === 'blanket',
                'canUndo' => $this->undo->canUndo(),
            ];
            $this->notify('updateActionButtons', '', ['gameData' => $result]);
        }
    }

    public function getArgsData(): array
    {
        $result = [
            'version' => $this->getVersion(),
            'activeCharacter' => $this->character->getTurnCharacterId(),
            'activeCharacters' => $this->gameData->getAllMultiActiveCharacterIds(),
            'tiles' => [],
        ];

        $stateName = $this->gamestate->state(true, false, true)['name'];
        if (!($stateName === 'characterSelect' || $stateName === 'gameSetup')) {
            $character = $this->character->getTurnCharacter(true);
            $result = [
                ...$result,
                'character_name' => $this->getCharacterHTML(),
                'actions' => array_values($this->actions->getValidActions(true)),
                'availableSkills' => $this->actions->getAvailableSkills(),
                'activeTurnPlayerId' => $character['player_id'],
                'moves' => $this->map->calculateMoves()['fatigueList'],
                'isStranded' => $this->map->isStranded(),
                'fires' => $this->map->calculateFires(),
                'adjacentTiles' => $this->map->getValidAdjacentTiles(...$character['pos']),
                'deckhandTargetCount' => $this->getDeckhandTargetCount(),
                'actionCount' => $character['actions'] + ($character['isActive'] ? $this->gameData->get('tempActions') : 0),
                'tempActionCount' => $character['isActive'] ? $this->gameData->get('tempActions') : 0,
                'actionsTaken' => $this->gameData->get('actionsTaken'),
                'canUseBlanket' => getUsePerTurn('blanket', $this) == 0 && $character['item'] && $character['item']['itemId'] === 'blanket',
            ];
            $this->getAllPlayers($result);
            $this->getTiles($result);
        }
        if (in_array($stateName, ['playerTurn', 'battleSelection'])) {
            $result['canUndo'] = $this->undo->canUndo();
        }
        $this->getDecks($result);
        $this->getGameData($result);
        $this->getItemData($result);

        return $result;
    }
    /*
     * Gather all information about current game situation (visible by the current player).
     *
     * The method is called each time the game interface is displayed to a player, i.e.:
     *
     * - when the game starts
     * - when a player refreshes the game page (F5)
     */
    public function getAllDatas(): array
    {
        $stateName = $this->gamestate->state(true, false, true)['name'];
        // Temp fix for stuck in battle
        if ($stateName === 'characterMovement') {
            $canMove = sizeof($this->map->calculateMoves(false)['fatigueList']) > 0;
            if (!$canMove) {
                $this->nextState('postBattle');
            }
        }
        // Temp fix for stuck tile placement
        if ($stateName === 'placeTile' && sizeof($this->map->getMap()) == 25) {
            if ($this->decks->getDeck('tile')->getCardOnTop('deck')) {
                $this->decks->discardCards('tile', 'discard', function () {
                    return true;
                });
                $this->gameData->set('dinghyChecked', true);
                if (sizeof($this->getTilePlacementLocations(true)) > 0) {
                    $this->decks->shuffleInCard('tile', 'dinghy', 'hand', false);
                    $this->decks->shuffleInCard('tile', 'dinghy', 'discard', false);

                    $card = $this->decks->pickCard('tile');
                    $this->gameData->set('newTile', $card);
                    $this->gameData->set('newTileCount', $this->gameData->get('newTileCount') + 1);
                } else {
                    $this->gameData->set('newTile', null);
                    $this->gameData->set('newTileCount', 0);
                    $this->nextState('playerTurn');
                }
            }
        }
        // Temp fix can remove later
        $deckData = $this->decks->getDecksData();
        if (
            $stateName === 'playerTurn' &&
            $deckData['decks']['tile']['count'] == 0 &&
            sizeof(
                array_filter($deckData['decksDiscards']['bag'], function ($d) {
                    return str_starts_with($d, 'guard');
                })
            ) > 0
        ) {
            $this->decks->discardCards('bag', 'discard', function ($data, $card) {
                $isGuard = str_contains($card['type_arg'], 'guard');
                if ($isGuard) {
                    $tokens = $this->getTokenPositions();
                    $pos = bga_rand(0, sizeof($tokens) - 1);
                    $i = 0;
                    array_walk($tokens, function (&$value) use ($card, &$i, $pos) {
                        if ($i === $pos) {
                            $value = [...$value, $this->getTokenData($card)];
                        }
                        $i++;
                    });
                    $this->gameData->set('tokenPositions', $tokens);
                }
                return $isGuard;
            });
        }

        if ($stateName === 'placeTile' && $this->gameData->get('newTile') && $this->gameData->get('newTile')['id'] !== 'dinghy') {
            if (sizeof($this->getTilePlacementLocations(false)) == 0) {
                $this->lose('trapped'); // Temp fix for game loss
            }
        }
        $result = [
            'version' => $this->getVersion(),
            'expansionList' => self::$expansionList,
            'expansion' => $this->getExpansion(),
            'difficulty' => $this->getDifficulty(),
            ...$this->getArgsData(),
        ];
        $this->getAllPlayers($result);

        return $result;
    }

    /**
     * Returns the game name.
     *
     * IMPORTANT: Please do not modify.
     */
    protected function getGameName()
    {
        return 'deadmentellnotales';
    }

    /**
     * This method is called only once, when a new game is launched. In this method, you must setup the game
     *  according to the game rules, so that the game is ready to be played.
     */
    protected function setupNewGame($players, $options = [])
    {
        $this->gameData->setup();
        // Set the colors of the players with HTML color code. The default below is red/green/blue/orange/brown. The
        // number of colors defined here must correspond to the maximum number of players allowed for the gams.
        $gameinfos = $this->getGameinfos();
        $default_colors = $gameinfos['player_colors'];
        // Create players based on generic information.
        //
        foreach ($players as $playerId => $player) {
            // Now you can access both $playerId and $player array
            $query_values[] = vsprintf("('%s', '%s', '%s', '%s', '%s')", [
                $playerId,
                array_shift($default_colors),
                $player['player_canal'],
                addslashes($player['player_name']),
                addslashes($player['player_avatar']),
            ]);
        }
        // NOTE: You can add extra field on player table in the database (see dbmodel.sql) and initialize
        // additional fields directly here.
        static::DbQuery(
            sprintf(
                'INSERT INTO player (player_id, player_color, player_canal, player_name, player_avatar) VALUES %s',
                implode(',', $query_values)
            )
        );

        $this->initStat('table', 'turn_count', 1);
        $this->initStat('table', 'explosions', 0);
        $this->initStat('table', 'rooms_lost', 0);
        $this->initStat('table', 'treasure_recovered', 0);
        $this->initStat('player', 'actions_used', 0);
        $this->initStat('player', 'fatigue_gained', 0);
        $this->initStat('player', 'deckhands_eliminated', 0);
        $this->initStat('player', 'treasure_recovered', 0);
        $this->initStat('player', 'crew_eliminated', 0);
        // $this->reattributeColorsBasedOnPreferences($players, $gameinfos['player_colors']);
        $players = $this->loadPlayersBasicInfos();

        $this->gameData->set('expansion', $this->getGameStateValue('expansion'));
        $this->gameData->set('difficulty', $this->getGameStateValue('difficulty'));
        $this->gameData->set('captainFromm', $this->getGameStateValue('captainFromm') == 0);
        $this->gameData->set('powderKegExplosion', $this->getGameStateValue('powderKegExplosion') == 0);

        $this->gameData->set(
            'characterCount',
            sizeof($players) === 1
                ? $this->getGameStateValue('soloCount')
                : (sizeof($players) === 2
                    ? $this->getGameStateValue('doubleCount')
                    : 1)
        );
        $this->gameData->set('randomSelection', $this->getGameStateValue('random') == 1);
        $this->decks = new DMTNT_Decks($this);
        $this->decks->setup();
        $this->map = new DMTNT_Map($this);
        $this->map->setup();

        // Activate first player once everything has been initialized and ready.
        $this->activeNextPlayer();
    }

    public function zombieBack(): void
    {
        $this->undo->clearUndoHistory();
        $returningPlayerId = $this->getCurrentPlayerId();
        $this->character->unZombiePlayer($returningPlayerId);
        $this->reloadPlayersBasicInfos();
        $this->character->clearCache();

        $stateName = $this->gamestate->state(true, false, true)['name'];
        $stateType = $this->gamestate->state()['type'];
        $this->log($stateName, $stateType, $returningPlayerId, $this->character->getTurnCharacter()['playerId']);
        if ($stateType === 'activeplayer') {
            if ($returningPlayerId == $this->character->getTurnCharacter()['playerId']) {
                $this->nextState('changeZombiePlayer');
                $this->gamestate->changeActivePlayer($returningPlayerId);
                $this->nextState($stateName);
            }
        } elseif ($stateType === 'multipleactiveplayer') {
            $this->gameData->resetMultiActiveCharacter();
        }
        $this->notify('zombieBackDLD', '', [
            'gameData' => $this->getAllDatas(),
        ]);
        $this->completeAction(false);
    }
    /**
     * This method is called each time it is the turn of a player who has quit the game (= "zombie" player).
     * You can do whatever you want in order to make sure the turn of this player ends appropriately
     * (ex: pass).
     *
     * Important: your zombie code will be called when the player leaves the game. This action is triggered
     * from the main site and propagated to the gameserver from a server, not from a browser.
     * As a consequence, there is no current player associated to this action. In your zombieTurn function,
     * you must _never_ use `getCurrentPlayerId()` or `getCurrentPlayerName()`, otherwise it will fail with a
     * "Not logged" error message.
     *
     * @param array{ type: string, name: string } $state
     * @param int $active_player
     * @return void
     * @throws feException if the zombie mode is not supported at this game state.
     */
    protected function zombieTurn(array $state, int $active_player): void
    {
        $this->undo->clearUndoHistory();

        $stateName = $state['name'];
        $characters = $this->character->getAllCharacterData(true);
        $mapping = [];
        $charactersToMove = [];
        array_walk($characters, function ($char) use ($active_player, &$mapping, &$charactersToMove) {
            $charPId = (int) $char['playerId'];
            if ($charPId == (int) $active_player) {
                array_push($charactersToMove, $char['id']);
            } else {
                if (!array_key_exists($charPId, $mapping)) {
                    $mapping[$charPId] = [];
                }
                array_push($mapping[$charPId], $char['id']);
            }
        });

        array_walk($charactersToMove, function ($charId) use ($active_player, &$mapping, &$charactersToMove) {
            $minPlayerId = 0;
            $minCount = 99;
            array_walk($mapping, function ($v, $k) use (&$minCount, &$minPlayerId) {
                if (sizeof($v) < $minCount) {
                    $minPlayerId = $k;
                }
            });
            array_push($mapping[$minPlayerId], $charId);
            $this->character->assignNecromancer($minPlayerId, $charId);
        });
        $this->character->clearCache();

        if ($state['type'] === 'activeplayer') {
            $currentCharId = $this->character->getTurnCharacterId();
            $newPlayerId = array_keys(
                array_filter($mapping, function ($v) use ($currentCharId) {
                    return array_search($currentCharId, $v);
                })
            )[0];

            $this->nextState('changeZombiePlayer');
            $this->gamestate->changeActivePlayer($newPlayerId);
            $this->nextState($stateName);
        } elseif ($state['type'] === 'multipleactiveplayer') {
            $this->gameData->resetMultiActiveCharacter();
        }
        $this->notify('zombieChange', '', [
            'gameData' => $this->getAllDatas(),
        ]);
        $this->completeAction(false);
    }

    // TEST FUNCTIONS START HERE
    public function resetActions()
    {
        $this->character->updateCharacterData($this->character->getSubmittingCharacter()['id'], function (&$data) {
            $data['actions'] = $data['maxActions'];
        });
        $this->completeAction();
    }
    public function noActions()
    {
        $this->character->updateCharacterData($this->character->getSubmittingCharacter()['id'], function (&$data) {
            $data['actions'] = 0;
        });
        $this->completeAction();
    }
    public function spreadDeckhand()
    {
        $this->map->spreadDeckhand();
        $this->completeAction();
    }
    public function setup()
    {
        $this->decks->setup();
    }
    public function resetFatigue()
    {
        $this->character->updateCharacterData($this->character->getSubmittingCharacter()['id'], function (&$data) {
            $data['fatigue'] = 0;
        });
        $this->completeAction();
    }
    public function highFatigue(?string $char = null)
    {
        if (!$char) {
            $char = $this->character->getSubmittingCharacter()['id'];
        }
        $this->character->updateCharacterData($char, function (&$data) {
            $data['fatigue'] = 13;
        });
        $this->completeAction();
    }
    public function checkTreasures()
    {
        $this->gameData->set('treasures', $this->gameData->get('treasures') + 3);
        $this->checkWin();
    }
    public function clearMap()
    {
        $this->map->iterateMap(function (&$tile) {
            $tile['fire'] = 0;
            $tile['deckhand'] = 0;
        });
        $this->map->saveMapChanges();
        $this->completeAction();
    }
    public function dinghy()
    {
        $card = $this->decks->getDeck('tile')->getCardOnTop('deck');

        $query = <<<EOD
UPDATE tile
SET card_location='discard'
WHERE card_location='deck' AND card_id != {$card['id']}
EOD;
        $this::DbQuery($query);
    }
    public function increaseFire(int $roll, string $color)
    {
        $this->map->increaseFire($roll, $color);
        $this->completeAction();
    }
}
