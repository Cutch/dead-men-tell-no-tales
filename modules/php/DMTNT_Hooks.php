<?php
declare(strict_types=1);

namespace Bga\Games\DeadMenTellNoTales;

use BgaUserException;

class DMTNT_Hooks
{
    private Game $game;
    private bool $checkInterrupt = false;
    public function __construct(Game $game)
    {
        $this->game = $game;
    }
    private function getHook(): array
    {
        $characters = $this->game->character->getAllCharacterData(true);
        $items = array_values(
            array_filter(
                array_map(function ($c) {
                    return $c['item'];
                }, $characters)
            )
        );
        $skills = array_merge(
            ...array_map(function ($c) {
                if (array_key_exists('skills', $c)) {
                    return $c['skills'];
                }
                return [];
            }, $items),
            ...array_map(function ($c) {
                $skills = [];
                if (array_key_exists('skills', $c)) {
                    $skills = $c['skills'];
                }
                return $skills;
            }, $characters)
        );
        return [...$characters, ...$skills, ...$items];
    }
    private function callHooks($functionName, $args, &$data1, &$data2 = null, &$data3 = null, &$data4 = null)
    {
        $this->checkInterrupt = array_key_exists('checkInterrupt', $args) ? $args['checkInterrupt'] : false;
        $hooks = $this->getHook();
        if ($this->checkInterrupt) {
            $hooks = array_filter($hooks, function ($object) use ($data1, $data2, $data3, $data4, $args) {
                // $interruptData = array_filter([$data1, $data2, $data3, $data4]);
                // $interruptData = $interruptData[sizeof($interruptData) - 1];
                return (!array_key_exists('state', $object) || in_array('interrupt', $object['state'])) &&
                    (!array_key_exists('interruptState', $object) || in_array($data1['currentState'], $object['interruptState'])) &&
                    (!array_key_exists('requires', $object) ||
                        $object['requires']($this->game, [...$object, ...$args], $data1, $data2, $data3, $data4));
            });
        }
        if (!array_key_exists('postOnly', $args) || !$args['postOnly']) {
            // Pre
            if (!array_key_exists('suffix', $args) || $args['suffix'] == 'Pre') {
                foreach ($hooks as $object) {
                    if (array_key_exists($functionName . 'Pre', $object)) {
                        $object[$functionName . 'Pre']($this->game, [...$object, ...$args], $data1, $data2, $data3, $data4);
                    }
                }
            }
            // Normal
            foreach ($hooks as $object) {
                if (array_key_exists($functionName, $object)) {
                    $object[$functionName]($this->game, [...$object, ...$args], $data1, $data2, $data3, $data4);
                }
            }
        }
        if (!array_key_exists('suffix', $args) || $args['suffix'] == 'Post') {
            foreach ($hooks as $object) {
                if (array_key_exists($functionName . 'Post', $object)) {
                    $object[$functionName . 'Post']($this->game, [...$object, ...$args], $data1, $data2, $data3, $data4);
                }
            }
        }
        $this->checkInterrupt = false;
    }
    function onInterrupt(&$data, $activatedSkill, array $args = [])
    {
        // Default checkInterrupt to true
        if (!array_key_exists('checkInterrupt', $args)) {
            $args['checkInterrupt'] = true;
        }
        $this->callHooks(__FUNCTION__, $args, $data, $activatedSkill);
        return $data;
    }
    public function reconnectHooks(&$jsonData, $underlyingData)
    {
        array_walk($underlyingData, function ($v, $k) use (&$jsonData) {
            if (str_starts_with($k, 'on') || str_starts_with($k, 'get')) {
                $jsonData[$k] = $v;
            }
        });
    }
    function onGetCharacterData(&$data, array $args = [])
    {
        $this->callHooks(__FUNCTION__, $args, $data);
        return $data;
    }
    function onGetCharacterSkills(&$data, array $args = [])
    {
        $this->callHooks(__FUNCTION__, $args, $data);
        return $data;
    }
    function onGetValidActions(&$data, array $args = [])
    {
        $this->callHooks(__FUNCTION__, $args, $data);
        return $data;
    }
    function onGetActionCost(&$data, array $args = [])
    {
        $this->callHooks(__FUNCTION__, $args, $data);
        return $data;
    }
    function onGetActionSelectable(&$data, array $args = [])
    {
        $this->callHooks(__FUNCTION__, $args, $data);
        return $data;
    }
    function onDrawRevengeCard(&$data, array $args = [])
    {
        $this->callHooks(__FUNCTION__, $args, $data);
        return $data;
    }
    function onGetAttack(&$data, array $args = [])
    {
        $this->callHooks(__FUNCTION__, $args, $data);
    }
    function onUseSkill(&$data, array $args = [])
    {
        $this->callHooks(__FUNCTION__, $args, $data);
    }
    function onGetDeckhandTargetCount(&$data, array $args = [])
    {
        $this->callHooks(__FUNCTION__, $args, $data);
        return $data;
    }
    function onCalculateMoves(&$data, array $args = [])
    {
        $this->callHooks(__FUNCTION__, $args, $data);
        return $data;
    }
    function onCalculateFires(&$data, array $args = [])
    {
        $this->callHooks(__FUNCTION__, $args, $data);
        return $data;
    }
    function onCalculateMovesHasTreasure(&$data, array $args = [])
    {
        $this->callHooks(__FUNCTION__, $args, $data);
        return $data;
    }
    function onRollDie(&$data, array $args = [])
    {
        $this->callHooks(__FUNCTION__, $args, $data);
        return $data;
    }
    function onEliminateDeckhands(&$data, array $args = [])
    {
        $this->callHooks(__FUNCTION__, $args, $data);
        return $data;
    }
    function onDrinkGrog(&$data, array $args = [])
    {
        $this->callHooks(__FUNCTION__, $args, $data);
        return $data;
    }
    function onCardSelection(&$data, array $args = [])
    {
        $this->callHooks(__FUNCTION__, $args, $data);
        return $data;
    }
    function onMove(&$data, array $args = [])
    {
        $this->callHooks(__FUNCTION__, $args, $data);
        return $data;
    }
    function onMoveFinalize(&$data, array $args = [])
    {
        $this->callHooks(__FUNCTION__, $args, $data);
        return $data;
    }
    function onAdjustActions(&$data, array $args = [])
    {
        $this->callHooks(__FUNCTION__, $args, $data);
        return $data;
    }
    function onAdjustFatigue(&$data, array $args = [])
    {
        $this->callHooks(__FUNCTION__, $args, $data);
        return $data;
    }
    function onCheckSkillRequirements(&$data, array $args = [])
    {
        $requires = ['requires' => true];
        $this->callHooks(__FUNCTION__, $args, $data, $requires);
        return $requires['requires'];
    }
    function onFightFire(&$data, array $args = [])
    {
        $this->callHooks(__FUNCTION__, $args, $data);
        return $data;
    }
    function onEndTurn(&$data, array $args = [])
    {
        $this->callHooks(__FUNCTION__, $args, $data);
        return $data;
    }
    function onCharacterChoose(&$data, array $args = [])
    {
        $this->callHooks(__FUNCTION__, $args, $data);
        return $data;
    }
    function onSpendActionCost(&$data, array $args = [])
    {
        $this->callHooks(__FUNCTION__, $args, $data);
        return $data;
    }
    function onMoveSelection(&$data, array $args = [])
    {
        $this->callHooks(__FUNCTION__, $args, $data);
        return $data;
    }
}
