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
            ],
            'flynn' => [
                'type' => 'character',
                'actions' => '5',
                'name' => 'Crimson Flynn',
                'color' => '#cd402a',
            ],
            'whitebeard' => [
                'type' => 'character',
                'actions' => '5',
                'name' => 'Whitebeard',
                'color' => '#ece9e8',
            ],
            'jade' => [
                'type' => 'character',
                'actions' => '5',
                'name' => 'Jade',
                'color' => '#4a9746',
            ],
            'titian' => [
                'type' => 'character',
                'actions' => '5',
                'name' => 'Five-Fingered Titian',
                'color' => '#dc9d29',
            ],
            'fallen' => [
                'type' => 'character',
                'actions' => '5',
                'name' => 'Cobalt Fallen',
                'color' => '#008cb9',
            ],
        ];
    }
}
