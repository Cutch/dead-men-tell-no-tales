<?php
declare(strict_types=1);

namespace Bga\Games\DeadMenTellNoTales;

use BgaUserException;
use Exception;

class DMTNT_Character
{
    private Game $game;
    private ?string $submittingCharacter = null;
    private array $cachedData = [];
    private static array $characterColumns = [
        'character_id',
        'player_id',
        'necromancer_player_id',
        'item',
        'actions',
        'fatigue',
        'tempStrength',
    ];

    public function __construct($game)
    {
        $this->game = $game;
    }
    public function addExtraTime(?int $extraTime = null)
    {
        $this->game->giveExtraTime($this->getTurnCharacter()['playerId'], $extraTime);
    }

    public function clearCache()
    {
        $this->cachedData = [];
    }
    public function _updateCharacterData(string $name, array $data)
    {
        // Update db
        if (array_key_exists('item', $data)) {
            $data['item'] = is_array($data['item']) ? $data['item']['id'] : $data['item'];
        }
        $data['fatigue'] = clamp($data['fatigue'], 0, $data['maxFatigue']);
        $data['actions'] =
            clamp($data['actions'], 0, $data['maxActions']) - ($data['isActive'] ? $this->game->gameData->get('tempActions') : 0);
        $values = [];
        foreach ($data as $key => $value) {
            if (in_array($key, self::$characterColumns)) {
                $sqlValue = $value ? "'{$value}'" : 'NULL';
                $values[] = "`{$key}` = {$sqlValue}";
                $this->cachedData[$name][$key] = $value;
            }
        }
        $values = implode(',', $values);
        $this->game::DbQuery("UPDATE `character` SET {$values} WHERE character_id = '$name'");
        $this->game->markChanged('player');

        if (
            array_key_exists('becameIncapacitated', $data) &&
            $data['becameIncapacitated'] &&
            $data['isActive'] &&
            $this->game->gamestate->state(true, false, true)['name'] == 'playerTurn'
        ) {
            $this->game->endTurn();
        }
    }
    public function updateCharacterData(string $name, $callback)
    {
        // Pull from db if needed
        $data = $this->getCharacterData($name, false);
        if (!$callback($data)) {
            $this->_updateCharacterData($name, $data);
        }
    }
    public function updateAllCharacterData($callback)
    {
        $turnOrder = $this->getAllCharacterIds();
        foreach ($turnOrder as $name) {
            // Pull from db if needed
            $data = $this->getCharacterData($name, false);
            if (!$callback($data)) {
                $this->_updateCharacterData($name, $data);
            }
        }
    }
    public function unZombiePlayer($playerId): void
    {
        $this->game::DbQuery("UPDATE `player` SET player_zombie='0' WHERE player_id=$playerId");
        $this->game::DbQuery("UPDATE `character` SET necromancer_player_id=NULL WHERE player_id=$playerId");
    }
    public function getAllCharacterIds(): array
    {
        $turnOrder = $this->game->gameData->get('turnOrder');
        return array_values(array_filter($turnOrder));
    }
    public function getAllCharacterData(bool $_skipHooks = false): array
    {
        $turnOrder = $this->getAllCharacterIds();
        return array_map(function ($char) use ($_skipHooks) {
            return $this->getCharacterData($char, $_skipHooks);
        }, $turnOrder);
    }
    public function getAllCharacterDataForPlayer(string|int $playerId): array
    {
        return array_values(
            array_filter($this->getAllCharacterData(), function ($char) use ($playerId) {
                return (int) $char['playerId'] == (int) $playerId;
            })
        );
    }
    public function assignNecromancer(string|int $playerId, $characterId): void
    {
        $this->updateCharacterData($characterId, function (&$char) use ($playerId) {
            $char['necromancer_player_id'] = $playerId;
            $char['playerId'] = $playerId;
        });
    }
    public function getCalculatedData(array $characterData, bool $_skipHooks = false): array
    {
        extract($this->game->gameData->getAll('turnNo', 'turnOrder'));
        $turnOrder = array_values(array_filter($turnOrder));
        $characterId = $characterData['character_id'];
        $isActive = $turnOrder[$turnNo ?? 0] == $characterId;
        $characterData['isActive'] = $isActive;
        $characterData['isFirst'] = array_key_exists(0, $turnOrder) && $turnOrder[0] == $characterId;
        $characterData['id'] = $characterId;
        $underlyingCharacterData = $this->game->data->getCharacters()[$characterData['id']];
        $characterData['maxActions'] =
            $underlyingCharacterData['actions'] + ($characterData['isActive'] ? $this->game->gameData->get('tempActions') : 0);
        $characterData['maxFatigue'] = 16;
        $characterData['pos'] = $this->game->getCharacterPos($characterId);

        array_walk($underlyingCharacterData, function ($v, $k) use (&$characterData) {
            if (str_starts_with($k, 'on') || in_array($k, ['skills']) || $k == 'name' || $k == 'color') {
                $characterData[$k] = $v;
            }
        });
        $itemId = $characterData['item'];
        if ($itemId) {
            $skills = [];
            if (array_key_exists('skills', $this->game->data->getItems()[$itemId])) {
                array_walk($this->game->data->getItems()[$itemId]['skills'], function ($v, $k) use ($itemId, $characterId, &$skills) {
                    $skillId = $k . '_' . $itemId;
                    $v['id'] = $skillId;
                    $v['itemId'] = $itemId;
                    $v['itemName'] = $this->game->data->getItems()[$itemId]['name'];
                    $v['characterId'] = $characterId;
                    $skills[$skillId] = $v;
                });
            }

            $characterData['item'] = [
                'itemId' => $itemId,
                'isActive' => $isActive,
                ...$this->game->data->getItems()[$itemId],
                'skills' => $skills,
                'character_id' => $characterId,
                'characterId' => $characterId,
            ];
        } else {
            $characterData['item'] = null;
        }
        $tokenItems = $this->game->gameData->get('tokenItems');
        if (array_key_exists($characterId, $tokenItems)) {
            $characterData['tokenItems'] = $tokenItems[$characterId];
        } else {
            $characterData['tokenItems'] = [];
        }

        if (!$_skipHooks) {
            $this->game->hooks->onGetCharacterData($characterData);
        }
        $characterData['fatigue'] = clamp($characterData['fatigue'], 0, $characterData['maxFatigue']);
        $characterData['actions'] =
            clamp($characterData['actions'], 0, $characterData['maxActions']) +
            ($characterData['isActive'] ? $this->game->gameData->get('tempActions') : 0);
        $characterData['playerId'] = $characterData['player_id'];

        if (
            $characterData['player_zombie'] &&
            array_key_exists('necromancer_player_id', $characterData) &&
            $characterData['necromancer_player_id']
        ) {
            $characterData['playerId'] = $characterData['necromancer_player_id'];
        }

        return $characterData;
    }
    public function swapToCharacter(string $fromCharacterId, string $toCharacterId)
    {
        $items = [...$this->game->getUnequippedItems()];
        $turnOrder = $this->game->gameData->get('turnOrder');
        $this->game->gameData->set(
            'turnOrder',
            array_map(function ($t) use ($fromCharacterId, $toCharacterId) {
                return $t == $fromCharacterId ? $toCharacterId : $t;
            }, $turnOrder)
        );

        $fromCharacterId = $this->game::escapeStringForDB($fromCharacterId);
        $toCharacterId = $this->game::escapeStringForDB($toCharacterId);
        shuffle($items);
        $item = $items[0];
        $this->game::DbQuery(
            "UPDATE `character` SET `character_id` = '$toCharacterId', `item` = '$item', `fatigue` = 0, `tempStrength` = 0 WHERE `character_id` = '$fromCharacterId'"
        );
    }
    public function getCharacterData(string $name, $_skipHooks = false): array
    {
        if (array_key_exists($name, $this->cachedData)) {
            return $this->getCalculatedData($this->cachedData[$name], $_skipHooks);
        } else {
            $this->cachedData[$name] = $this->game->getCollectionFromDb(
                "SELECT c.*, player_color, player_zombie FROM `character` c INNER JOIN `player` p ON p.player_id = c.player_id WHERE character_id = '$name'"
            )[$name];
            return $this->getCalculatedData($this->cachedData[$name], $_skipHooks);
        }
    }
    public function setCharacterItem(string $characterId, array $item): void
    {
        $this->updateCharacterData($characterId, function (&$data) use ($item) {
            $data['item'] = $item;
        });
    }
    public function setSubmittingCharacter(?string $actions, ?string $subActions = null): void
    {
        if ($actions == 'actUseSkill') {
            $skillData = $this->getSkill($subActions);
            if ($skillData && !array_key_exists('global', $skillData['skill'])) {
                $this->submittingCharacter = $this->getSkill($subActions)['character']['id'];
            } else {
                $this->submittingCharacter = null;
            }
        } elseif ($actions == 'actUseItem') {
            $skillData = $this->getSkill($subActions);
            if ($skillData && array_key_exists('character', $skillData)) {
                $this->submittingCharacter = $skillData['character']['id'];
            } else {
                $this->submittingCharacter = null;
            }
        } elseif ($actions == null) {
            $this->submittingCharacter = null;
        }
        $this->game->log('submittingCharacter: ', $actions, $subActions, $this->submittingCharacter);
    }
    public function setSubmittingCharacterById(?string $characterId): void
    {
        $this->submittingCharacter = $characterId;
    }
    public function getSkill(string $skillId): ?array
    {
        $characters = $this->getAllCharacterData(true);
        foreach ($characters as $k => $v) {
            if (array_key_exists('skills', $v)) {
                if (array_key_exists($skillId, $v['skills'])) {
                    return ['character' => $v, 'skill' => $v['skills'][$skillId]];
                }
            }
        }
        return null;
    }
    public function getSubmittingCharacterId(): ?string
    {
        return $this->submittingCharacter ? $this->submittingCharacter : $this->getTurnCharacterId();
    }
    public function getSubmittingCharacter(bool $_skipHooks = false): array
    {
        return $this->submittingCharacter
            ? $this->getCharacterData($this->submittingCharacter, $_skipHooks)
            : $this->getTurnCharacter($_skipHooks);
    }
    public function getTurnCharacterId(): ?string
    {
        extract($this->game->gameData->getAll('turnNo', 'turnOrder'));
        return $turnOrder[$turnNo ?? 0];
    }
    public function getTurnCharacter(bool $_skipHooks = false): array
    {
        return $this->getCharacterData($this->getTurnCharacterId(), $_skipHooks);
    }
    public function getActiveItem(): array
    {
        $character = $this->getSubmittingCharacter();
        return $character['item'];
    }
    public function activateNextCharacter(): void
    {
        // Making the assumption that the functions are checking isLastCharacter()
        extract($this->game->gameData->getAll('turnNo', 'turnOrder'));
        if ($turnNo === null) {
            $turnNo = -1;
        }
        $this->game->gameData->set('turnNo', ($turnNo + 1) % sizeof($turnOrder));
        $character = $turnOrder[($turnNo + 1) % sizeof($turnOrder)];
        if ($turnNo + 1 === sizeof($turnOrder)) {
            $this->game->gameData->set('round', $this->game->gameData->get('round') + 1);
        }
        $turnNo = ($turnNo + 1) % sizeof($turnOrder);
        $characterData = $this->getCharacterData($character);

        $playerId = (int) $this->game->getActivePlayerId();
        if ($playerId != $characterData['playerId']) {
            $this->game->gamestate->changeActivePlayer($characterData['playerId']);
            $this->addExtraTime();
        }
        $this->game->markChanged('player');
    }
    public function getActiveActions(): int
    {
        return (int) $this->getSubmittingCharacter()['actions'];
    }
    public function _adjustActions(array &$data, int $actionChange, &$prev, $characterId): bool
    {
        $prev = $data['actions'];
        $hookData = [
            'currentActions' => $prev,
            'change' => $actionChange,
            'characterId' => $characterId,
            'maxActions' => $data['maxActions'],
        ];
        $this->game->hooks->onAdjustActions($hookData);
        $tempActions = $this->game->gameData->get('tempActions');
        if ($tempActions > 0 && $hookData['change'] < 0) {
            $newTempActions = max($tempActions + $hookData['change'], 0);
            $this->game->gameData->set('tempActions', $newTempActions);

            $hookData['change'] -= $newTempActions - $tempActions;
        }

        $data['actions'] = clamp($data['actions'] + $hookData['change'], 0, $data['maxActions']);
        $prev = $data['actions'] - $prev;
        return $prev == 0;
    }
    public function adjustActions(string $characterId, int $actionChange): int
    {
        $prev = 0;
        $this->updateCharacterData($characterId, function (&$data) use ($actionChange, &$prev, $characterId) {
            return $this->_adjustActions($data, $actionChange, $prev, $characterId);
        });
        return $prev;
    }
    public function adjustActiveActions(int $actions): int
    {
        $characterId = $this->getSubmittingCharacter()['character_id'];
        return $this->adjustActions($characterId, $actions);
    }
    public function getActiveFatigue(): int
    {
        return (int) $this->getSubmittingCharacter()['fatigue'];
    }

    public function _adjustFatigue(array &$data, $fatigueChange, &$prev, $characterId): bool
    {
        $prev = $data['fatigue'];
        $hookData = [
            'currentFatigue' => $prev,
            'change' => $fatigueChange,
            'characterId' => $characterId,
            'maxFatigue' => $data['maxFatigue'],
        ];
        $this->game->hooks->onAdjustFatigue($hookData);
        $data['fatigue'] = clamp($data['fatigue'] + $hookData['change'], 0, $data['maxFatigue']);
        $prev = $data['fatigue'] - $prev;

        if ($prev > 0) {
            $this->game->incStat($prev, 'fatigue_gained', $this->getCharacterData($characterId)['playerId']);
        }

        if ($data['fatigue'] == $data['maxFatigue']) {
            $this->game->eventLog(clienttranslate('${character_name} has died'), [
                'character_id' => $this->game->getCharacterHTML($characterId),
            ]);
            $data['actions'] = 0;
            $hookData = [
                'characterId' => $characterId,
            ];
            $this->game->hooks->onDeath($hookData);
            return false;
        } else {
            return $prev == 0;
        }
    }
    public function adjustFatigue(string $characterId, int $fatigueChange): int
    {
        $prev = 0;
        $this->updateCharacterData($characterId, function (&$data) use ($fatigueChange, &$prev, $characterId) {
            return $this->_adjustFatigue($data, $fatigueChange, $prev, $characterId);
        });
        return $prev;
    }
    public function adjustActiveFatigue(int $fatigue): int
    {
        $characterId = $this->getSubmittingCharacter()['character_id'];
        return $this->adjustFatigue($characterId, $fatigue);
    }
    public function getMarshallCharacters()
    {
        return array_map(function ($char) {
            return [
                'id' => $char['character_id'],
                'isFirst' => $char['isFirst'],
                'isActive' => $char['isActive'],
                'item' => $char['item'],
                'playerColor' => $char['player_color'],
                'characterColor' => $char['color'],
                'playerId' => $char['playerId'],
                'actions' => $char['actions'],
                'maxActions' => $char['maxActions'],
                'maxFatigue' => $char['maxFatigue'],
                'tempActions' => $char['isActive'] ? $this->game->gameData->get('tempActions') : 0,
                'fatigue' => $char['fatigue'],
                'pos' => $char['pos'],
                'tempStrength' => $char['tempStrength'],
                'tokenItems' => $char['tokenItems'],
            ];
        }, $this->getAllCharacterData());
    }
    public function clearCharacterSkills(&$skills, ?string $characterId = null)
    {
        array_walk($skills, function ($v, $k) use (&$skills, $characterId) {
            if ($characterId == null || $v['characterId'] == $characterId) {
                unset($skills[$k]);
            }
        });
    }
}
