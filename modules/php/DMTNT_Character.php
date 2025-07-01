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
        'character_name',
        'player_id',
        'necromancer_player_id',
        'item',
        'actions',
        'fatigue',
        'modifiedMaxActions',
        'modifiedMaxFatigue',
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
        for ($i = 0; $i < 3; $i++) {
            $data['item_' . ($i + 1)] = array_key_exists($i, $data['equipment']) ? $data['equipment'][$i] : null;
            if ($data['item_' . ($i + 1)]) {
                $data['item_' . ($i + 1)] = is_array($data['item_' . ($i + 1)])
                    ? (array_key_exists('itemId', $data['item_' . ($i + 1)])
                        ? $data['item_' . ($i + 1)]['itemId']
                        : null)
                    : $data['item_' . ($i + 1)];
            }
        }
        $data['fatigue'] = clamp($data['fatigue'], 0, $data['maxFatigue']);
        $data['actions'] = clamp($data['actions'], 0, $data['maxActions']);
        $values = [];
        foreach ($data as $key => $value) {
            if (in_array($key, self::$characterColumns)) {
                $sqlValue = $value ? "'{$value}'" : 'NULL';
                $values[] = "`{$key}` = {$sqlValue}";
                $this->cachedData[$name][$key] = $value;
            }
        }
        $values = implode(',', $values);
        $this->game::DbQuery("UPDATE `character` SET {$values} WHERE character_name = '$name'");
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
        $turnOrderStart = $this->game->gameData->get('turnOrderStart');
        $turnOrder = sizeof(array_filter($turnOrderStart ?? [])) == 4 ? $turnOrderStart : $this->game->gameData->get('turnOrder');
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
        $characterName = $characterData['character_name'];
        $isActive = $turnOrder[$turnNo ?? 0] == $characterName;
        $characterData['isActive'] = $isActive;
        $characterData['isFirst'] = array_key_exists(0, $turnOrder) && $turnOrder[0] == $characterName;
        $characterData['id'] = $characterName;
        $underlyingCharacterData = $this->game->data->getCharacters()[$characterData['id']];
        $characterData['maxActions'] = $underlyingCharacterData['actions'] + $characterData['modifiedMaxActions'];
        $characterData['maxFatigue'] = $underlyingCharacterData['fatigue'] + $characterData['modifiedMaxFatigue'];

        array_walk($underlyingCharacterData, function ($v, $k) use (&$characterData) {
            if (str_starts_with($k, 'on') || in_array($k, ['slots', 'skills']) || $k == 'getPerDayKey' || $k == 'characterSkillName') {
                $characterData[$k] = $v;
            }
        });

        $characterData['equipment'] = array_map(function ($itemId) use ($isActive, $characterName, $itemsLookup) {
            $itemName = $itemsLookup[$itemId];
            $skills = [];
            if (array_key_exists('skills', $this->game->data->getItems()[$itemName])) {
                array_walk($this->game->data->getItems()[$itemName]['skills'], function ($v, $k) use (
                    $itemId,
                    $itemName,
                    $characterName,
                    &$skills
                ) {
                    $skillId = $k . '_' . $itemId;
                    $v['id'] = $skillId;
                    $v['itemId'] = $itemId;
                    $v['itemName'] = $itemName;
                    $v['characterId'] = $characterName;
                    $skills[$skillId] = $v;
                });
            }

            return [
                'itemId' => $itemId,
                'isActive' => $isActive,
                ...$this->game->data->getItems()[$itemName],
                'skills' => $skills,
                'character_name' => $characterName,
                'characterId' => $characterName,
            ];
        }, array_values(array_filter([$characterData['item_1'], $characterData['item_2'], $characterData['item_3']])));
        if (!$_skipHooks) {
            $this->game->hooks->onGetCharacterData($characterData);
        }
        $characterData['maxActions'] = clamp($characterData['maxActions'], 0, 10);
        $characterData['maxFatigue'] = clamp($characterData['maxFatigue'], 0, 10);
        $characterData['fatigue'] = clamp($characterData['fatigue'], 0, $characterData['maxFatigue']);
        $characterData['actions'] = clamp($characterData['actions'], 0, $characterData['maxActions']);
        $characterData['playerId'] = $characterData['player_id'];
        $characterData['incapacitated'] = !!$characterData['incapacitated'];
        $characterData['recovering'] = $characterData['fatigue'] > 0 && $characterData['incapacitated'];

        if (
            $characterData['player_zombie'] &&
            array_key_exists('necromancer_player_id', $characterData) &&
            $characterData['necromancer_player_id']
        ) {
            $characterData['playerId'] = $characterData['necromancer_player_id'];
        }

        return $characterData;
    }
    public function getCharacterData(string $name, $_skipHooks = false): array
    {
        if (array_key_exists($name, $this->cachedData)) {
            return $this->getCalculatedData($this->cachedData[$name], $_skipHooks);
        } else {
            $this->cachedData[$name] = $this->game->getCollectionFromDb(
                "SELECT c.*, player_color, player_zombie FROM `character` c INNER JOIN `player` p ON p.player_id = c.player_id WHERE character_name = '$name'"
            )[$name];
            return $this->getCalculatedData($this->cachedData[$name], $_skipHooks);
        }
    }
    public function getItemValidations(int $itemId, array $character, ?int $removingItemId = null)
    {
        $items = $this->game->gameData->getCreatedItems();
        $item = $items[$itemId];
        $itemName = $this->game->data->getItems()[$item]['id'];
        $itemType = $this->game->data->getItems()[$item]['itemType'];
        $this->game->hooks->onGetSlots($character);
        $result = [
            'character' => $character,
            'item' => $this->game->data->getItems()[$item],
            'canEquip' => true,
        ];
        $this->game->hooks->onGetItemValidation($result);
        $slotsAllowed = $character['slots'];
        $equipment = array_values(
            array_filter($character['equipment'], function ($d) use ($removingItemId) {
                return $d['itemId'] != $removingItemId;
            })
        );
        $slotsUsed = array_count_values(
            array_map(function ($d) {
                return $d['itemType'];
            }, $equipment)
        );
        $hasOpenSlots =
            (array_key_exists($itemType, $slotsAllowed) ? $slotsAllowed[$itemType] : 0) -
                (array_key_exists($itemType, $slotsUsed) ? $slotsUsed[$itemType] : 0) >
            0;
        $hasDuplicateTool =
            sizeof(
                array_filter($equipment, function ($d) use ($itemName) {
                    return $d['id'] == $itemName;
                })
            ) > 0;
        return ['hasOpenSlots' => $hasOpenSlots, 'hasDuplicateTool' => $hasDuplicateTool, 'canEquip' => $result['canEquip']];
    }
    public function equipAndValidateEquipment(string $characterId, int $itemId)
    {
        $character = $this->getCharacterData($characterId);
        $itemsLookup = $this->game->gameData->getCreatedItems();
        $itemName = $itemsLookup[$itemId];
        $itemObj = $this->game->data->getItems()[$itemName];
        $itemType = $itemObj['itemType'];

        $result = $this->getItemValidations((int) $itemId, $character);
        $canEquip = $result['canEquip'];
        $hasOpenSlots = $result['hasOpenSlots'];
        $hasDuplicateTool = $result['hasDuplicateTool'];
        if ($hasOpenSlots && !$hasDuplicateTool && $canEquip) {
            $this->equipEquipment($character['id'], [$itemId]);
        } else {
            $existingItems = array_map(
                function ($d) {
                    return ['name' => $d['id'], 'itemId' => $d['itemId']];
                },
                array_filter($character['equipment'], function ($d) use ($itemType, $hasDuplicateTool, $itemName) {
                    if ($hasDuplicateTool) {
                        return $d['id'] == $itemName;
                    } else {
                        return $d['itemType'] == $itemType;
                    }
                })
            );
            $this->game->selectionStates->initiateState(
                'tooManyItems',
                [
                    'characterId' => $character['id'],
                    'itemType' => $itemType,
                    'items' => [...$existingItems, ['name' => $itemName, 'itemId' => $itemId]],
                ],
                $character['id'],
                false
            );
        }
    }
    public function equipEquipment(string $characterName, array $items): void
    {
        $this->updateCharacterData($characterName, function (&$data) use ($items) {
            $equippedIds = array_map(function ($d) {
                return $d['itemId'];
            }, $data['equipment']);
            $equipment = [...$equippedIds, ...$items];
            $data['equipment'] = $equipment;
        });
        $this->updateItemLastOwner($characterName, $items);
    }
    public function unequipEquipment(string $characterName, array $items, bool $sendToCamp = false): void
    {
        $this->updateCharacterData($characterName, function (&$data) use ($items, $sendToCamp) {
            $equippedIds = array_map(function ($d) {
                return $d['itemId'];
            }, $data['equipment']);
            $equipment = array_diff($equippedIds, array_intersect($equippedIds, $items));
            $data['equipment'] = $equipment;
            if ($sendToCamp) {
                $this->game->gameData->set('campEquipment', [...$this->game->gameData->get('campEquipment'), ...$items]);
            }
        });
    }
    public function setCharacterEquipment(string $characterName, array $equipment): void
    {
        $this->updateCharacterData($characterName, function (&$data) use ($equipment) {
            $data['equipment'] = $equipment;
        });
        $this->updateItemLastOwner($characterName, $equipment);
    }
    public function updateItemLastOwner(string $characterName, array $equipment): void
    {
        $lastItemOwners = $this->game->gameData->get('lastItemOwners');
        foreach ($equipment as $id) {
            $lastItemOwners[$id] = $characterName;
        }
        $this->game->gameData->set('lastItemOwners', $lastItemOwners);
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
        $currentCharacter = $this->getTurnCharacter(true);
        foreach ($characters as $k => $v) {
            if (array_key_exists('skills', $v)) {
                if (array_key_exists($skillId, $v['skills'])) {
                    return ['character' => $v, 'skill' => $v['skills'][$skillId]];
                }
            }
        }
        // foreach ($this->game->actions->getActionss() as $k => $actions) {
        //     if (array_key_exists('skills', $actions)) {
        //         if (array_key_exists($skillId, $actions['skills'])) {
        //             return ['character' => $currentCharacter, 'skill' => $actions['skills'][$skillId]];
        //         }
        //     }
        // }
        return null;
    }
    // public function getItem($itemId): ?array
    // {
    //     $characters = $this->getAllCharacterData(true);
    //     foreach ($characters as $k => $v) {
    //         $array = array_values(
    //             array_filter($v['equipment'], function ($item) use ($itemId) {
    //                 return $item['itemId'] == $itemId;
    //             })
    //         );
    //         if (sizeof($array) > 0) {
    //             return ['character' => $v, 'item' => $$array[0]];
    //         }
    //     }
    //     return null;
    // }
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
        if (sizeof($turnOrder) == 4) {
            return $turnOrder[$turnNo ?? 0];
        } else {
            return null;
        }
    }
    public function getTurnCharacter(bool $_skipHooks = false): array
    {
        return $this->getCharacterData($this->getTurnCharacterId(), $_skipHooks);
    }
    public function getActiveEquipment(): array
    {
        $character = $this->getSubmittingCharacter();
        return $character['equipment'];
    }
    public function activateNextCharacter(): void
    {
        // Making the assumption that the functions are checking isLastCharacter()
        extract($this->game->gameData->getAll('turnNo', 'turnOrder'));
        if ($turnNo !== null) {
            $this->game->gameData->set('turnNo', $turnNo + 1);
            $character = $turnOrder[$turnNo + 1];
            $turnNo = $turnNo + 1;
        } else {
            $this->game->gameData->set('turnNo', 0);
            $character = $turnOrder[0];
            $turnNo = 0;
        }
        $characterData = $this->getCharacterData($character);

        $playerId = (int) $this->game->getActivePlayerId();
        if ($playerId != $characterData['playerId']) {
            $this->game->gamestate->changeActivePlayer($characterData['playerId']);
            $this->addExtraTime();
        }
        $this->game->markChanged('player');
    }
    public function getFirstCharacter(): string
    {
        $turnOrder = $this->game->gameData->get('turnOrder');
        return $turnOrder[0];
    }
    public function isLastCharacter(): bool
    {
        extract($this->game->gameData->getAll('turnNo', 'turnOrder'));
        return sizeof($turnOrder) == ($turnNo ?? 0) + 1;
    }
    public function setFirstTurnOrder(string $characterId): void
    {
        $turnOrder = $this->game->gameData->get('turnOrder');
        $i = array_search($characterId, $turnOrder);
        $endArray = array_slice($turnOrder, 0, $i);
        $startArray = array_slice($turnOrder, $i, sizeof($turnOrder) - $i);
        $this->game->gameData->set('turnOrder', [...$startArray, ...$endArray]);
        $this->game->gameData->set('turnNo', null);
        $this->game->markChanged('player');
    }
    public function rotateTurnOrder(): void
    {
        $turnOrder = $this->game->gameData->get('turnOrder');
        $temp = array_shift($turnOrder);
        array_push($turnOrder, $temp);
        $this->game->gameData->set('turnOrder', $turnOrder);
        $this->game->gameData->set('turnNo', null);
        $this->game->markChanged('player');
    }

    public function getActiveActions(): int
    {
        return (int) $this->getSubmittingCharacter()['actions'];
    }
    public function _adjustActions(array &$data, int $actionChange, &$prev, $characterName): bool
    {
        $prev = $data['actions'];
        $hookData = [
            'currentActions' => $prev,
            'change' => $actionChange,
            'characterId' => $characterName,
            'maxActions' => $data['maxActions'],
        ];
        $this->game->hooks->onAdjustActions($hookData);
        $data['actions'] = clamp($data['actions'] + $hookData['change'], 0, $data['maxActions']);
        $prev = $data['actions'] - $prev;
        return $prev == 0;
    }
    public function adjustAllActions(int $actionChange): void
    {
        $prev = 0;
        $this->updateAllCharacterData(function (&$data) use ($actionChange, &$prev) {
            return $this->_adjustActions($data, $actionChange, $prev, $data['id']);
        });
    }
    public function adjustActions(string $characterName, int $actionChange): int
    {
        $prev = 0;
        $this->updateCharacterData($characterName, function (&$data) use ($actionChange, &$prev, $characterName) {
            return $this->_adjustActions($data, $actionChange, $prev, $characterName);
        });
        return $prev;
    }
    public function adjustActiveActions(int $actions): int
    {
        $characterName = $this->getSubmittingCharacter()['character_name'];
        return $this->adjustActions($characterName, $actions);
    }
    public function getActiveFatigue(): int
    {
        return (int) $this->getSubmittingCharacter()['fatigue'];
    }

    public function _adjustFatigue(array &$data, $fatigueChange, &$prev, $characterName): bool
    {
        if ($data['incapacitated'] && !$data['recovering'] && $fatigueChange > 0) {
            return true;
        }
        if ($data['recovering'] && $fatigueChange < 0) {
            return true;
        }
        $prev = $data['fatigue'];
        $hookData = [
            'currentFatigue' => $prev,
            'change' => $fatigueChange,
            'characterId' => $characterName,
            'maxFatigue' => $data['maxFatigue'],
        ];
        $this->game->hooks->onAdjustFatigue($hookData);
        $data['fatigue'] = clamp($data['fatigue'] + $hookData['change'], 0, $data['maxFatigue']);
        $prev = $data['fatigue'] - $prev;

        if ($prev < 0) {
            $this->game->incStat(-$prev, 'fatigue_lost', $this->getCharacterData($characterName)['playerId']);
        }
        if ($prev > 0) {
            $this->game->incStat($prev, 'fatigue_gained', $this->getCharacterData($characterName)['playerId']);
        }

        if ($data['fatigue'] == 0 && !$data['incapacitated']) {
            $this->game->eventLog(clienttranslate('${character_name} is incapacitated'), [
                'character_name' => $this->game->getCharacterHTML($characterName),
            ]);
            $data['incapacitated'] = true;
            $data['becameIncapacitated'] = true;
            $data['actions'] = 0;
            $hookData = [
                'characterId' => $characterName,
            ];
            $this->game->hooks->onIncapacitation($hookData);
            return false;
        } else {
            return $prev == 0;
        }
    }
    public function adjustAllFatigue(int $fatigueChange): void
    {
        $prev = 0;
        $this->updateAllCharacterData(function (&$data) use ($fatigueChange, &$prev) {
            return $this->_adjustFatigue($data, $fatigueChange, $prev, $data['id']);
        });
    }
    public function adjustFatigue(string $characterName, int $fatigueChange): int
    {
        $prev = 0;
        $this->updateCharacterData($characterName, function (&$data) use ($fatigueChange, &$prev, $characterName) {
            return $this->_adjustFatigue($data, $fatigueChange, $prev, $characterName);
        });
        return $prev;
    }
    public function adjustActiveFatigue(int $fatigue): int
    {
        $characterName = $this->getSubmittingCharacter()['character_name'];
        return $this->adjustFatigue($characterName, $fatigue);
    }
    public function getMarshallCharacters()
    {
        return array_map(function ($char) {
            $this->game->hooks->onGetSlots($char);
            $slotsAllowed = $char['slots'];
            $slotsUsed = array_count_values(
                array_map(function ($d) {
                    return $d['itemType'];
                }, $char['equipment'])
            );
            return [
                'name' => $char['character_name'],
                'isFirst' => $char['isFirst'],
                'isActive' => $char['isActive'],
                'equipment' => $char['equipment'],
                'playerColor' => $char['player_color'],
                'playerId' => $char['playerId'],
                'actions' => $char['actions'],
                'maxActions' => $char['maxActions'],
                'maxFatigue' => $char['maxFatigue'],
                'dayEvent' => $char['dayEvent'],
                'mentalHindrance' => $char['mentalHindrance'],
                'physicalHindrance' => $char['physicalHindrance'],
                'necklaces' => $char['necklaces'],
                'fatigue' => $char['fatigue'],
                'incapacitated' => $char['incapacitated'],
                'recovering' => $char['recovering'],
                'slotsUsed' => $slotsUsed,
                'slotsAllowed' => $slotsAllowed,
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
