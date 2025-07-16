<?php
/**
 *------
 * BGA framework: Gregory Isabelli & Emmanuel Colin & BoardGameArena
 * deadmentellnotales implementation : © Cutch <Your email address here>
 *
 * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
 * See http://en.boardgamearena.com/#!doc/Studio for more information.
 * -----
 *
 * states.inc.php
 *
 * deadmentellnotales game states description
 *
 */

/*
   Game state machine is a tool used to facilitate game developpement by doing common stuff that can be set up
   in a very easy way from this configuration file.

   Please check the BGA Studio presentation about game state to understand this, and associated documentation.

   Summary:

   States types:
   _ activeplayer: in this type of state, we expect some action from the active player.
   _ multipleactiveplayer: in this type of state, we expect some action from multiple players (the active players)
   _ game: this is an intermediary state where we don't expect any actions from players. Your game logic must decide what is the next game state.
   _ manager: special type for initial and final state

   Arguments of game states:
   _ name: the name of the GameState, in order you can recognize it on your own code.
   _ description: the description of the current game state is always displayed in the action status bar on
                  the top of the game. Most of the time this is useless for game state with "game" type.
   _ descriptionmyturn: the description of the current game state when it's your turn.
   _ type: defines the type of game states (activeplayer / multipleactiveplayer / game / manager)
   _ action: name of the method to call when this game state become the current game state. Usually, the
             action method is prefixed by "st" (ex: "stMyGameStateName").
   _ possibleactions: array that specify possible player actions on this step. It allows you to use "checkAction"
                      method on both client side (Javacript: this.checkAction) and server side (PHP: $this->checkAction).
   _ transitions: the transitions are the possible paths to go from a game state to another. You must name
                  transitions in order to use transition names in "nextState" PHP method, and use IDs to
                  specify the next game state for each transition.
   _ args: name of the method to call to retrieve arguments for this gamestate. Arguments are sent to the
           client side to be used on "onEnteringState" or to set arguments in the gamestate description.
   _ updateGameProgression: when specified, the game progression is updated (=> call to your getGameProgression
                            method).
*/

//    !! It is not a good idea to modify this file when a game is running !!
$gameSetup = 1;
$gameStart = 2;
$characterSelect = 3;
$initializeTile = 7;
$placeTile = 8;
$finalizeTile = 9;
$playerTurn = 10;
$drawRevengeCard = 11;
$nextCharacter = 15;
$interrupt = 20;
$battle = 30;
$battleSelection = 31;
$postBattle = 32;
$crewMovement = 70;
$characterSelection = 71;
$cardSelection = 72;
$itemSelection = 73;
$characterMovement = 74;
$undo = 96;
$changeZombiePlayer = 97;
$gameEnd = 99;

$interruptScreens = [
    'drawRevengeCard' => $drawRevengeCard,
    'characterSelection' => $characterSelection,
    'itemSelection' => $itemSelection,
    'interrupt' => $interrupt,
    'cardSelection' => $cardSelection,
    'crewMovement' => $crewMovement,
    'characterMovement' => $characterMovement,
    'undo' => $undo,
    'changeZombiePlayer' => $changeZombiePlayer,
];

$machinestates = [
    // The initial state. Please do not modify.

    $gameSetup => [
        'name' => 'gameSetup',
        'description' => '',
        'type' => 'manager',
        'action' => 'stGameSetup',
        'transitions' => ['' => $gameStart],
    ],
    $gameStart => [
        'name' => 'gameStart',
        'descriptionmyturn' => clienttranslate('Welcome'),
        'type' => 'game',
        'action' => 'stGameStart',
        'transitions' => ['characterSelect' => $characterSelect, 'initializeTile' => $initializeTile],
    ],
    $characterSelect => [
        'name' => 'characterSelect',
        'description' => clienttranslate('Others are selecting a character'),
        'descriptionmyturn' => clienttranslate('Select a Character'),
        'type' => 'multipleactiveplayer',
        'args' => 'argSelectionCount',
        'possibleactions' => ['actChooseCharacters', 'actCharacterClicked', 'actUnBack'],
        'transitions' => ['initializeTile' => $initializeTile],
        'action' => 'stSelectCharacter',
    ],
    $initializeTile => [
        'name' => 'initializeTile',
        'description' => clienttranslate('${character_name} is searching the ship'),
        'descriptionmyturn' => clienttranslate('Search the ship'),
        'type' => 'game',
        'action' => 'stInitializeTile',
        'args' => 'argBasic',
        'transitions' => [
            'placeTile' => $placeTile,
            'playerTurn' => $playerTurn,
        ],
    ],
    $placeTile => [
        'name' => 'placeTile',
        'description' => clienttranslate('${character_name} is searching the ship'),
        'descriptionmyturn' => clienttranslate('Search the ship'),
        'type' => 'activeplayer',
        'args' => 'argPlaceTile',
        'possibleactions' => ['actPlaceTile'],
        'transitions' => [
            'finalizeTile' => $finalizeTile,
            'changeZombiePlayer' => $changeZombiePlayer,
        ],
    ],
    $finalizeTile => [
        'name' => 'finalizeTile',
        'description' => clienttranslate('${character_name} is searching the ship'),
        'descriptionmyturn' => clienttranslate('Search the ship'),
        'type' => 'game',
        'action' => 'stFinalizeTile',
        'args' => 'argFinalizeTile',
        'transitions' => [
            'initializeTile' => $initializeTile,
            'playerTurn' => $playerTurn,
        ],
    ],
    $playerTurn => [
        'name' => 'playerTurn',
        'description' => clienttranslate('${character_name} is playing'),
        'descriptionmyturn' => clienttranslate('${character_name} can'),
        'type' => 'activeplayer',
        'args' => 'argPlayerState',
        'action' => 'stPlayerState',
        'possibleactions' => [
            'actTrade',
            'actEndTurn',
            'actUndo',
            'actMove',
            'actFightFire',
            'actEliminateDeckhand',
            'actPickupToken',
            'actRest',
            'actIncreaseBattleStrength',
            'actDrop',
            'actDrinkGrog',
            'actSwapItem',
            'actAbandonShip',
            'actUseSkill',
        ],
        'transitions' => [
            // 'placeTile' => $placeTile,
            'endGame' => $gameEnd,
            'drawRevengeCard' => $drawRevengeCard,
            'changeZombiePlayer' => $changeZombiePlayer,
            'battleSelection' => $battleSelection,
        ],
    ],
    $undo => [
        'name' => 'undo',
        'descriptionmyturn' => clienttranslate('Waiting'),
        'type' => 'game',
        'transitions' => [],
    ],
    $drawRevengeCard => [
        'name' => 'drawRevengeCard',
        'description' => clienttranslate('Skelit\'s Revenge'),
        'descriptionmyturn' => clienttranslate('Skelit\'s Revenge'),
        'type' => 'game',
        'args' => 'argDrawRevengeCard',
        'action' => 'stDrawRevengeCard',
        'transitions' => [
            'endGame' => $gameEnd,
            'nextCharacter' => $nextCharacter,
            'drawRevengeCard' => $drawRevengeCard,
        ],
    ],
    $nextCharacter => [
        'name' => 'nextCharacter',
        'description' => '',
        'type' => 'game',
        'action' => 'stNextCharacter',
        'args' => 'argNextCharacter',
        'updateGameProgression' => true,
        'transitions' => ['endGame' => $gameEnd, 'initializeTile' => $initializeTile],
    ],
    $characterSelection => [
        'name' => 'characterSelection',
        'description' => clienttranslate('${character_name} is selecting a character'),
        'descriptionmyturn' => clienttranslate('${character_name} Select a character'),
        'type' => 'multipleactiveplayer',
        'args' => 'argSelectionState',
        'possibleactions' => ['actSelectCharacter', 'actCancel'],
        'transitions' => [
            'playerTurn' => $playerTurn,
            'changeZombiePlayer' => $changeZombiePlayer,
        ],
    ],
    $cardSelection => [
        'name' => 'cardSelection',
        'description' => clienttranslate('${character_name} is selecting a card'),
        'descriptionmyturn' => clienttranslate('${character_name} Select a card'),
        'type' => 'multipleactiveplayer',
        'args' => 'argSelectionState',
        'possibleactions' => ['actSelectCard', 'actCancel'],
        'transitions' => ['playerTurn' => $playerTurn, 'changeZombiePlayer' => $changeZombiePlayer],
    ],
    $crewMovement => [
        'name' => 'crewMovement',
        'description' => clienttranslate('The crew is moving'),
        'descriptionmyturn' => clienttranslate('${character_name} Select where the crew moves'),
        'type' => 'multipleactiveplayer',
        'args' => 'argSelectionState',
        'possibleactions' => ['actMoveCrew', 'actCancel'],
        'transitions' => ['playerTurn' => $playerTurn, 'changeZombiePlayer' => $changeZombiePlayer],
    ],
    $characterMovement => [
        'name' => 'characterMovement',
        'description' => clienttranslate('${character_name} is moving'),
        'descriptionmyturn' => '${character_name} ${title}',
        'type' => 'activeplayer',
        'args' => 'argSelectionState',
        'possibleactions' => ['actMoveSelection'],
        'transitions' => ['playerTurn' => $playerTurn, 'changeZombiePlayer' => $changeZombiePlayer],
    ],
    $itemSelection => [
        'name' => 'itemSelection',
        'description' => clienttranslate('${character_name} is selecting an item'),
        'descriptionmyturn' => clienttranslate('${character_name} Select an item'),
        'type' => 'multipleactiveplayer',
        'args' => 'argSelectionState',
        'possibleactions' => ['actSelectItem', 'actCancel'],
        'transitions' => ['playerTurn' => $playerTurn, 'changeZombiePlayer' => $changeZombiePlayer],
    ],
    $battleSelection => [
        'name' => 'battleSelection',
        'description' => clienttranslate('${character_name} is Battling'),
        'descriptionmyturn' => clienttranslate('Battling'),
        'type' => 'activeplayer',
        'action' => 'stBattleSelection',
        'args' => 'argBattleSelection',
        'possibleactions' => ['actBattleSelection', 'actUndo'],
        'transitions' => [
            'endGame' => $gameEnd,
            'changeZombiePlayer' => $changeZombiePlayer,
            'battle' => $battle,
            'postBattle' => $postBattle,
            'playerTurn' => $playerTurn,
        ],
    ],
    $battle => [
        'name' => 'battle',
        'description' => clienttranslate('${character_name} is Battling'),
        'descriptionmyturn' => clienttranslate('Battling'),
        'type' => 'activeplayer',
        'args' => 'argBattle',
        'possibleactions' => ['actUseStrength', 'actDontUseStrength'],
        'transitions' => [
            'endGame' => $gameEnd,
            'postBattle' => $postBattle,
            'endTurn' => $nextCharacter,
            'changeZombiePlayer' => $changeZombiePlayer,
            'playerTurn' => $playerTurn,
            'nextCharacter' => $nextCharacter,
        ],
    ],
    $postBattle => [
        'name' => 'postBattle',
        'description' => clienttranslate('${character_name} is Battling'),
        'descriptionmyturn' => clienttranslate('Battling'),
        'type' => 'activeplayer',
        'action' => 'stPostBattle',
        'args' => 'argPostBattle',
        'possibleactions' => ['actRetreat', 'actMakeThemFlee', 'actBattleAgain'],
        'transitions' => [
            'endGame' => $gameEnd,
            'battleSelection' => $battleSelection,
            'playerTurn' => $playerTurn,
            'postBattle' => $postBattle,
            'battle' => $battle,
            'endTurn' => $nextCharacter,
            'changeZombiePlayer' => $changeZombiePlayer,
        ],
    ],
    $interrupt => [
        'name' => 'interrupt',
        'description' => clienttranslate('Other players are looking at their skills'),
        'descriptionmyturn' => clienttranslate('Looking at skills'),
        'type' => 'multipleactiveplayer',
        'action' => 'stInterrupt',
        'args' => 'argInterrupt',
        'possibleactions' => ['actUseSkill', 'actDone', 'actForceSkip'],
        'transitions' => [
            'endGame' => $gameEnd,
            'playerTurn' => $playerTurn,
            'drawRevengeCard' => $drawRevengeCard,
            'endTurn' => $nextCharacter,
            'characterSelection' => $characterSelection,
            'cardSelection' => $cardSelection,
            'battle' => $battle,
            'battleSelection' => $battleSelection,
            'changeZombiePlayer' => $changeZombiePlayer,
        ],
    ],
    $changeZombiePlayer => [
        'name' => 'changeZombiePlayer',
        'descriptionmyturn' => clienttranslate('Waiting for other players'),
        'type' => 'game',
        'transitions' => [],
    ],
    // Final state.
    // Please do not modify (and do not overload action/args methods).
    $gameEnd => [
        'name' => 'gameEnd',
        'description' => clienttranslate('End of game'),
        'descriptionmyturn' => clienttranslate('End of game'),
        'type' => 'manager',
        'action' => 'stGameEnd',
        'args' => 'argGameEnd',
    ],
];

foreach ($machinestates as $key => $state) {
    $machinestates[$changeZombiePlayer]['transitions'][$state['name']] = $key;
}

$interruptableScreens = [$battle, $battleSelection, $drawRevengeCard, $playerTurn, $nextCharacter, $battle, $postBattle, $placeTile];
$interruptableScreenNames = [];
foreach ($interruptableScreens as $stateId) {
    $interruptableScreenNames[$stateId] = $machinestates[$stateId]['name'];
    $machinestates[$stateId]['transitions'] = [...$machinestates[$stateId]['transitions'], ...$interruptScreens];
}

foreach ($interruptScreens as $interruptStateId) {
    $machinestates[$interruptStateId]['transitions'] = [...$machinestates[$interruptStateId]['transitions'], ...$interruptScreens];
}
foreach ($interruptableScreenNames as $stateId => $stateName) {
    foreach ($interruptScreens as $interruptStateId) {
        $machinestates[$interruptStateId]['transitions'][$stateName] = $stateId;
    }
}
