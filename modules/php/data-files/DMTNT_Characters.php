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
                'name' => 'LySia Lamore',
            ],
            'garrett' => [
                'type' => 'character',
                'actions' => '5',
                'name' => 'Black Gus Garrett',
            ],
        ];
    }
}
