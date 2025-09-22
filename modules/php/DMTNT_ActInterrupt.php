<?php
declare(strict_types=1);

namespace Bga\Games\DeadMenTellNoTales;

use Closure;

class DMTNT_ActInterrupt
{
    private Game $game;
    private array $activatableSkills = [];
    // private array $calledFunction = [];

    public function __construct(Game $game)
    {
        $this->game = $game;
        // $this->calledFunction = [];
    }
    public function interruptableFunction(
        string $functionName,
        array $args = [],
        callable $hook,
        callable $startCallback,
        callable $endCallback
    ) {
        $entireState = $this->getEntireState();
        $originalFunctionName = $functionName;
        $existingData = $this->getState($functionName);
        if (!$existingData) {
            // First time calling
            $data = $startCallback($this->game, ...$args);
            $currentState = $this->game->gamestate->state(true, false, true)['name'];
            if ($data === null) {
                return;
            }
            $data['functionName'] = $functionName;
            $data['isOriginalFunction'] = $originalFunctionName == $functionName;
            $res = $hook($data, ['suffix' => 'Pre']);
            $interrupt = $res && array_key_exists('interrupt', $res) && $res['interrupt'];
            $cancel = $res && array_key_exists('cancel', $res) && $res['cancel'];
            $interruptData = [
                'data' => $data,
                'functionName' => $functionName,
                'args' => $args,
                'currentState' => $currentState,
                'skills' => $this->activatableSkills,
                'stateNumber' => sizeof($entireState) + 1,
            ];
            $this->activatableSkills = [];
            if ($cancel) {
                return;
            }
            // if($this->game->gamestate->state(true,true,true)['name'] != $currentState){
            //     // If we moved to a selection screen, we will need to call the hook again after, and finalize the function

            //     $this->setState($functionName, $interruptData);
            // }
            // else
            if (sizeof($interruptData['skills']) == 0 && !$interrupt) {
                $this->game->log('exitHook noSkill', 'not interrupted', $currentState);
                // No skills can activate
                $res = $hook($data, ['postOnly' => true]);
                $endCallback($this->game, false, $data, ...$args);
            } else {
                $this->setState($functionName, $interruptData);
                // Goto the skill screen
                if ($this->game->gamestate->state(true, false, true)['name'] === $currentState) {
                    $this->game->nextState('interrupt');
                    $this->game->completeAction(false);
                }
            }
        } elseif (
            array_key_exists('cancelled', $existingData) &&
            sizeof($existingData['skills']) ==
                sizeof(
                    array_filter($existingData['skills'], function ($s) {
                        return array_key_exists('cancellable', $s) && $s['cancellable'];
                    })
                )
        ) {
            $this->setState($functionName, null);
        } elseif (!array_key_exists('activated', $existingData)) {
            $this->setState($functionName, ['activated' => true, ...$existingData]);
            $this->game->log('exitHook finalize', $functionName, $this->game->gamestate->state(true, false, true)['name'], $existingData);
            // Don't need to re-check for interrupts
            $hook($existingData['data'], ['suffix' => 'Post']);
            // Calling after skill screen
            $endCallback($this->game, true, $existingData['data'], ...$existingData['args']);
            $this->setState($functionName, null);
        } else {
            $this->setState($functionName, null);
        }
    }
    public function checkForInterrupt(): bool
    {
        $state = $this->getDataForState() ?? $this->getLatestInterruptState();
        $this->game->log('checkForInterrupt', $state);
        if (!$state) {
            return false;
        }
        $data = $state['data'];

        if ($data && array_key_exists('functionName', $data)) {
            $this->game->log('checkForInterrupt ' . $state['functionName'], $data['args']);
            call_user_func([$this->game, $data['functionName']], ...$data['args']);
            return true;
        }
        return false;
    }
    public function getState(string $functionName): ?array
    {
        $data = $this->getEntireState();
        return array_key_exists($functionName, $data) ? $data[$functionName] : null;
    }
    public function setState(string $functionName, ?array $data): void
    {
        $currentData = $this->getEntireState();
        if ($data) {
            $currentData[$functionName] = $data;
        } else {
            unset($currentData[$functionName]);
        }
        $this->setEntireState($currentData);
    }
    public function getEntireState(): array
    {
        return $this->game->gameData->get('actInterruptState');
    }
    private function setEntireState(array $data): void
    {
        $this->game->gameData->set('actInterruptState', $data);
    }
    private function getSkillsCharacterIds(array $skills): array
    {
        return array_unique(
            array_map(function ($skill) {
                return $skill['characterId'];
            }, $skills)
        );
    }
    public function addMoreSkillInterrupt($skill): void
    {
        $state = $this->getLatestInterruptState();
        array_push($state['data']['skills'], $skill);
        $this->setState($state['functionName'], $state['data']);
    }
    public function addSkillInterrupt($skill): void
    {
        if (!array_key_exists('requires', $skill) || $skill['requires']($this->game, $skill)) {
            array_push($this->activatableSkills, $skill);
        }
    }
    public function getLatestInterruptState(): ?array
    {
        $state = $this->getEntireState();
        $maxStateNumber = 0;
        array_walk($state, function ($v) use (&$maxStateNumber) {
            $maxStateNumber = max($maxStateNumber, $v['stateNumber']);
        });

        $data = array_keys(
            array_filter($state, function ($v) use ($maxStateNumber) {
                return $v['stateNumber'] == $maxStateNumber;
            })
        );
        if (sizeof($data) == 0) {
            return null;
        }
        $functionName = $data[0];
        $data = $state[$functionName];
        return ['functionName' => $functionName, 'data' => $data];
    }
    private function getDataForState(): ?array
    {
        $state = $this->getEntireState();
        $stateName = $this->game->gamestate->state(true, false, true)['name'];
        $data = array_keys(
            array_filter($state, function ($v) use ($stateName) {
                return $v['currentState'] == $stateName;
            })
        );
        if (sizeof($data) == 0) {
            return null;
        }
        $functionName = $data[0];
        $data = $state[$functionName];
        return ['functionName' => $functionName, 'data' => $data];
    }
    public function actInterrupt(string $skillId, ?string $skillSecondaryId = null): void
    {
        $state = $this->getLatestInterruptState();
        $this->game->character->addExtraTime();
        $this->game->log('actInterrupt', ['action' => 'actInterrupt', 'state' => $state]);
        if (!$state) {
            return;
        }
        $functionName = $state['functionName'];
        $data = $state['data'];

        $skills = $data['skills'];
        $characterIds = $this->getSkillsCharacterIds($skills);
        // Remove skills so that we know there's nothing left to do
        array_walk($skills, function ($v, $k) use ($skillId, &$skills, &$data) {
            if ($skillId == $v['id']) {
                if (array_key_exists($k, $skills)) {
                    $data['skills'] = &$skills;
                    $this->game->hooks->onInterrupt($data, $v);
                }
                unset($skills[$k]);
                $skills = array_values($skills);
            }
        });
        if (array_key_exists('skipAndDontComplete', $data)) {
        } else {
            $this->setState($functionName, [...$data, 'skills' => $skills]);
            $newCharacterIds = $this->getSkillsCharacterIds($skills);

            $array = array_unique(array_diff($characterIds, $newCharacterIds));
            // if (sizeof($array) > 0) {
            //     $this->game->gamestate->setPlayerNonMultiactive($array[0], $data['currentState']);
            // }
            $changeState = !$this->game->gamestate->isMutiactiveState();
            if (!$changeState) {
                foreach ($array as $k => $v) {
                    $changeState |= $this->game->gameData->removeMultiActiveCharacter($v, $data['currentState']);
                }
            }
            if ($changeState) {
                $this->completeInterrupt();
            }
        }
    }
    public function completeInterrupt()
    {
        $state = $this->getLatestInterruptState();
        if (!$state) {
            return;
        }
        $data = $state['data'];
        // Check that the state change has not already handled the function call
        if ($data && array_key_exists('functionName', $data) && $this->getState($data['functionName'])) {
            $this->game->log('actInterrupt ' . $state['functionName'], $data['args']);
            call_user_func([$this->game, $data['functionName']], ...$data['args']);
            $this->game->log('actInterrupt changeState ', $data['functionName']);
        }
    }
    public function onInterruptCancel($cancelAll = false)
    {
        $state = $this->getLatestInterruptState();
        if (!$state) {
            return false;
        }
        $data = $state['data'];

        $playerId = $this->game->getCurrentPlayer();

        $characterIds = $cancelAll
            ? $this->game->character->getAllCharacterIds()
            : array_map(function ($char) {
                return $char['id'];
            }, $this->game->character->getAllCharacterDataForPlayer($playerId));
        // Remove skills so that we know there's nothing left to do
        // $skills = $data['skills'];
        // array_walk($skills, function (&$v, $k) use ($characterIds) {
        //     if (in_array($v['characterId'], $characterIds)) {
        //         unset($v);
        //     }
        // });
        // $this->setState($state['functionName'], [...$data, 'skills' => $skills, 'activated' => true]);
        // $this->setState($state['functionName'], null); // TODO: for items this doesnt work, but does work for player turn?

        // $this->game->log($this->game->gamestate->state(true,true,true)['name'], $state);
        $changeState = false;
        foreach ($characterIds as $k => $v) {
            $changeState |= $this->game->gameData->removeMultiActiveCharacter($v, $data['currentState']);
        }
        if (sizeof($characterIds) > 0 && sizeof($this->game->gameData->getAllMultiActiveCharacter()) == 0 && !$changeState) {
            $this->game->nextState($data['currentState']);
            $changeState = true;
        }
        if ($changeState) {
            if ($data && array_key_exists('functionName', $data) && $this->getState($state['functionName'])) {
                $this->setState($state['functionName'], [...$this->getState($state['functionName']), 'cancelled' => true]);
                call_user_func([$this->game, $data['functionName']], ...$data['args']);
            }
        }

        return true;
    }
    public function argInterrupt(): array
    {
        $state = $this->getLatestInterruptState();
        if (!$state) {
            return [
                'character_name' => $this->game->getCharacterHTML(),
            ];
        }
        $data = $state['data'];
        $this->game->getAllPlayers($data);
        // $this->game->log('argInterrupt', ['action' => 'argInterrupt', 'state' => $data]);

        array_walk($data['skills'], function (&$skill) {
            $this->game->hooks->reconnectHooks($skill, $this->game->character->getSkill($skill['id'])['skill']);
        });

        // $this->game->log(
        //     'interrupt skills',
        //     $this->game->actions->wrapSkills(
        //         array_filter($data['skills'], function ($skill) {
        //             return $skill['type'] == 'skill';
        //         }),
        //         'actUseSkill'
        //     )
        // );
        return [
            ...$data,
            'character_name' => $this->game->getCharacterHTML(),
            'actions' => [
                [
                    'action' => 'actUseSkill',
                    'type' => 'action',
                ],
            ],
            'availableSkills' => $this->game->actions->wrapSkills(
                array_filter($data['skills'], function ($skill) {
                    return $skill['type'] == 'skill';
                }),
                'actUseSkill'
            ),
        ];
    }
    public function stInterrupt(): void
    {
        $state = $this->getLatestInterruptState();
        $data = $state['data'];
        $characterIds = $this->getSkillsCharacterIds($data['skills']);
        $this->game->gameData->setMultiActiveCharacter($characterIds, true);
        $changeState = false;
        if (sizeof($characterIds) == 0) {
            $this->game->nextState($data['currentState']);
            $changeState = true;
        }

        $this->game->log('stInterrupt', [
            'action' => 'stInterrupt',
            'state' => $state,
            'changeState' => $changeState,
            'state' => $data['currentState'],
        ]);
    }
}
