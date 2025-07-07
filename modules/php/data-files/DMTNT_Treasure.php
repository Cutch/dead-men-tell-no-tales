<?php
namespace Bga\Games\DeadMenTellNoTales;

use Bga\Games\DeadMenTellNoTales\Game;
use BgaUserException;

class DMTNT_TreasureData
{
    public function getData(): array
    {
        $data = [
            'captain-4' => [
                'type' => 'deck',
                'deck' => 'bag',
                'deckType' => 'captain',
                'count' => 1,
                'battle' => 4,
            ],
            'captain-8' => [
                'type' => 'deck',
                'deck' => 'bag',
                'deckType' => 'captain',
                'count' => 1,
                'battle' => 8,
            ],
            'explosion' => [
                'count' => 1,
            ],
            'explosion-barrel' => [
                'count' => 1,
            ],
            'guard-6' => [
                'type' => 'deck',
                'deck' => 'bag',
                'deckType' => 'guard',
                'count' => 2,
                'battle' => 6,
                'rewards' => ['treasure' => 2],
            ],
            'guard-7' => [
                'type' => 'deck',
                'deck' => 'bag',
                'deckType' => 'guard',
                'count' => 2,
                'battle' => 7,
                'rewards' => ['treasure' => 2],
            ],
            'guard-8' => [
                'type' => 'deck',
                'deck' => 'bag',
                'deckType' => 'guard',
                'count' => 2,
                'battle' => 8,
                'rewards' => ['treasure' => 2],
            ],
            'cutlass' => [
                'count' => 5,
            ],
            'rum-4' => [
                'deckType' => 'rum',
                'count' => 2,
                'fatigue' => 4,
            ],
            'rum-5' => [
                'deckType' => 'rum',
                'count' => 1,
                'fatigue' => 5,
            ],
            'crew-3' => [
                'type' => 'deck',
                'deck' => 'bag',
                'deckType' => 'crew',
                'count' => 3,
                'rewards' => ['cutlass' => 1, 'rum-4' => 2],
                'battle' => 3,
            ],
            'crew-4' => [
                'type' => 'deck',
                'deck' => 'bag',
                'deckType' => 'crew',
                'count' => 3,
                'rewards' => ['cutlass' => 2, 'rum-5' => 1],
                'battle' => 4,
            ],
            'crew-5' => [
                'type' => 'deck',
                'deck' => 'bag',
                'deckType' => 'crew',
                'count' => 2,
                'rewards' => ['cutlass' => 2],
                'battle' => 5,
            ],
            'token-action' => [
                'count' => 1,
            ],
            'trapdoor' => [
                'type' => 'deck',
                'deck' => 'bag',
                'count' => 6,
            ],
            'treasure' => [
                'count' => 6,
            ],
        ];
        return $data;
    }
}
