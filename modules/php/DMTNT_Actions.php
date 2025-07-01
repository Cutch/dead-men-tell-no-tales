<?php
declare(strict_types=1);

namespace Bga\Games\DeadMenTaleNoTales;

use BgaUserException;

class DMTNT_Actions
{
    private $actions;
    private Game $game;
    public function __construct(Game $game)
    {
        $this->actions = addId([
            'actMove' => [
                'state' => ['playerTurn'],
                'actions' => 1,
                'type' => 'action',
                'requires' => function (Game $game, $action) {
                    return true;
                },
            ],
            'actRun' => [
                'state' => ['playerTurn'],
                'actions' => 1,
                'fatigue' => 2,
                'type' => 'action',
                'requires' => function (Game $game, $action) {
                    return true;
                },
            ],
            'actFightFire' => [
                'state' => ['playerTurn'],
                'actions' => 1,
                'type' => 'action',
                'requires' => function (Game $game, $action) {
                    return true;
                },
            ],
            'actEliminateDeckhand' => [
                'state' => ['playerTurn'],
                'actions' => 1,
                'type' => 'action',
                'requires' => function (Game $game, $action) {
                    return true;
                },
            ],
            'actPickupToken' => [
                'state' => ['playerTurn'],
                'actions' => 1,
                'type' => 'action',
                'requires' => function (Game $game, $action) {
                    return true;
                },
            ],
            'actRest' => [
                'state' => ['playerTurn'],
                'actions' => 1,
                'type' => 'action',
                'requires' => function (Game $game, $action) {
                    return true;
                },
            ],
            'actIncreaseBattleStrength' => [
                'state' => ['playerTurn'],
                'actions' => 1,
                'type' => 'action',
                'requires' => function (Game $game, $action) {
                    return true;
                },
            ],
            'actSwapItem' => [
                'state' => ['playerTurn'],
                'actions' => 1,
                'type' => 'action',
            ],
        ]);
        $this->game = $game;
    }
    private function expansionFilter(array $data)
    {
        if (!array_key_exists('expansion', $data)) {
            return true;
        }
        return $this->game->isValidExpansion($data['expansion']);
    }
    public function getActions()
    {
        return array_filter($this->actions, [$this, 'expansionFilter']);
    }
    public function getSkills(): array
    {
        $characters = $this->game->character->getAllCharacterData();
        $characterSkills = array_merge(
            ...array_map(function ($c) {
                if (array_key_exists('skills', $c)) {
                    return $c['skills'];
                }
                return [];
            }, $characters)
        );
        $this->game->hooks->onGetCharacterSkills($characterSkills);
        return [
            ...$characterSkills,
            ...array_merge(
                ...array_values(
                    array_map(function ($c) {
                        if (array_key_exists('skills', $c)) {
                            return $c['skills'];
                        }
                        return [];
                    }, $this->actions)
                )
            ),
        ];
    }
    public function getActiveEquipmentSkills()
    {
        $character = $this->game->character->getSubmittingCharacter();
        $skills = array_merge(
            ...array_map(function ($item) {
                if (!array_key_exists('skills', $item)) {
                    return [];
                }
                return $item['skills'];
            }, $character['equipment'])
        );
        return $skills;
    }
    public function getAction(string $actionId, ?string $subActionId = null): array
    {
        if ($actionId == 'actUseSkill') {
            $skills = $this->getSkills();
            if (isset($skills[$subActionId])) {
                return $skills[$subActionId];
            }
            return [];
        } elseif ($actionId == 'actUseItem') {
            $skills = $this->getActiveEquipmentSkills();
            if (isset($skills[$subActionId])) {
                return $skills[$subActionId];
            }
            return [];
        } else {
            $actionObj = $this->getActions()[$actionId];
            $actionObj['action'] = $actionId;
            return $actionObj;
        }
    }
    public function getAvailableSkills(): array
    {
        $skills = $this->getSkills();
        $skills = array_values(
            array_filter($skills, function ($skill) {
                $character = $this->game->character->getCharacterData(
                    array_key_exists('characterId', $skill) && !array_key_exists('global', $skill)
                        ? $skill['characterId']
                        : $this->game->character->getTurnCharacterId()
                );
                $actions = $character['actions'];
                $fatigue = $character['fatigue'];

                $this->skillActionCost('actUseSkill', null, $skill);
                return $this->game->hooks->onCheckSkillRequirements($skill) &&
                    $this->checkRequirements($skill, $character) &&
                    (!array_key_exists('actions', $skill) || $actions >= $skill['actions']) &&
                    (!array_key_exists('fatigue', $skill) || $fatigue >= $skill['fatigue']);
            })
        );
        return $skills;
    }

    public function getAvailableItemSkills(): array
    {
        $character = $this->game->character->getSubmittingCharacter();
        $skills = $this->getActiveEquipmentSkills();
        return array_values(
            array_filter($skills, function ($skill) use ($character) {
                $actions = $character['actions'];
                $fatigue = $character['fatigue'];
                $this->skillActionCost('actUseItem', null, $skill);
                return $this->checkRequirements($skill, $character) &&
                    (!array_key_exists('actions', $skill) || $actions >= $skill['actions']) &&
                    (!array_key_exists('fatigue', $skill) || $fatigue >= $skill['fatigue']);
            })
        );
    }
    public function getActionSelectable(string $actionId, ?string $subActionId = null, ?string $characterId = null)
    {
        $data = [
            'action' => $actionId,
            'selectable' => $this->getAction($actionId, $subActionId)['selectable']($this->game),
            'characterId' => $characterId ?? $this->game->character->getSubmittingCharacterId(),
        ];
        return $this->game->hooks->onGetActionSelectable($data)['selectable'];
    }
    /**
     * Get character actions cost
     * @return array
     * @see ./states.inc.php
     */
    public function getActionCost(string $action, ?string $subAction = null, ?string $characterId = null): array
    {
        $actionObj = $this->getAction($action, $subAction);
        $this->skillActionCost($action, $subAction, $actionObj, $characterId);
        return $actionObj;
    }
    private function skillActionCost(string $action, ?string $subAction = null, array &$skill, ?string $characterId = null)
    {
        $actionCost = [
            'action' => $action,
            'subAction' => $subAction ?? (array_key_exists('id', $skill) ? $skill['id'] : null),
            'actions' => array_key_exists('actions', $skill) ? $skill['actions'] : null,
            'fatigue' => array_key_exists('fatigue', $skill) ? $skill['fatigue'] : null,
            'perDay' => array_key_exists('perDay', $skill) ? $skill['perDay'] : null,
            'perForever' => array_key_exists('perForever', $skill) ? $skill['perForever'] : null,
            'name' => array_key_exists('name', $skill) ? $skill['name'] : null,
            'random' => array_key_exists('random', $skill) ? $skill['random'] : null,
            'characterId' => $characterId ?? $this->game->character->getSubmittingCharacterId(),
        ];
        $this->game->hooks->onGetActionCost($actionCost);

        $skill['action'] = $actionCost['action'];
        if (array_key_exists('actions', $actionCost)) {
            $skill['actions'] = $actionCost['actions'];
        }
        if (array_key_exists('fatigue', $actionCost)) {
            $skill['fatigue'] = $actionCost['fatigue'];
        }
        if (array_key_exists('perDay', $actionCost)) {
            $skill['perDay'] = $actionCost['perDay'];
        }
        if (array_key_exists('perForever', $actionCost)) {
            $skill['perForever'] = $actionCost['perForever'];
        }
        if (array_key_exists('random', $actionCost)) {
            $skill['random'] = $actionCost['random'];
        }
        if (array_key_exists('name', $actionCost)) {
            $skill['name'] = $actionCost['name'];
        }
    }
    public function wrapSkills(array $skills, string $action): array
    {
        return array_values(
            array_map(function ($skill) use ($action) {
                $this->skillActionCost($action, null, $skill);
                return $skill;
            }, $skills)
        );
    }
    public function checkRequirements(array $actionObj, ...$args): bool
    {
        return (!array_key_exists('getState', $actionObj) ||
            in_array($this->game->gamestate->state(true, false, true)['name'], $actionObj['getState']())) &&
            (!array_key_exists('state', $actionObj) ||
                in_array($this->game->gamestate->state(true, false, true)['name'], $actionObj['state'])) &&
            // (!array_key_exists('interruptState', $actionObj)
            //  ||
            //     ($this->game->actInterrupt->getLatestInterruptState() &&
            //         in_array(
            //             $this->game->actInterrupt->getLatestInterruptState()['data']['currentState'],
            //             $actionObj['interruptState']
            //         ))) &&
            (!array_key_exists('interruptState', $actionObj) ||
                ($this->game->actInterrupt->getLatestInterruptState() &&
                    in_array($actionObj['id'], toId($this->game->actInterrupt->getLatestInterruptState()['data']['skills'])))) &&
            (!array_key_exists('requires', $actionObj) || $actionObj['requires']($this->game, $actionObj, ...$args));
    }
    public function spendActionCost(string $action, ?string $subAction = null, ?string $characterId = null)
    {
        $cost = $this->getActionCost($action, $subAction, $characterId);
        $this->spendCost($cost);
    }
    public function spendCost(array $cost)
    {
        $this->game->hooks->onSpendActionCost($cost);
        if (array_key_exists('fatigue', $cost)) {
            $this->game->character->adjustActiveFatigue(-$cost['fatigue']);
        }
        if (array_key_exists('actions', $cost)) {
            $this->game->character->adjustActiveActions(-$cost['actions']);

            if ($cost['actions'] > 0) {
                $this->game->incStat($cost['actions'], 'actions_used', $this->game->character->getSubmittingCharacter()['playerId']);
            }
        }
    }
    public function validateSelectable(
        string $type,
        callable $selector,
        string $actionId,
        ?string $subActionId = null,
        ?string $characterId = null
    ) {
        $selections = $this->getActionSelectable($actionId, $subActionId, $characterId);
        $selections = array_map($selector, $selections);
        if (!in_array($type, $selections)) {
            throw new BgaUserException(clienttranslate('The selection is invalid'));
        }
    }
    public function validateCanRunAction(string $action, ?string $subAction = null, ...$args)
    {
        $character = $this->game->character->getSubmittingCharacter();

        $cost = $this->getActionCost($action, $subAction);
        $this->game->hooks->onSpendActionCost($cost);
        $actions = $character['actions'];
        // $fatigue = $character['fatigue'];
        if (array_key_exists('actions', $cost) && $actions < $cost['actions']) {
            throw new BgaUserException(clienttranslate('Not enough actions'));
        }
        // if (array_key_exists('fatigue', $cost) && $fatigue < $cost['fatigue']) {
        //     throw new BgaUserException(clienttranslate('Not enough fatigue'));
        // }
        if (!$this->checkRequirements($this->getAction($action, $subAction, ...$args))) {
            throw new BgaUserException(clienttranslate('Can\'t use this action'));
        }
        $validActions = $this->getValidActions();
        if (!array_key_exists($action, $validActions)) {
            throw new BgaUserException(clienttranslate('This action can not be used this turn'));
        }
    }
    public function getValidActions()
    {
        // Get some values from the current game situation from the database.
        $validActionsFiltered = array_filter($this->getActions(), function ($v) {
            $actionCost = $this->getActionCost($v['id']);
            $actions = $this->game->character->getActiveActions();
            $fatigue = $this->game->character->getActiveFatigue();
            // Rock only needs 1 actions, this is in the hindrance expansion
            $this->game->hooks->onSpendActionCost($actionCost);
            return $this->checkRequirements($v) &&
                (!array_key_exists('actions', $actionCost) || $actions >= $actionCost['actions']) &&
                (!array_key_exists('fatigue', $actionCost) || $fatigue >= $actionCost['fatigue']);
        });
        $data = array_column(
            array_map(
                function ($k, $v) {
                    return [$k, $this->getActionCost($k)];
                },
                array_keys($validActionsFiltered),
                $validActionsFiltered
            ),
            1,
            0
        );
        return $this->game->hooks->onGetValidActions($data);
    }
}
