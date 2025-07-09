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
        ]);
        $this->gameData = new DMTNT_GameData($this);
        $this->actions = new DMTNT_Actions($this);
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
                } elseif (array_key_exists('character_id', $args)) {
                    $playerId = (int) $this->character->getCharacterData($args['character_id'])['playerId'];
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
    public function cardDrawEvent($card, $deck, $arg = [])
    {
        $gameData = [];
        $this->getDecks($gameData);
        $result = [
            'card' => $card,
            'deck' => $deck,
            'resolving' => $this->actInterrupt->isStateResolving(),
            'character_name' => $this->getCharacterHTML(),
            'gameData' => $gameData,
            ...$arg,
        ];
        $this->notify('cardDrawn', '', $result);
    }
    public function rollBattleDie(string $characterName): int
    {
        $this->markRandomness();
        $value = rand(1, 6);
        $notificationSent = false;
        $data = [
            'value' => $value,
        ];
        $data['sendNotification'] = function () use ($value, $characterName, &$notificationSent) {
            $this->notify('rollBattleDie', clienttranslate('${character_name} rolled a ${value} ${action_name}'), [
                'value' => $value,
                'character_name' => $this->getCharacterHTML($characterName),
                'characterId' => $characterName,
                'roll' => $value,
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
    public function actPlaceTile(int $x, int $y, int $rotate): void
    {
        $newTile = $this->gameData->get('newTile');
        $newTile['x'] = $x;
        $newTile['y'] = $y;
        $newTile['rotate'] = $rotate;
        $tiles = $this->map->getAdjacentTiles($x, $y);
        if (sizeof($tiles) === 0 || $y < 0) {
            throw new BgaUserException(clienttranslate('Tile can\'t be placed there'));
        }
        $any = false;
        array_walk($tiles, function ($tile) use ($newTile, &$any) {
            $any = $any || $this->map->testTouchPoints($tile, $newTile);
        });
        if (!$any) {
            throw new BgaUserException(clienttranslate('Tile must connect to a door'));
        }
        $newTileData = $this->data->getTile()[$newTile['id']];

        // Get tokens
        $card = $this->decks->pickCardWithoutLookup('bag');
        $trapdoor = false;
        if ($card['type_arg'] === 'trapdoor') {
            $trapdoor = true;
        } else {
            $tokens = [$this->getTokenData($card)];
            if (str_contains($card['type_arg'], 'captain')) {
                $this->decks->discardCards('tile', function ($data, $card) {
                    return str_contains($card['type_arg'], 'captain');
                });
                $card2 = $this->decks->pickCardWithoutLookup('bag');
                $tokens[] = $this->getTokenData($card2);
            }
            $this->gameData->set('tokenPositions', [...$this->gameData->get('tokenPositions'), $this->map->xy($x, $y) => $tokens]);
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

        $this->nextState('finalizeTile');
    }
    public function getTokenData(array $card)
    {
        [$token, $treasure] = explode('_', $card['type_arg']);
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
            'character_name' => $this->getCharacterHTML(),
            'newTile' => $this->gameData->get('newTile'),
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
        return [
            'actions' => [],
            'character_name' => $this->getCharacterHTML(),
        ];
    }

    public function stInitializeTile()
    {
        $card = $this->decks->pickCard('tile');
        $this->gameData->set('newTile', $card);
        $this->gameData->set('newTileCount', $this->gameData->get('newTileCount') + 1);
        $this->nextState('placeTile');
    }

    public function stFinalizeTile()
    {
        if ($this->gameData->get('round') == 1 && $this->gameData->get('newTileCount') < 2) {
            $this->nextState('initializeTile');
        } else {
            $this->gameData->set('newTile', null);
            $this->gameData->set('newTileCount', 0);
            $this->nextState('playerTurn');
        }
    }
    public function getEnemies(): array
    {
        $xy = $this->getCharacterPos($this->character->getTurnCharacterId());
        $xyId = $this->map->xy(...$xy);
        $tokenPositions = $this->gameData->get('tokenPositions');
        if (array_key_exists($xyId, $tokenPositions)) {
            return array_map(
                function ($d) {
                    $data = $this->data->getTreasure()[$d['token']];
                    return [
                        'name' => $d['token'],
                        'enemyName' => $data['name'],
                        'id' => $d['id'],
                        'type' => $data['deckType'],
                        'battle' => $data['battle'],
                    ];
                },
                array_filter($tokenPositions[$xyId], function ($d) {
                    return !$d['treasure'];
                })
            );
        }
        return [];
    }
    public function stPlayerState()
    {
        if (sizeof($this->getEnemies()) > 0) {
            $this->nextState('battleSelection');
        }
    }
    public function actInitSwapItem(): void
    {
        $equippedItems = array_map(
            function ($d) {
                return ['id' => $d['item']['id'], 'characterId' => $d['id'], 'isActive' => $d['isActive']];
            },
            array_filter($this->character->getAllCharacterData(true), function ($d) {
                return $d['item'];
            })
        );
        $items = [...$this->data->getItems()];
        array_walk($equippedItems, function ($d, $k) use (&$items, &$equippedItems) {
            unset($items[$d['id']]);
            if ($d['isActive']) {
                unset($equippedItems[$k]);
            }
        });
        $items = array_map(function ($d) {
            return ['id' => $d];
        }, array_values(toId($items)));

        $this->selectionStates->initiateState(
            'itemSelection',
            [
                'items' => [...$items, ...array_filter($equippedItems)],
                'id' => 'actInitSwapItem',
            ],
            $this->character->getTurnCharacterId(),
            true
        );
    }
    public function actMoveCrew(?int $x, ?int $y): void
    {
        $this->selectionStates->actMoveCrew($x, $y);
    }
    public function actMove(?int $x, ?int $y): void
    {
        if ($x === null || $y === null) {
            throw new BgaUserException(clienttranslate('Select a location'));
        }
        $character = $this->character->getTurnCharacter();
        $moves = $this->map->calculateMoves();
        $fatigue = $moves[$this->map->getTileByXY($x, $y)['id']];
        if ($character['fatigue'] + $fatigue >= $character['maxFatigue']) {
            throw new BgaUserException(clienttranslate('Not enough fatigue'));
        }
        $this->character->adjustActiveFatigue($fatigue);
        $this->actions->spendActionCost('actMove');
        $this->gameData->set('characterPositions', [...$this->gameData->get('characterPositions'), $character['id'] => [$x, $y]]);
        $this->markChanged('player');
        $this->eventLog(clienttranslate('${character_name} moved'), [
            'usedActionId' => 'actMove',
        ]);
        if (sizeof($this->getEnemies()) > 0) {
            $this->nextState('battleSelection');
        }
        $this->completeAction();
    }
    public function actEliminateDeckhand(#[JsonParam] array $data): void
    {
        if (!$data || sizeof($data) == 0) {
            throw new BgaUserException(clienttranslate('Must select a deckhand'));
        }
        foreach ($data as $deckhandTargets) {
            $this->map->decreaseDeckhand($deckhandTargets['x'], $deckhandTargets['y']);
        }
        $this->actions->spendActionCost('actEliminateDeckhand');
        $this->eventLog(clienttranslate('${character_name} increased their strength by 1'), [
            'usedActionId' => 'actEliminateDeckhand',
        ]);
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

    public function actPickupToken(): void
    {
        $this->actions->spendActionCost('actPickupToken');
        // $this->character->updateCharacterData($this->character->getTurnCharacterId(), function (&$data) {
        //     $data['tempStrength']++;
        // });
        $this->eventLog(clienttranslate('${character_name} picked up a ${item}'), [
            'usedActionId' => 'actPickupToken',
        ]);
        $this->completeAction();
    }

    public function actRest(): void
    {
        $this->actions->spendActionCost('actRest');
        $this->character->adjustActiveFatigue(-2);
        $this->completeAction();
    }
    public function actFightFire(int $x, int $y): void
    {
        $tileId = $this->map->getTileByXY($x, $y)['id'];
        $fires = $this->map->calculateFires();
        if (!in_array($tileId, $fires)) {
            throw new BgaUserException(clienttranslate('Invalid Selection'));
        }
        $this->actions->spendActionCost('actFightFire');
        $this->map->decreaseFire($x, $y);
        $this->eventLog(clienttranslate('${character_name} lowered a fire by ${count}'), [
            'usedActionId' => 'actFightFire',
            'count' => 1,
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
    public function actUseItem(string $skillId): void
    {
        if ($this->gamestate->state(true, false, true)['name'] == 'playerTurn') {
        }
        $this->actInterrupt->interruptableFunction(
            __FUNCTION__,
            func_get_args(),
            [$this->hooks, 'onUseSkill'],
            function (Game $_this) use ($skillId) {
                $_this->character->setSubmittingCharacter('actUseItem', $skillId);
                // $this->character->addExtraTime();
                $_this->actions->validateCanRunAction('actUseItem', $skillId);
                $character = $this->character->getSubmittingCharacter();

                $skills = $this->actions->getActiveEquipmentSkills();
                $skill = $skills[$skillId];
                return [
                    'skillId' => $skillId,
                    'skill' => $skill,
                    'character' => $character,
                    'turnCharacter' => $this->character->getTurnCharacter(),
                ];
            },
            function (Game $_this, bool $finalizeInterrupt, $data) {
                $skill = $data['skill'];
                $character = $data['character'];
                $skillId = $data['skillId'];

                $skills = $this->actions->getActiveEquipmentSkills();
                $_this->hooks->reconnectHooks($skill, $skills[$skillId]);
                $_this->character->setSubmittingCharacter('actUseItem', $skillId);
                $notificationSent = false;
                $skill['sendNotification'] = function () use (&$skill, $_this, &$notificationSent) {
                    $_this->notify('notify', clienttranslate('${character_name} used the item\'s skill ${skill_name}'), [
                        'skill_name' => $skill['name'],
                        'usedActionId' => 'actUseSkill',
                        'usedActionName' => $skill['name'],
                    ]);
                    $notificationSent = true;
                };
                if ($_this->gamestate->state(true, false, true)['name'] == 'interrupt') {
                    $_this->actInterrupt->actInterrupt($skillId);
                    $_this->character->setSubmittingCharacter('actUseItem', $skillId);
                    $skill['sendNotification']();
                }
                if (!array_key_exists('interruptState', $skill) || (in_array('interrupt', $skill['state']) && $finalizeInterrupt)) {
                    $result = array_key_exists('onUse', $skill) ? $skill['onUse']($this, $skill, $character) : null;
                    if (!$result || !array_key_exists('spendActionCost', $result) || $result['spendActionCost'] != false) {
                        $_this->actions->spendActionCost('actUseItem', $skillId);
                    }
                    if (!$notificationSent && (!$result || !array_key_exists('notify', $result) || $result['notify'] != false)) {
                        $skill['sendNotification']();
                    }
                }
                $_this->character->setSubmittingCharacter(null);
            }
        );
        $this->completeAction();
    }
    public function actEndTurn(): void
    {
        // TODO: Can't end turn early if sweltering
        // Notify all players about the choice to pass.
        $leftOverActions = $this->character->getTurnCharacter()['actions'];
        if ($leftOverActions > 0) {
            $this->gameData->set('tempActions', $leftOverActions);
            $this->eventLog(clienttranslate('${character_name} ends their turn and passes ${count} actions'), [
                'usedActionId' => 'actEndTurn',
                'count' => $leftOverActions,
            ]);
        } else {
            $this->gameData->set('tempActions', 0);
            $this->eventLog(clienttranslate('${character_name} ends their turn'), [
                'usedActionId' => 'actEndTurn',
            ]);
        }

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

    public function argDrawRevengeCard()
    {
        $result = [
            'resolving' => $this->actInterrupt->isStateResolving(),
            'character_name' => $this->getCharacterHTML(),
            'activeTurnPlayerId' => 0,
        ];
        $this->getDecks($result);
        return $result;
    }

    public function argNextCharacter()
    {
        $result = [
            'resolving' => $this->actInterrupt->isStateResolving(),
            'character_name' => $this->getCharacterHTML(),
            'activeTurnPlayerId' => 0,
        ];
        $this->getAllPlayers($result);
        $this->getTiles($result);
        return $result;
    }
    public function actBattleSelection(int $targetId)
    {
        $enemies = $this->getEnemies();
        $this->gameData->set('battle', [
            'target' => array_values(
                array_filter($enemies, function ($d) use ($targetId) {
                    return $d['id'] === $targetId;
                })
            )[0],
        ]);
        $this->nextState('battle');
    }
    public function argBattleSelection()
    {
        $enemies = $this->getEnemies();
        $targets = array_filter($enemies, function ($d) {
            return $d['type'] !== 'guard';
        });
        if (sizeof($targets) === 0) {
            $targets = array_filter($enemies, function ($d) {
                return $d['type'] === 'guard';
            });
        }
        $result = [
            'resolving' => $this->actInterrupt->isStateResolving(),
            'character_name' => $this->getCharacterHTML(),
            'activeTurnPlayerId' => 0,
            'actions' => array_map(function ($d) {
                return [
                    'action' => 'actFight',
                    'type' => 'action',
                    'targetId' => $d['id'],
                    'targetName' => $d['name'],
                    'targetDie' => $d['battle'],
                ];
            }, array_values($targets)),
        ];

        $this->getAllPlayers($result);
        $this->getTiles($result);
        return $result;
    }
    public function stBattleSelection()
    {
        // $enemies = $this->getEnemies();
        // $firstBattles = array_filter($enemies, function ($d) {
        //     return $d['type'] !== 'guard';
        // });
        // if ($firstBattles) {
        //     if (sizeof($firstBattles) === 1) {
        //         $this->gameData->set('battle', $firstBattles[0]);
        //         $this->nextState('battle');
        //     } else {
        //         // $this->gameData->set('battle', );
        //     }
        // } else {
        //     $guards = array_filter($enemies, function ($d) {
        //         return $d['type'] === 'guard';
        //     });

        //     $this->gameData->set('battle', $guards[0]);
        //     $this->nextState('battle');
        // }
    }
    public function stBattle()
    {
        $this->gameData->set('battle', [
            ...$this->gameData->get('battle'),
            'attack' => $this->rollBattleDie($this->character->getTurnCharacterId()),
        ]);
        // $this->encounter->stBattle();
        // $this->eventLog('${buttons} ${color} ${number}s increase', [
        //     'buttons' => notifyButtons([
        //         ['name' => $this->decks->getDeckName($card['deck']), 'dataId' => $card['id'], 'dataType' => 'revenge'],
        //     ]),
        //     'number' => $card['dice'],
        //     'color' =>
        //         $card['color'] === 'both'
        //             ? clienttranslate('all')
        //             : ($card['color'] === 'red'
        //                 ? clienttranslate('red')
        //                 : clienttranslate('yellow')),
        // ]);
    }
    public function argBattle()
    {
        $result = [
            'resolving' => $this->actInterrupt->isStateResolving(),
            'character_name' => $this->getCharacterHTML(),
            'activeTurnPlayerId' => 0,
        ];
        $this->getAllPlayers($result);
        $this->getTiles($result);
        return $result;
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
    public function crewMove(): void
    {
        $this->map->crewMove();
        $this->completeAction();
    }
    public function stDrawRevengeCard()
    {
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
            function (Game $_this, bool $finalizeInterrupt, $data) {
                $card = $data['card'];
                $_this->hooks->reconnectHooks($card, $_this->decks->getCard($card['id']));
                $nextState = true;
                $this->eventLog('${buttons} ${color} ${number}s increase', [
                    'buttons' => notifyButtons([
                        ['name' => $this->decks->getDeckName($card['deck']), 'dataId' => $card['id'], 'dataType' => 'revenge'],
                    ]),
                    'number' => $card['dice'],
                    'color' =>
                        $card['color'] === 'both'
                            ? clienttranslate('all')
                            : ($card['color'] === 'red'
                                ? clienttranslate('red')
                                : clienttranslate('yellow')),
                ]);
                if (array_key_exists('action', $card)) {
                    if ($card['action'] === 'deckhand-spread') {
                        $this->map->spreadDeckhand();
                        $this->eventLog('The deckhands spread', []);
                    } elseif ($card['action'] === 'deckhand-spawn') {
                        $this->map->increaseDeckhand();
                        $this->eventLog('The deckhands spawn', []);
                    } elseif ($card['action'] === 'crew-move') {
                        $nextState = $this->map->crewMove();
                        $this->eventLog('The crew moves', []);
                    }
                }
                $this->map->increaseFire($card['dice'], $card['color']);

                // if (!$data || !array_key_exists('onUse', $data) || $data['onUse'] != false) {
                //     $result = array_key_exists('onUse', $card) ? $card['onUse']($this, $card) : null;
                // }
                // if (
                //     (!$data || !array_key_exists('notify', $data) || $data['notify'] != false) &&
                //     (!$result || !array_key_exists('notify', $result) || $result['notify'] != false)
                // ) {
                // }
                if ($nextState) {
                    $this->nextState('nextCharacter');
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
        return 1;
    }
    public function endTurn()
    {
        $data = [
            'characterId' => $this->character->getTurnCharacterId(),
        ];
        $this->hooks->onEndTurn($data);
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
        $this->character->activateNextCharacter();
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
        $eloMapping = [5, 10, 15];

        $score = $eloMapping[$this->gameData->get('difficulty')];
        $this->DbQuery("UPDATE player SET player_score={$score} WHERE 1=1");
        $this->nextState('endGame');
    }
    public function lose()
    {
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
        // if ($from_version <= 2506201717) {
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
        $result['characters'] = $this->character->getMarshallCharacters();
        $result['characterPositions'] = array_reduce(
            $result['characters'],
            function ($arr, $char) {
                $arr[$char['pos'][0] . 'x' . $char['pos'][1]][] = ['name' => $char['id'], 'id' => $char['id'], 'type' => 'character'];
                return $arr;
            },
            []
        );
        $result['players'] = $this->getCollectionFromDb('SELECT `player_id` `id`, player_no FROM `player`');
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
        $result['explosions'] = $this->gameData->get('explosions');
        $result['tokenPositions'] = array_map(function ($tokens) {
            return array_map(function ($d) {
                if ($d['isTreasure']) {
                    return ['name' => $d['treasure'], 'id' => $d['id'], 'type' => 'treasure'];
                } else {
                    return ['name' => $d['token'], 'id' => $d['id'], 'type' => 'enemy'];
                }
            }, $tokens);
        }, $this->gameData->get('tokenPositions'));
        $this->getAllPlayers($result);
    }
    public function getItemData(&$result): void
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
        $result['availableItems'] = array_values(toId($items));
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
    public function getDifficulty()
    {
        $difficultyMapping = ['normal', 'challenge', 'hard'];
        return $difficultyMapping[$this->gameData->get('difficulty')];
    }
    private array $changed = ['token' => false, 'player' => false, 'map' => false, 'actions' => false];
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
            $this->incStat(1, 'actions_used', $this->character->getSubmittingCharacter()['playerId']);
        }
        if ($this->changed['token']) {
            $result = [];
            $this->getItemData($result);

            $this->notify('tokenUsed', '', ['gameData' => $result]);
        }
        if ($this->changed['player']) {
            $result = [
                'activeCharacter' => $this->character->getTurnCharacterId(),
                'activePlayer' => $this->character->getTurnCharacterId(),
                'moves' => $this->map->calculateMoves(),
                'fires' => $this->map->calculateFires(),
            ];
            $this->getAllPlayers($result);
            $this->getItemData($result);

            $this->notify('updateCharacterData', '', ['gameData' => $result]);
        }
        if ($this->changed['map']) {
            $result = [
                'activeCharacter' => $this->character->getTurnCharacterId(),
                'activePlayer' => $this->character->getTurnCharacterId(),
                'moves' => $this->map->calculateMoves(),
                'fires' => $this->map->calculateFires(),
            ];
            $this->getAllPlayers($result);
            $this->getTiles($result);

            $this->notify('updateMap', '', ['gameData' => $result]);
        }
        if (in_array($this->gamestate->state(true, false, true)['name'], ['playerTurn'])) {
            $result = [
                'actions' => array_values($this->actions->getValidActions()),
                'availableSkills' => $this->actions->getAvailableSkills(),
                'availableItemSkills' => $this->actions->getAvailableItemSkills(),
            ];
            if ($this->gamestate->state(true, false, true)['name'] == 'playerTurn') {
                $result['canUndo'] = $this->undo->canUndo();
            }
            $this->notify('updateActionButtons', '', ['gameData' => $result]);
        }
    }

    public function getArgsData(): array
    {
        $result = [
            'version' => $this->getVersion(),
            'activeCharacter' => $this->character->getTurnCharacterId(),
            'activeCharacters' => $this->gameData->getAllMultiActiveCharacterIds(),
            'resolving' => $this->actInterrupt->isStateResolving(),
        ];
        if ($this->gamestate->state(true, false, true)['name'] != 'characterSelect') {
            $result = [
                ...$result,
                'character_name' => $this->getCharacterHTML(),
                'actions' => array_values($this->actions->getValidActions()),
                'availableSkills' => $this->actions->getAvailableSkills(),
                // $result['availableItemSkills'] = $this->actions->getAvailableItemSkills();
                'activeTurnPlayerId' => $this->character->getTurnCharacter(true)['player_id'],
                'moves' => $this->map->calculateMoves(),
                'fires' => $this->map->calculateFires(),
                'deckhandTargetCount' => $this->getDeckhandTargetCount(),
            ];
            $this->getAllPlayers($result);
            $this->getTiles($result);
        }
        if ($this->gamestate->state(true, false, true)['name'] == 'playerTurn') {
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
        $this->reattributeColorsBasedOnPreferences($players, $gameinfos['player_colors']);
        $players = $this->loadPlayersBasicInfos();

        $this->gameData->set('expansion', $this->getGameStateValue('expansion'));
        $this->gameData->set('difficulty', $this->getGameStateValue('difficulty'));
        $this->gameData->set('captainFromm', $this->getGameStateValue('captainFromm') == 0);
        $this->gameData->set(
            'characterCount',
            sizeof($players) === 1
                ? $this->getGameStateValue('soloCount')
                : (sizeof($players) === 2
                    ? $this->getGameStateValue('doubleCount')
                    : 1)
        );
        $this->gameData->set('randomSelection', $this->getGameStateValue('random'));
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
    public function setup()
    {
        $this->decks->setup();
    }
    public function resetFatigue()
    {
        $this->character->updateCharacterData($this->character->getSubmittingCharacter()['id'], function (&$data) {
            $data['fatigue'] = $data['maxFatigue'];
        });
        $this->completeAction();
    }
    public function lowFatigue(?string $char = null)
    {
        if (!$char) {
            $char = $this->character->getSubmittingCharacter()['id'];
        }
        $this->character->updateCharacterData($char, function (&$data) {
            $data['fatigue'] = 1;
        });
        $this->completeAction();
    }
    public function fatigue()
    {
        $this->character->adjustActiveFatigue(-10);
        $this->completeAction();
    }
    public function fatigueChar($character)
    {
        $this->character->adjustFatigue($character, -10);
        $this->completeAction();
    }
}
