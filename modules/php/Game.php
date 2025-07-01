<?php
/**
 *------
 * BGA framework: Gregory Isabelli & Emmanuel Colin & BoardGameArena
 * DeadMenTaleNoTales implementation : © Cutch <Your email address here>
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

namespace Bga\Games\DeadMenTaleNoTales;

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
    // public DMTNT_RevengeDeck $decks;
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
        ]);
        $this->gameData = new DMTNT_GameData($this);
        $this->actions = new DMTNT_Actions($this);
        $this->data = new DMTNT_Data($this);
        // $this->decks = new DMTNT_RevengeDeck($this);
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
    public function getCharacterHTML(?string $name = null)
    {
        if ($name) {
            $char = $this->character->getCharacterData($name);
        } else {
            $char = $this->character->getSubmittingCharacter();
            $name = $char['character_name'];
        }
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
    public function rollFireDie(string $actionName, ?string $characterName = null): int
    {
        $this->markRandomness();
        $value = rand(1, 6);
        $notificationSent = false;
        $data = [
            'value' => $value,
        ];
        $data['sendNotification'] = function () use ($value, $characterName, &$notificationSent, $actionName) {
            if ($characterName) {
                $this->notify('rollFireDie', clienttranslate('${character_name} rolled a ${value} ${action_name}'), [
                    'value' => $value,
                    'character_name' => $this->getCharacterHTML($characterName),
                    'characterId' => $characterName,
                    'roll' => $value,
                    'action_name' => '(' . $actionName . ')',
                ]);
            } else {
                $this->notify('rollFireDie', clienttranslate('The fire die rolled a ${value} ${action_name}'), [
                    'value' => $value,
                    'roll' => $value,
                    'action_name' => '(' . $actionName . ')',
                ]);
            }
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

    public function destroyItem(int $itemId): void
    {
        $destroyedEquipment = $this->gameData->get('destroyedEquipment');
        array_push($destroyedEquipment, $itemId);
        $this->gameData->set('destroyedEquipment', $destroyedEquipment);

        $campEquipment = $this->gameData->get('campEquipment');
        if (in_array($itemId, $campEquipment)) {
            $this->gameData->set(
                'campEquipment',
                array_values(
                    array_filter($campEquipment, function ($id) use ($itemId) {
                        return $id != $itemId;
                    })
                )
            );
            $this->markChanged('player');
        } else {
            foreach ($this->character->getAllCharacterData() as $k => $v) {
                $equippedIds = array_map(function ($d) {
                    return $d['itemId'];
                }, $v['equipment']);
                if (in_array($itemId, $equippedIds)) {
                    $this->character->unequipEquipment($v['character_name'], [$itemId]);
                    break;
                }
            }
        }
        $items = $this->gameData->getCreatedItems();

        $this->notify('notify', clienttranslate('${item_name} destroyed'), [
            'item_name' => notifyTextButton([
                'name' => $this->data->getItems()[$items[$itemId]]['name'],
                'dataId' => $items[$itemId],
                'dataType' => 'item',
            ]),
        ]);
    }
    public function actDestroyItem(int $itemId): void
    {
        $this->destroyItem($itemId);
        // $this->completeAction();
    }
    public function actConfirmTradeItem(): void
    {
        $this->itemTrade->actConfirmTradeItem();
        // $this->completeAction();
    }
    public function actCancelTrade(): void
    {
        $this->itemTrade->actCancelTrade();
        // $this->completeAction();
    }
    public function actTradeDone(): void
    {
        $this->itemTrade->actTradeDone();
        // $this->completeAction();
    }
    public function actTradeYield(): void
    {
        $this->itemTrade->actTradeYield();
        // $this->completeAction();
    }
    public function actTradeItem(#[JsonParam] array $data): void
    {
        $this->itemTrade->actTradeItem($data);
        // $this->completeAction();
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
        // Notify all players about the choice to pass.
        $this->eventLog(clienttranslate('${character_name} ends their turn'), [
            'usedActionId' => 'actEndTurn',
        ]);

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
        } elseif ($stateName == 'tradePhase') {
            $privateState = $this->gamestate->getPrivateState($this->getCurrentPlayer());
            if ($privateState && $privateState['name'] == 'waitTradePhase') {
                $this->itemTrade->actCancelTrade();
            } else {
                $this->itemTrade->actForceSkip();
            }
        }
    }
    #[CheckAction(false)]
    public function actUnBack(): void
    {
        $this->gamestate->checkPossibleAction('actUnBack');
        $stateName = $this->gamestate->state(true, false, true)['name'];
        if ($stateName == 'characterSelect') {
            $this->characterSelection->actUnBack();
        } elseif ($stateName == 'tradePhase') {
            $this->itemTrade->actUnBack();
        }
    }
    public function actDone(): void
    {
        // $this->character->addExtraTime();
        $saveState = true;
        $stateName = $this->gamestate->state(true, false, true)['name'];
        if ($stateName == 'postEncounter') {
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

    public function argDrawCard()
    {
        $result = [
            ...$this->gameData->get('state'),
            'resolving' => $this->actInterrupt->isStateResolving(),
            'character_name' => $this->getCharacterHTML(),
        ];
        $this->getDecks($result);
        return $result;
    }
    public function argNightDrawCard()
    {
        $result = [
            ...$this->gameData->get('drawNightState') ?? $this->gameData->get('state'),
            'resolving' => $this->actInterrupt->isStateResolving(),
            'character_name' => $this->getCharacterHTML(),
            'activeTurnPlayerId' => 0,
        ];
        $this->getDecks($result);
        return $result;
    }
    public function argPostEncounter()
    {
        return $this->encounter->argPostEncounter();
    }
    public function stPostEncounter()
    {
        $this->encounter->stPostEncounter();
    }
    public function stResolveEncounter()
    {
        $this->encounter->stResolveEncounter();
    }
    public function argResolveEncounter()
    {
        return $this->encounter->argResolveEncounter();
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
    public function stPlayerTurn()
    {
        $char = $this->character->getTurnCharacter();
        if ($char['isActive'] && $char['incapacitated']) {
            $this->eventLog(clienttranslate('${character_name} is incapacitated'));
            $this->endTurn();
        }
        // }
    }
    public function stDrawCard()
    {
        $this->actInterrupt->interruptableFunction(
            __FUNCTION__,
            func_get_args(),
            [$this->hooks, 'onResolveDraw'],
            function (Game $_this) {
                // $character = $this->character->getSubmittingCharacter();
                // deck,card
                $state = $this->gameData->get('state');
                $deck = $state['deck'];
                $card = $state['card'];
                $this->cardDrawEvent($card, $deck);
                if ($card['deckType'] == 'resource') {
                    $this->adjustResource($card['resourceType'], $card['count']);

                    $this->eventLog(clienttranslate('${character_name} found ${count} ${name}(s) ${buttons}'), [
                        ...$card,
                        'buttons' => notifyButtons([
                            ['name' => $this->decks->getDeckName($card['deck']), 'dataId' => $card['id'], 'dataType' => 'card'],
                        ]),
                    ]);
                } elseif ($card['deckType'] == 'encounter') {
                    // Change state and check for fatigue/damage modifications
                    $this->eventLog(clienttranslate('${character_name} encountered a ${name} (${fatigue} fatigue, ${damage} damage)'), [
                        ...$card,
                        'name' => notifyTextButton([
                            'name' => $card['name'],
                            'dataId' => $card['id'],
                            'dataType' => 'card',
                        ]),
                    ]);
                } elseif ($card['deckType'] == 'nothing') {
                    if (!$this->isValidExpansion('mini-expansion')) {
                        $this->eventLog(clienttranslate('${character_name} did nothing ${buttons}'), [
                            'buttons' => notifyButtons([
                                ['name' => $this->decks->getDeckName($card['deck']), 'dataId' => $card['id'], 'dataType' => 'card'],
                            ]),
                        ]);
                    }
                } elseif ($card['deckType'] == 'physical-hindrance') {
                    $this->eventLog(clienttranslate('${character_name} must draw a ${deck} ${buttons}'), [
                        'deck' => clienttranslate('Physical Hindrance'),
                        'buttons' => notifyButtons([
                            ['name' => $this->decks->getDeckName($card['deck']), 'dataId' => $card['id'], 'dataType' => 'card'],
                        ]),
                    ]);
                } elseif ($card['deckType'] == 'mental-hindrance') {
                    $this->eventLog(clienttranslate('${character_name} must draw a ${deck} ${buttons}'), [
                        'deck' => clienttranslate('Mental Hindrance'),
                        'buttons' => notifyButtons([
                            ['name' => $this->decks->getDeckName($card['deck']), 'dataId' => $card['id'], 'dataType' => 'card'],
                        ]),
                    ]);
                } else {
                }
                return [...$state, 'discard' => false];
            },
            function (Game $_this, bool $finalizeInterrupt, $data) use (&$moveToDrawCardState) {
                $deck = $data['deck'];
                $card = $data['card'];
                if ($data['discard']) {
                    $this->nextState('playerTurn');
                } elseif ($card['deckType'] == 'resource') {
                    $this->nextState('playerTurn');
                } elseif ($card['deckType'] == 'encounter') {
                    $this->nextState('resolveEncounter');
                } elseif ($card['deckType'] == 'nothing') {
                    if ($this->isValidExpansion('mini-expansion')) {
                        $card = $this->decks->pickCard('day-event');
                        $this->gameData->set('state', ['card' => $card, 'deck' => 'day-event']);
                        $this->actions->addDayEvent($card['id']);
                        $moveToDrawCardState = true;
                    } else {
                        $this->nextState('playerTurn');
                    }
                } elseif (
                    $card['deck'] != $card['deckType'] &&
                    ($card['deckType'] == 'physical-hindrance' || $card['deckType'] == 'mental-hindrance')
                ) {
                    $this->checkHindrance(true, $this->character->getSubmittingCharacterId());
                    $this->nextState('playerTurn');
                } elseif ($card['deckType'] == 'day-event') {
                    $this->nextState('dayEvent');
                } else {
                    $this->nextState('playerTurn');
                }
            }
        );
        if ($moveToDrawCardState) {
            $this->nextState('drawCard');
        }
    }
    public function stNightPhase()
    {
        $this->actInterrupt->interruptableFunction(
            __FUNCTION__,
            func_get_args(),
            [$this->hooks, 'onNight'],
            function (Game $_this) {
                $card = $this->decks->pickCard('night-event');
                $this->gameData->set('drawNightState', ['card' => $card, 'deck' => 'night-event']);
                return ['card' => $card, 'deck' => 'night-event'];
            },
            function (Game $_this, bool $finalizeInterrupt, $data) {
                $deck = $data['deck'];
                $this->eventLog(clienttranslate('It\'s night, drawing from the night deck'));
                $this->nextState('nightDrawCard');
            }
        );
    }
    public function stNightDrawCard()
    {
        $this->actInterrupt->interruptableFunction(
            __FUNCTION__,
            func_get_args(),
            [$this->hooks, 'onNightDrawCard'],
            function (Game $_this) {
                // deck,card
                $state = $this->gameData->get('drawNightState') ?? $this->gameData->get('state');
                $deck = $state['deck'];
                $card = $state['card'];
                $this->cardDrawEvent($card, $deck);
                return ['state' => $state];
            },
            function (Game $_this, bool $finalizeInterrupt, $data) {
                $card = $data['state']['card'];
                $_this->hooks->reconnectHooks($card, $_this->decks->getCard($card['id']));

                if (!$data || !array_key_exists('onUse', $data) || $data['onUse'] != false) {
                    $result = array_key_exists('onUse', $card) ? $card['onUse']($this, $card) : null;
                }
                if (
                    (!$data || !array_key_exists('notify', $data) || $data['notify'] != false) &&
                    (!$result || !array_key_exists('notify', $result) || $result['notify'] != false)
                ) {
                    $this->eventLog('${buttons}', [
                        'buttons' => notifyButtons([
                            ['name' => $this->decks->getDeckName($card['deck']), 'dataId' => $card['id'], 'dataType' => 'night-event'],
                        ]),
                    ]);
                }
                if (
                    (!$data || !array_key_exists('nextState', $data) || $data['nextState'] != false) &&
                    (!$result || !array_key_exists('nextState', $result) || $result['nextState'] != false)
                ) {
                    $this->nextState('morningPhase');
                }
            }
        );
    }

    public function argSelectionCount(): array
    {
        $result = ['actions' => []];
        $this->getAllPlayers($result);
        return $result;
    }
    public function argStartHindrance(): array
    {
        $selectableUpgrades = array_keys(
            array_filter($this->data->getBoards()['knowledge-tree-' . $this->getDifficulty()]['track'], function ($v) {
                return !array_key_exists('upgradeType', $v);
            })
        );
        $result = [...$this->getArgsData(), 'selectableUpgrades' => $selectableUpgrades];
        return $result;
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
        extract($this->gameData->getAll('day', 'turnNo'));
        return ((($day - 1) * 4 + ($turnNo ?? 0)) / (12 * 4)) * 100;
    }
    public function endTurn()
    {
        $data = [
            'characterId' => $this->character->getTurnCharacterId(),
        ];
        $this->hooks->onEndTurn($data);
        $this->gameData->set('lastAction', null);
        $this->nextState('endTurn');
        $this->undo->clearUndoHistory();
    }
    /**
     * The action method of state `nextCharacter` is called every time the current game state is set to `nextCharacter`.
     */
    public function stNextCharacter(): void
    {
        // Retrieve the active player ID.
        if (
            sizeof(
                array_filter($this->character->getAllCharacterData(true), function ($d) {
                    return $d['incapacitated'] && !$d['recovering'];
                })
            ) == 4
        ) {
            $this->lose();
        } else {
            while (true) {
                if ($this->character->isLastCharacter()) {
                    $this->nextState('dinnerPhase');
                    $this->actions->clearDayEvent();
                    break;
                } else {
                    $this->character->activateNextCharacter();
                    $this->actions->clearDayEvent();
                    if ($this->character->getActiveFatigue() == 0) {
                        $this->notify('playerTurn', clienttranslate('${character_name} is incapacitated'), []);
                    } else {
                        $this->nextState('playerTurn');
                        $this->notify('playerTurn', clienttranslate('${character_name} begins their turn'), []);
                        break;
                    }
                }
            }
        }
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
    public function stMorningPhase()
    {
        $this->actInterrupt->interruptableFunction(
            __FUNCTION__,
            func_get_args(),
            [$this->hooks, 'onMorning'],
            function (Game $_this) {
                $woodNeeded = $this->getFirewoodCost();
                $day = $this->gameData->get('day');
                $day += 1;
                $this->gameData->set('day', $day);
                $fireWood = $this->gameData->getResource('fireWood');
                if (array_key_exists('allowFireWoodAddition', $this->gameData->get('morningState') ?? [])) {
                    $this->gameData->set('morningState', [...$this->gameData->get('morningState') ?? [], 'allowFireWoodAddition' => false]);
                    if ($fireWood < $woodNeeded + 1) {
                        $missingWood = $woodNeeded + 1 - $fireWood;
                        $wood = $this->gameData->getResource('wood');
                        if ($wood >= $missingWood) {
                            $this->gameData->setResource(
                                'fireWood',
                                min($fireWood + $missingWood, $this->gameData->getResourceMax('wood'))
                            );
                            $this->gameData->setResource('wood', max($wood - $missingWood, 0));
                            $this->notify(
                                'notify',
                                clienttranslate('During the night the tribe quickly added ${woodNeeded} ${token_name} to the fire'),
                                [
                                    'woodNeeded' => $woodNeeded,
                                    'token_name' => 'wood',
                                ]
                            );
                        }
                    }
                }

                $this->setStat($day, 'day_number');
                resetPerDay($this);
                if ($day == 14) {
                    $this->lose();
                }
                $difficulty = $this->getTrackDifficulty();
                $fatigue = -1;
                if ($difficulty == 'hard') {
                    $fatigue = -2;
                }
                return [
                    'difficulty' => $difficulty,
                    'fatigue' => $fatigue,
                    'actions' => 0,
                    'skipMorningDamage' => [],
                    'woodNeeded' => $woodNeeded,
                    'changeOrder' => true,
                    'nextState' => 'tradePhase',
                    'day' => $day,
                ];
            },
            function (Game $_this, bool $finalizeInterrupt, $data) {
                // extract($data);
                $fatigue = $data['fatigue'];
                $actions = $data['actions'];
                $skipMorningDamage = $data['skipMorningDamage'];
                $woodNeeded = $data['woodNeeded'];
                $this->character->updateAllCharacterData(function (&$data) use ($fatigue, $actions, $skipMorningDamage) {
                    if (!in_array($data['id'], $skipMorningDamage)) {
                        $prev = 0;
                        $this->character->_adjustFatigue($data, $fatigue, $prev, $data['id']);
                    }
                    if ($data['incapacitated'] && $data['recovering']) {
                        $data['incapacitated'] = false;
                    }

                    if (!$data['incapacitated']) {
                        $data['actions'] = $data['maxActions'];
                        $data['actions'] = clamp($data['actions'] + $actions, 0, $data['maxActions']);
                    }
                });
                if ($fatigue != 0) {
                    $this->notify('morningPhase', clienttranslate('Everyone lost ${amount} ${character_resource}'), [
                        'amount' => -$fatigue,
                        'character_resource' => clienttranslate('fatigue'),
                    ]);
                }

                $this->notify('morningPhase', clienttranslate('The fire pit used ${amount} wood'), [
                    'amount' => $woodNeeded,
                ]);
                $this->adjustResource('fireWood', -$woodNeeded);
                if ($this->gameData->getResource('fireWood') <= 0) {
                    $this->lose();
                }
                $this->notify('morningPhase', clienttranslate('Morning has arrived (Day ${day})'), [
                    'day' => $data['day'],
                ]);
                $this->hooks->onMorningAfter($data);
                if ($data['changeOrder']) {
                    $this->character->rotateTurnOrder();
                }
                if ($data['nextState'] != false) {
                    $this->nextState('tradePhase');
                }
            }
        );
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
        $result['players'] = $this->getCollectionFromDb('SELECT `player_id` `id`, player_no FROM `player`');
    }
    public function getDecks(&$result): void
    {
        $data = $this->decks->getDecksData();
        $result['decks'] = $data['decks'];
        $result['decksDiscards'] = $data['decksDiscards'];
    }
    public function getItemData(&$result): void
    {
        $result['builtEquipment'] = $this->getCraftedItems();
        $result['buildings'] = $this->gameData->get('buildings');
        $items = $this->gameData->getCreatedItems();
        $result['campEquipmentCounts'] = array_count_values(
            array_map(function ($d) use ($items) {
                return $items[$d];
            }, $this->gameData->get('campEquipment'))
        );
        $result['campEquipment'] = array_values(
            array_map(function ($d) use ($items) {
                return ['name' => $items[$d], 'itemId' => $d];
            }, $this->gameData->get('campEquipment'))
        );

        $result['cookableFoods'] = $this->actions->getActionSelectable('actCook');

        $result['eatableFoods'] = array_map(function ($eatable) {
            $data = [...$eatable['actEat'], 'id' => $eatable['id'], 'characterId' => $this->character->getTurnCharacterId()];
            $this->hooks->onGetEatData($data);
            return $data;
        }, $this->actions->getActionSelectable('actEat'));

        $result['revivableFoods'] = array_map(function ($eatable) {
            $data = [...$eatable['actRevive'], 'id' => $eatable['id']];
            return $data;
        }, $this->actions->getActionSelectable('actRevive'));
        $selectable = $this->actions->getActionSelectable('actCraft');

        $result['availableEquipment'] = array_combine(
            toId($selectable),
            array_map(function ($d) use ($result) {
                return $d['count'] - (array_key_exists($d['id'], $result['builtEquipment']) ? $result['builtEquipment'][$d['id']] : 0);
            }, $selectable)
        );
        $availableEquipment = array_keys($result['availableEquipment']);

        $result['availableEquipmentWithCost'] = array_values(
            array_filter($availableEquipment, function ($itemName) {
                $item = $this->data->getItems()[$itemName];
                return $this->hasResourceCost($item);
            })
        );

        $craftingLevel = $this->gameData->get('craftingLevel');
        $buildings = $this->gameData->get('buildings');
        $allBuildableEquipment = array_values(
            array_filter(
                $this->data->getItems(),
                function ($v, $k) use ($craftingLevel) {
                    return $v['type'] == 'item' && in_array($v['craftingLevel'], $craftingLevel);
                },
                ARRAY_FILTER_USE_BOTH
            )
        );
    }
    public function getValidTokens(): array
    {
        return array_filter($this->data->getTokens(), function ($v) {
            return $v['type'] == 'resource' &&
                (!array_key_exists('requires', $v) || $v['requires']($this, $v)) &&
                (!array_key_exists('expansion', $v) || $this->isValidExpansion($v['expansion']));
        });
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
        $difficultyMapping = ['easy', 'normal', 'normal+', 'hard'];
        return $difficultyMapping[$this->gameData->get('difficulty')];
    }
    private array $changed = ['token' => false, 'player' => false, 'knowledge' => false, 'actions' => false];
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
        if ($this->changed['player'] || $this->changed['knowledge']) {
            $result = [
                'activeCharacter' => $this->character->getTurnCharacterId(),
                'activePlayer' => $this->character->getTurnCharacterId(),
            ];
            $this->getAllPlayers($result);
            $this->getItemData($result);

            $this->notify('updateCharacterData', '', ['gameData' => $result]);
        }
        if ($this->changed['knowledge']) {
            $selectableUpgrades = array_keys(
                array_filter($this->data->getBoards()['knowledge-tree-' . $this->getDifficulty()]['track'], function ($v) {
                    return !array_key_exists('upgradeType', $v);
                })
            );
            $availableUnlocks = $this->data->getValidKnowledgeTree();
            $result = [
                'upgrades' => $this->gameData->get('upgrades'),
                'unlocks' => $this->gameData->get('unlocks'),
                'availableUnlocks' => array_map(function ($id) use ($availableUnlocks) {
                    $knowledgeObj = $this->data->getKnowledgeTree()[$id];
                    return [
                        'id' => $id,
                        'name' => $knowledgeObj['name'],
                        'name_suffix' => array_key_exists('name_suffix', $knowledgeObj) ? $knowledgeObj['name_suffix'] : '',
                        'unlockCost' => $availableUnlocks[$id]['unlockCost'],
                    ];
                }, array_keys($availableUnlocks)),
                'selectableUpgrades' => $selectableUpgrades,
            ];
            $this->getItemData($result);

            $result = [...$this->getArgsData(), 'selectableUpgrades' => $selectableUpgrades];

            $this->notify('updateKnowledgeTree', '', ['gameData' => $result]);
        }
        if (
            !in_array($this->gamestate->state(true, false, true)['name'], [
                'characterSelect',
                'interrupt',
                'dinnerPhasePrivate',
                'dinnerPhase',
            ])
        ) {
            $availableUnlocks = $this->data->getValidKnowledgeTree();
            $result = [
                'tradeRatio' => $this->getTradeRatio(),
                'actions' => array_values($this->actions->getValidActions()),
                'availableSkills' => $this->actions->getAvailableSkills(),
                'availableItemSkills' => $this->actions->getAvailableItemSkills(),
            ];
            if ($this->gamestate->state(true, false, true)['name'] == 'playerTurn') {
                $result['canUndo'] = $this->undo->canUndo();
            }
            $this->notify('updateActionButtons', '', ['gameData' => $result]);
        }
        if (in_array($this->gamestate->state(true, false, true)['name'], ['dinnerPhasePrivate', 'dinnerPhase'])) {
            foreach ($this->gamestate->getActivePlayerList() as $playerId) {
                $result = $this->argDinnerPhase($playerId);
                $this->notify_player((int) $playerId, 'updateActionButtons', '', ['gameData' => $result]);
            }
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
            $result['character_name'] = $this->getCharacterHTML();
            $result['actions'] = array_values($this->actions->getValidActions());
            $result['availableSkills'] = $this->actions->getAvailableSkills();
            $result['availableItemSkills'] = $this->actions->getAvailableItemSkills();
            $result['activeTurnPlayerId'] = $this->character->getTurnCharacter(true)['player_id'];
            $this->getAllPlayers($result);
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
        $stateName = $this->gamestate->state(true, false, true)['name'];
        // TODO remove this check after initial games are no longer in progress
        $turnOrder = $this->gameData->get('turnOrder');
        if (sizeof(array_filter($turnOrder)) != 4) {
            $players = $this->loadPlayersBasicInfos();

            $characters = array_values(
                $this->getCollectionFromDb('SELECT character_name, player_id FROM `character` order by character_name')
            );
            if (sizeof($characters) == 4) {
                $players = array_orderby($players, 'player_no', SORT_ASC);
                $turnOrder = [];
                array_walk($players, function ($player) use (&$turnOrder, $characters) {
                    $turnOrder = [
                        ...$turnOrder,
                        ...array_map(
                            function ($d) {
                                return $d['character_name'];
                            },
                            array_filter($characters, function ($char) use ($player) {
                                return $char['player_id'] == $player['player_id'];
                            })
                        ),
                    ];
                });
                $this->gameData->set('turnOrder', $turnOrder);
                if ($stateName !== 'characterSelect') {
                    $this->gameData->set('turnOrderStart', $this->gameData->get('turnOrder'));
                }
            }
        }
        if (
            (!$this->gameData->get('turnOrderStart') || sizeof($this->gameData->get('turnOrderStart')) < 4) &&
            $stateName !== 'characterSelect'
        ) {
            $players = $this->loadPlayersBasicInfos();

            $characters = array_values(
                $this->getCollectionFromDb('SELECT character_name, player_id FROM `character` order by character_name')
            );
            $players = array_orderby($players, 'player_no', SORT_ASC);
            $turnOrder = [];
            array_walk($players, function ($player) use (&$turnOrder, $characters) {
                $turnOrder = [
                    ...$turnOrder,
                    ...array_map(
                        function ($d) {
                            return $d['character_name'];
                        },
                        array_filter($characters, function ($char) use ($player) {
                            return $char['player_id'] == $player['player_id'];
                        })
                    ),
                ];
            });
            $this->gameData->set('turnOrderStart', $turnOrder);
        }
        if ($stateName == 'characterSelect' && $this->gameData->get('turnOrderStart')) {
            $this->gameData->set('turnOrderStart', null);
        }
        $equippedEquipment = array_merge(
            [],
            ...array_map(function ($data) {
                return toId($data['equipment']);
            }, $this->character->getAllCharacterData(false))
        );
        if (sizeof($this->gameData->get('lastItemOwners')) == 0 && sizeof($equippedEquipment) > 0) {
            foreach ($this->character->getAllCharacterData(false) as $char) {
                $ids = array_map(function ($d) {
                    return $d['itemId'];
                }, $char['equipment']);
                $this->character->updateItemLastOwner($char['id'], $ids);
            }
        }

        $result = [
            'version' => $this->getVersion(),
            'expansionList' => self::$expansionList,
            'expansion' => $this->getExpansion(),
            'difficulty' => $this->getDifficulty(),
            // 'isRealTime' => $this->isRealTime() || !$this->getIsTrusting(),
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
        $this->reloadPlayersBasicInfos();

        $this->gameData->set('expansion', $this->getGameStateValue('expansion'));
        $this->gameData->set('difficulty', $this->getGameStateValue('difficulty'));
        $this->decks = new DMTNT_RevengeDeck($this);
        $this->decks->setup();

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
    public function setNightCard()
    {
        $cards = array_values($this->decks->getDeck('night-event')->getCardsInLocation('deck'));
        $firstCard = null;
        $max = 0;
        foreach ($cards as $k => $v) {
            if ($max < $v['location_arg']) {
                $max = max($max, $v['location_arg']);
                $firstCard = $v;
            }
        }
        foreach ($cards as $k => $v) {
            if ($v['type_arg'] == 'night-event-7_15' && $firstCard['type_arg'] != 'night-event-7_15') {
                $this->decks->getDeck('night-event')->moveCard($firstCard['id'], 'deck', $v['location_arg']);
                $this->decks->getDeck('night-event')->moveCard($v['id'], 'deck', $max);
            }
        }
        $this->gameData->setResources(['fireWood' => 1, 'wood' => 1]);
        $this->completeAction();
    }
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
    public function shuffle()
    {
        $this->decks->shuffleInDiscard('gather', true);
        $this->completeAction();
    }
}
