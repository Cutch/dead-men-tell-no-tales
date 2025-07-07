<?php
namespace Bga\Games\DeadMenTellNoTales;

use Bga\Games\DeadMenTellNoTales\Game;
use BgaUserException;
class DMTNT_CharactersData
{
    public function getData(): array
    {
        return [
            'lamore' => [
                'type' => 'character',
                'actions' => '6',
                'name' => 'Lydia Lamore',
                'color' => '#89357d',
            ],
            'garrett' => [
                'type' => 'character',
                'actions' => '5',
                'name' => 'Black Gus Garrett',
                'color' => '#3c464c',
                // Garret can run and force deckhands to flee
            ],
            'flynn' => [
                'type' => 'character',
                'actions' => '5',
                'name' => 'Crimson Flynn',
                'color' => '#cd402a',
                'onCalculateFires' => function (Game $game, $char, &$data) {
                    if ($char['isActive']) {
                        $moveList = $game->map->getValidAdjacentTiles($data['x'], $data['y']);
                        $currentTile = $data['currentTile'];
                        array_walk($moveList, function ($firstTile) use ($currentTile, &$data, $game) {
                            if ($currentTile && !$game->map->testTouchPoints($currentTile, $firstTile)) {
                                return;
                            }
                            if ($firstTile['fire'] > 0) {
                                $data['fireList'][] = $firstTile['id'];
                            }
                        });
                    }
                },
            ],
            'whitebeard' => [
                'type' => 'character',
                'actions' => '5',
                'name' => 'Whitebeard',
                'color' => '#ece9e8',
                'onCalculateMoves' => function (Game $game, $char, &$data) {
                    if ($char['isActive']) {
                        $fatigueList = &$data['fatigueList'];
                        array_walk($fatigueList, function (&$cost, $k) {
                            $cost = max($cost - 1, 0);
                        });
                    }
                },
            ],
            'jade' => [
                'type' => 'character',
                'actions' => '5',
                'name' => 'Jade',
                'color' => '#4a9746',
                'onGetDeckhandTargetCount' => function (Game $game, $char, &$data) {
                    if ($char['isActive']) {
                        $data['count'] = 2;
                    }
                },
            ],
            'titian' => [
                'type' => 'character',
                'actions' => '5',
                'name' => 'Five-Fingered Titian',
                'color' => '#dc9d29',
                'skills' => [
                    'skill1' => [
                        'type' => 'skill',
                        'name' => clienttranslate('Pick 1'),
                        'state' => ['interrupt'],
                        'interruptState' => ['revenge'],
                        'onDrawRevenge' => function (Game $game, $skill, &$data) {
                            $game->actInterrupt->addSkillInterrupt($skill);
                        },
                        'onUseSkill' => function (Game $game, $skill, &$data) {
                            if ($data['skillId'] == $skill['id']) {
                                $existingData = $game->actInterrupt->getState('actDraw');
                                if (array_key_exists('data', $existingData)) {
                                    $deck = $existingData['data']['deck'];
                                    $card1 = $existingData['data']['card'];
                                    $card2 = $game->decks->pickCard($deck);
                                    $game->incStat(1, 'cards_drawn', $game->character->getSubmittingCharacter()['playerId']);
                                    $data['interrupt'] = true;
                                    $game->selectionStates->initiateState(
                                        'cardSelection',
                                        [
                                            'cards' => [$card1, $card2],
                                            'id' => $skill['id'],
                                        ],
                                        $game->character->getTurnCharacterId(),
                                        false
                                    );
                                }
                            }
                        },
                        'onCardSelection' => function (Game $game, $skill, &$data) {
                            $state = $game->selectionStates->getState('cardSelection');
                            if ($state && $state['id'] == $skill['id']) {
                                $discardCard = array_values(
                                    array_filter($state['cards'], function ($card) use ($data) {
                                        return $card['id'] != $data['cardId'];
                                    })
                                )[0];
                                $game->cardDrawEvent($discardCard, $discardCard['deck']);

                                $drawState = $game->actInterrupt->getState('actDraw');
                                $drawState['data']['card'] = $game->decks->getCard($data['cardId']);
                                $game->actInterrupt->setState('actDraw', $drawState);
                                $game->actInterrupt->actInterrupt($skill['id']);
                                $data['nextState'] = false;
                            }
                        },
                        'requires' => function (Game $game, $skill) {
                            $char = $game->character->getCharacterData($skill['characterId']);
                            return $char['isActive'];
                        },
                    ],
                ],
            ],
            'fallen' => [
                'type' => 'character',
                'actions' => '5',
                'name' => 'Cobalt Fallen',
                'color' => '#008cb9',
                'onCalculateMovesHasTreasure' => function (Game $game, $char, &$data) {
                    if ($char['isActive']) {
                        $data['hasTreasure'] = false;
                    }
                },
            ],
        ];
    }
}
