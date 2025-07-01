<?php
namespace Bga\Games\DeadMenTaleNoTales;

use Bga\Games\DeadMenTaleNoTales\Game;
use BgaUserException;

if (!function_exists('getUsePerDay')) {
    function getUsePerDay(string $itemId, Game $game)
    {
        $dailyUseItems = $game->gameData->get('dailyUseItems');
        return array_key_exists($itemId, $dailyUseItems) ? $dailyUseItems[$itemId] : 0;
    }
    function usePerDay(string $itemId, Game $game)
    {
        $dailyUseItems = $game->gameData->get('dailyUseItems');
        $dailyUseItems[$itemId] = array_key_exists($itemId, $dailyUseItems) ? $dailyUseItems[$itemId] + 1 : 1;
        $game->gameData->set('dailyUseItems', $dailyUseItems);
    }
    function subtractPerDay(string $itemId, Game $game)
    {
        $dailyUseItems = $game->gameData->get('dailyUseItems');
        $dailyUseItems[$itemId] = array_key_exists($itemId, $dailyUseItems) ? $dailyUseItems[$itemId] - 1 : 0;
        $game->gameData->set('dailyUseItems', $dailyUseItems);
        $game->markChanged('token');
        $game->markChanged('player');
    }
    function resetPerDay(Game $game)
    {
        $game->gameData->set('dailyUseItems', []);
        $game->markChanged('token');
        $game->markChanged('player');
    }
    function array_orderby()
    {
        $args = func_get_args();
        $data = array_shift($args);
        foreach ($args as $n => $field) {
            if (is_string($field)) {
                $tmp = [];
                foreach ($data as $key => $row) {
                    $tmp[$key] = $row[$field] ?? '0';
                }
                $args[$n] = $tmp;
            }
        }
        $args[] = &$data;
        call_user_func_array('array_multisort', $args);
        return array_pop($args);
    }
    function clearItemSkills(&$skills, $itemId)
    {
        array_walk($skills, function ($v, $k) use (&$skills, $itemId) {
            if ($v['itemId'] == $itemId) {
                unset($skills[$k]);
            }
        });
    }
}
class DMTNT_ItemsData
{
    public function getData(): array
    {
        $data = [
            'gem-b-necklace' => [
                'type' => 'item',
                'craftingLevel' => 4,
                'count' => 1,
                'expansion' => 'hindrance',
                'name' => clienttranslate('Blue Necklace'),
                'itemType' => 'necklace',
                'cost' => [
                    'gem-b' => 1,
                    'fiber' => 1,
                ],
                'onGetCharacterData' => function (Game $game, $item, &$data) {
                    if ($data['character_name'] == $item['character_name']) {
                        $data['maxFatigue'] = clamp($data['maxFatigue'] + 1, 0, 10);
                    }
                },
            ],
            'gem-p-necklace' => [
                'type' => 'item',
                'craftingLevel' => 4,
                'count' => 1,
                'expansion' => 'hindrance',
                'name' => clienttranslate('Purple Necklace'),
                'itemType' => 'necklace',
                'skills' => [
                    'skill1' => [
                        'type' => 'item-skill',
                        'name' => clienttranslate('Re-Roll'),
                        'state' => ['interrupt'],
                        'interruptState' => ['playerTurn'],
                        'perDay' => 1,
                        'onInvestigateFire' => function (Game $game, $skill, &$data) {
                            $char = $game->character->getCharacterData($skill['characterId']);
                            if ($data['roll'] < 3 && getUsePerDay($char['id'] . 'gem-p-necklace', $game) < 1) {
                                // If kara is not the character, and the roll is not the max
                                // $game->actInterrupt->addSkillInterrupt($skill);
                            }
                        },
                        'onInterrupt' => function (Game $game, $skill, &$data, $activatedSkill) {
                            if ($skill['id'] == $activatedSkill['id']) {
                                $char = $game->character->getCharacterData($skill['characterId']);
                                $game->eventLog(clienttranslate('${character_name} is re-rolling ${active_character_name}\'s fire die'), [
                                    ...$char,
                                    'active_character_name' => $game->character->getTurnCharacter()['character_name'],
                                ]);
                                $data['data']['roll'] = $game->rollFireDie($skill['parentName'], $char['character_name']);
                                usePerDay($char['id'] . 'gem-p-necklace', $game);
                            }
                        },
                        'requires' => function (Game $game, $skill) {
                            $char = $game->character->getCharacterData($skill['characterId']);
                            return getUsePerDay($char['id'] . 'gem-p-necklace', $game) < 1;
                        },
                    ],
                ],
            ],
        ];
        array_walk($data, function (&$item) {
            $item['totalCost'] = array_sum(array_values(array_key_exists('cost', $item) ? $item['cost'] : []));
        });
        $itemsData = array_orderby($data, 'craftingLevel', SORT_ASC, 'itemType', SORT_DESC, 'totalCost', SORT_ASC);
        return $itemsData;
    }
}
