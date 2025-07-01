<?php
namespace Bga\Games\DeadMenTaleNoTales;

use Bga\Games\DeadMenTaleNoTales\Game;
use BgaUserException;
class DMTNT_CharactersData
{
    public function getData(): array
    {
        return [
            'Gronk' => [
                // Done
                'type' => 'character',
                'fatigue' => '7',
                'actions' => '4',
                'name' => 'Gronk',
                'slots' => ['weapon', 'weapon', 'tool'],
                'skills' => [
                    'skill1' => [
                        'type' => 'skill',
                        'state' => ['playerTurn'],
                        'name' => clienttranslate('Gain 2 actions'),
                        'fatigue' => 2,
                        'healthAsActions' => true,
                        'perDay' => 1,
                        'getPerDayKey' => function (Game $game, $skill): string {
                            return $skill['characterId'];
                        },
                        'onUse' => function (Game $game, $skill) {
                            usePerDay($skill['getPerDayKey']($game, $skill), $game);
                            $game->character->adjustActiveActions(2);
                            $game->eventLog(
                                clienttranslate(
                                    '${character_name} gained ${count_1} ${character_resource_1}, lost ${count_2} ${character_resource_2}'
                                ),
                                [
                                    'count_1' => 2,
                                    'character_resource_1' => clienttranslate('actions'),
                                    'count_2' => 2,
                                    'character_resource_2' => clienttranslate('fatigue'),
                                ]
                            );
                            return ['notify' => false];
                        },
                        'requires' => function (Game $game, $skill) {
                            $char = $game->character->getCharacterData($skill['characterId']);
                            if ($char['isActive']) {
                                return getUsePerDay($skill['getPerDayKey']($game, $skill), $game) < 1;
                            }
                        },
                    ],
                ],
                'onEncounterPost' => function (Game $game, $char, &$data) {
                    if ($char['isActive'] && $game->encounter->killCheck($data)) {
                        $data['actions'] += 2;
                        $game->eventLog(clienttranslate('${character_name} gained ${count} ${character_resource}'), [
                            'count' => 2,
                            'character_resource' => clienttranslate('actions'),
                        ]);
                    }
                },
            ],
        ];
    }
}
