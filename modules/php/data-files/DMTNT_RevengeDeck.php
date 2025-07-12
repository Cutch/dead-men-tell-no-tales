<?php
namespace Bga\Games\DeadMenTellNoTales;

use Bga\Games\DeadMenTellNoTales\Game;
use BgaUserException;

class DMTNT_RevengeDeckData
{
    public function getData(): array
    {
        return [
            'revenge-back' => [
                'type' => 'back',
                'deck' => 'revenge',
            ],
            'revenge001' => [
                'type' => 'deck',
                'deck' => 'revenge',
                'dice' => 0,
                'color' => 'both',
                'action' => 'crew-move',
            ],
            'revenge002' => [
                'type' => 'deck',
                'deck' => 'revenge',
                'dice' => 2,
                'color' => 'red',
                'action' => 'deckhand-spread',
            ],
            'revenge003' => [
                'type' => 'deck',
                'deck' => 'revenge',
                'dice' => 3,
                'color' => 'red',
                'action' => 'deckhand-spread',
            ],
            'revenge004' => [
                'type' => 'deck',
                'deck' => 'revenge',
                'dice' => 4,
                'color' => 'red',
                'action' => 'deckhand-spread',
            ],

            'revenge005' => [
                'type' => 'deck',
                'deck' => 'revenge',
                'dice' => 5,
                'color' => 'red',
            ],
            'revenge006' => [
                'type' => 'deck',
                'deck' => 'revenge',
                'dice' => 0,
                'color' => 'yellow',
                'action' => 'deckhand-spawn',
            ],
            'revenge007' => [
                'type' => 'deck',
                'deck' => 'revenge',
                'dice' => 1,
                'color' => 'yellow',
                'action' => 'deckhand-spawn',
            ],
            'revenge008' => [
                'type' => 'deck',
                'deck' => 'revenge',
                'dice' => 2,
                'color' => 'yellow',
                'action' => 'deckhand-spread',
            ],
            'revenge009' => [
                'type' => 'deck',
                'deck' => 'revenge',
                'dice' => 3,
                'color' => 'yellow',
                'action' => 'deckhand-spread',
            ],
            'revenge010' => [
                'type' => 'deck',
                'deck' => 'revenge',
                'dice' => 4,
                'color' => 'yellow',
                'action' => 'deckhand-spread',
            ],
            'revenge011' => [
                'type' => 'deck',
                'deck' => 'revenge',
                'dice' => 5,
                'color' => 'yellow',
            ],
            'revenge012' => [
                'type' => 'deck',
                'deck' => 'revenge',
                'dice' => 1,
                'color' => 'both',
                'action' => 'crew-move',
            ],
            'revenge013' => [
                'type' => 'deck',
                'deck' => 'revenge',
                'dice' => 2,
                'color' => 'both',
                'action' => 'crew-move',
            ],
            'revenge014' => [
                'type' => 'deck',
                'deck' => 'revenge',
                'dice' => 3,
                'color' => 'both',
                'action' => 'crew-move',
            ],
            'revenge015' => [
                'type' => 'deck',
                'deck' => 'revenge',
                'dice' => 4,
                'color' => 'both',
                'action' => 'deckhand-spawn',
            ],
            'revenge016' => [
                'type' => 'deck',
                'deck' => 'revenge',
                'dice' => 5,
                'color' => 'both',
            ],
            'revenge017' => [
                'type' => 'deck',
                'deck' => 'revenge',
                'dice' => 5,
                'color' => 'both',
            ],
            'revenge018' => [
                'type' => 'deck',
                'deck' => 'revenge',
                'dice' => 0,
                'color' => 'red',
                'action' => 'deckhand-spawn',
            ],
            'revenge019' => [
                'type' => 'deck',
                'deck' => 'revenge',
                'dice' => 1,
                'color' => 'red',
                'action' => 'deckhand-spawn',
            ],
        ];
    }
}
