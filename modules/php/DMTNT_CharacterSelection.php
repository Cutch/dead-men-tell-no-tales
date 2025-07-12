<?php
declare(strict_types=1);

namespace Bga\Games\DeadMenTellNoTales;

use BgaUserException;
use Exception;

class DMTNT_CharacterSelection
{
    private Game $game;
    public function __construct(Game $game)
    {
        $this->game = $game;
    }

    public function actCharacterClicked(
        ?string $character1 = null,
        ?string $character2 = null,
        ?string $character3 = null,
        ?string $character4 = null,
        ?string $character5 = null
    ): void {
        $characters = [$character1, $character2, $character3, $character4, $character5];
        $this->validateCharacterCount(false, $characters);
        $playerId = $this->game->getCurrentPlayer();
        $characters = array_filter($characters);
        sort($characters);
        $this->setTurnOrder($playerId, $characters);
        // Check if already selected
        $escapedCharacterList = join(
            ', ',
            array_map(function ($char) use ($playerId) {
                $char = $this->game::escapeStringForDB($char);
                return "'$char'";
            }, $characters)
        );
        if (
            $escapedCharacterList &&
            sizeof(
                array_values(
                    $this->game->getCollectionFromDb(
                        "SELECT 1 FROM `character` WHERE player_id != $playerId AND character_id in (" . $escapedCharacterList . ')'
                    )
                )
            ) > 0
        ) {
            throw new BgaUserException(clienttranslate('Character Selected By Another Player'));
        }
        // Remove player's previous selected
        $this->game::DbQuery("DELETE FROM `character` WHERE player_id = $playerId");
        // Add player's current selected
        if ($characters) {
            $values = join(
                ', ',
                array_map(function ($char) use ($playerId) {
                    extract($this->game->data->getCharacters()[$char]);
                    $char = $this->game::escapeStringForDB($char);
                    return "('$char', $playerId, $actions)";
                }, $characters)
            );
            $this->game::DbQuery("INSERT INTO `character` (`character_id`, `player_id`, `actions`) VALUES $values");
        }
        // Notify Players
        $results = [];
        $this->game->getAllPlayers($results);
        $this->game->notify('characterClicked', '', ['gameData' => $results]);
    }
    private function validateCharacterCount(bool $checkIfNotEnough, array $characters)
    {
        // Check for bad character name
        foreach ($characters as $index => $char) {
            if ($char) {
                if (!array_key_exists($char, $this->game->data->getCharacters())) {
                    throw new Exception('Bad value for character');
                }
            }
        }
        // Check how many characters the player can select
        $count = $this->game->gameData->get('characterCount');
        if (sizeof(array_filter($characters)) > $count) {
            throw new BgaUserException(clienttranslate('Too many characters selected'));
        }
        if ($checkIfNotEnough && sizeof(array_filter($characters)) != $count) {
            throw new BgaUserException(clienttranslate('Not enough characters selected'));
        }
    }
    private function setTurnOrder($playerId, $selectedCharacters)
    {
        // Set the character turn order
        $turnOrder = $this->game->gameData->get('turnOrder');
        $players = $this->game->loadPlayersBasicInfos();
        $playerNo = ((int) $players[$playerId]['player_no']) - 1;
        $playerCount = sizeof($players);
        $characterCount = $this->game->gameData->get('characterCount');
        if ($playerCount <= 2) {
            for ($i = 0; $i < $characterCount; $i++) {
                $turnOrder[$playerNo * $characterCount + $i] = array_key_exists($i, $selectedCharacters) ? $selectedCharacters[$i] : null;
            }
        } else {
            $turnOrder[$playerNo] = array_key_exists(0, $selectedCharacters) ? $selectedCharacters[0] : null;
        }
        $this->game->gameData->set('turnOrder', $turnOrder);
    }
    public function randomCharacters(): void
    {
        $players = $this->game->getCollectionFromDb('SELECT `player_id` `id`, player_no FROM `player`');
        $characterCount = $this->game->gameData->get('characterCount');
        $characters = [...$this->game->data->getCharacters()];
        $items = [...$this->game->data->getItems()];
        shuffle($characters);
        shuffle($items);
        $selectedI = 0;
        $queries = [];
        array_walk($players, function ($v, $player) use ($characterCount, $characters, $items, &$selectedI, &$queries) {
            $selectedCharacters = [];
            for ($i = 0; $i < $characterCount; $i++) {
                $char = $characters[$selectedI];
                array_push($selectedCharacters, $char['id']);
                $name = $this->game::escapeStringForDB($char['id']);
                $actions = $char['actions'];
                $item = $this->game::escapeStringForDB($items[$selectedI]['id']);
                array_push($queries, "('$name', $player, $actions, '$item', 1)");
                $selectedI++;
            }
            $this->setTurnOrder($player, $selectedCharacters);
        });
        $values = join(', ', $queries);
        $this->game::DbQuery("INSERT INTO `character` (`character_id`, `player_id`, `actions`, `item`, `confirmed`) VALUES $values");
    }
    public function actChooseCharacters(): void
    {
        $playerId = $this->game->getCurrentPlayer();
        $selectedCharacters = array_map(function ($char) {
            return $char['characterId'];
        }, array_values($this->game->getCollectionFromDb("SELECT character_id FROM `character` WHERE `player_id` = '$playerId'")));
        $selectedCharacters = array_orderby($selectedCharacters, 'character_id', SORT_ASC);

        $this->validateCharacterCount(true, $selectedCharacters);

        $this->game::DbQuery("UPDATE `character` set `confirmed`=1 WHERE `player_id` = $playerId");
        $selectedCharactersArgs = [];
        $message = '';
        foreach ($selectedCharacters as $index => $value) {
            $characterObject = $this->game->data->getCharacters()[$value];
            // if (array_key_exists('startsWith', $characterObject)) {
            //     $this->game->character->equipEquipment($value, [$itemId]);$characterObject['startsWith']
            // }
            $this->game->hooks->onCharacterChoose($characterObject);

            $selectedCharactersArgs['character' . ($index + 1)] = $value;
        }
        switch (sizeof($selectedCharacters)) {
            case 1:
                $message = clienttranslate('${player_name} selected ${character1}');
                break;
            case 2:
                $message = clienttranslate('${player_name} selected ${character1} and ${character2}');
                break;
            case 3:
                $message = clienttranslate('${player_name} selected ${character1}, ${character2} and ${character3}');
                break;
            case 4:
                $message = clienttranslate('${player_name} selected ${character1}, ${character2}, ${character3} and ${character4}');
                break;
            case 5:
                $message = clienttranslate(
                    '${player_name} selected ${character1}, ${character2}, ${character3}, ${character4} and ${character5}'
                );
                break;
        }

        $this->setTurnOrder($playerId, $selectedCharacters);
        $results = ['player_id' => $playerId];
        $this->game->getAllPlayers($results);
        // $this->game->initCharacters($playerId);
        $this->game->notify(
            'chooseCharacters',
            clienttranslate($message),
            array_merge(['gameData' => $results, 'playerId' => $playerId], $selectedCharactersArgs)
        );
        $this->game->markChanged('token');

        // Deactivate player, and move to next state if none are active
        $this->game->gamestate->setPlayerNonMultiactive($playerId, 'initializeTile');
    }
    public function argSelectionCount(): array
    {
        $result = ['actions' => [], 'selectionCount' => $this->game->gameData->get('characterCount')];
        $this->game->getAllPlayers($result);
        return $result;
    }
    public function actUnBack(): void
    {
        $playerId = $this->game->getCurrentPlayer();
        // Deactivate player, and move to next state if none are active
        $this->game->gamestate->setPlayersMultiactive([$playerId], 'initializeTile');
    }
    public function test_swapCharacter($character)
    {
        $oldChar = $this->game->character->getTurnCharacterId();
        $playerId = $this->game->getCurrentPlayer();
        // Remove player's previous selected
        $this->game::DbQuery('DELETE FROM `character` WHERE character_id = "' . $oldChar . '"');
        // Add player's current selected
        $data = $this->game->data->getCharacters()[$character];
        $fatigue = $data['fatigue'];
        $actions = $data['actions'];
        $char = $this->game::escapeStringForDB($character);
        $this->game::DbQuery(
            "INSERT INTO `character` (`character_id`, `player_id`, `actions`, `fatigue`) VALUES ('$char', $playerId, $actions, $fatigue)"
        );
        $turnOrder = $this->game->gameData->get('turnOrder');
        $this->game->gameData->set(
            'turnOrder',
            array_map(function ($charId) use ($oldChar, $character) {
                return $charId == $oldChar ? $character : $charId;
            }, $turnOrder)
        );
        // if (array_key_exists('startsWith', $data)) {
        //     $itemId = $this->game->gameData->createItem($data['startsWith']);
        //     $this->game->character->equipEquipment($character, [$itemId]);
        // }
        $this->game->hooks->onCharacterChoose($data);
    }
}
