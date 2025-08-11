<?php
declare(strict_types=1);

namespace Bga\Games\DeadMenTellNoTales;

use Bga\GameFramework\Actions\Types\JsonParam;
use BgaUserException;
use Exception;

class DMTNT_SelectionStates
{
    private Game $game;
    private bool $stateChanged = false;
    public function __construct(Game $game)
    {
        $this->game = $game;
    }

    public function completeSelectionState(array $data): void
    {
        $isInterrupt = array_key_exists('isInterrupt', $data) && $data['isInterrupt'];

        $pendingStates = $this->getPendingStates();
        if ((sizeof($pendingStates) == 0 || in_array($data['nextState'], ['nextCharacter', 'playerTurn'])) && $data['nextState']) {
            $this->game->character->setSubmittingCharacterById(null);
            $this->game->nextState($data['nextState']);
        }
        if ($isInterrupt) {
            $this->game->actInterrupt->completeInterrupt();
        }
        $this->initiatePendingState();
    }
    public function actMoveSelection(?int $x, ?int $y): void
    {
        $stateData = $this->getState(null);
        $characterId = $stateData['characterId'];
        if ($stateData['id'] === 'gusMovement') {
        } else {
            $this->game->_actMove('actMoveSelection', $x, $y, $characterId);
        }
        $data = [
            'characterId' => $characterId,
            'nextState' => $stateData['nextState'],
            'isInterrupt' => $stateData['isInterrupt'],
            'x' => $x,
            'y' => $y,
        ];
        $this->game->hooks->onMoveSelection($data);
        $this->completeSelectionState($data);
    }
    public function actMoveCrew(?int $x, ?int $y): void
    {
        if ($x === null || $y === null) {
            throw new BgaUserException(clienttranslate('Select a location'));
        }

        $stateData = $this->getState(null);
        $characterId = $stateData['characterId'];
        $currentPosId = $stateData['currentPosId'];
        $crewTokenId = $stateData['crew']['id'];

        $targetPosId = $this->game->map->xy($x, $y);
        $tokenPositions = $this->game->gameData->get('tokenPositions');
        $token = array_values(
            array_filter(array_merge(...array_values($tokenPositions)), function ($d) use ($crewTokenId) {
                return $d['id'] === $crewTokenId;
            })
        )[0];
        $tokenPositions[$currentPosId] = array_values(
            array_filter($tokenPositions[$currentPosId], function ($d) use ($crewTokenId) {
                return $d['id'] !== $crewTokenId;
            })
        );
        if (!array_key_exists($targetPosId, $tokenPositions)) {
            $tokenPositions[$targetPosId] = [];
        }
        $tokenPositions[$targetPosId][] = $token;
        // $tokenPositions[$currentPosId]
        $this->game->gameData->set('tokenPositions', $tokenPositions);
        $this->game->markChanged('map');
        $data = [
            'characterId' => $characterId,
            'nextState' => $stateData['nextState'],
            'isInterrupt' => $stateData['isInterrupt'],
        ];
        $this->completeSelectionState($data);
        // If this is the first call it will be player turn, otherwise it will use the battle's next state

        if (sizeof($this->getPendingStates()) == 0) {
            if ($this->game->battle->battleLocation('playerTurn') == 0) {
                $this->game->character->activateCharacter($this->game->character->getTurnCharacterId());
                $this->game->nextState('playerTurn');
            }
        }
    }
    public function actSelectItem(?string $itemId = null): void
    {
        if (!$itemId) {
            throw new BgaUserException(clienttranslate('Select an item'));
        }
        $stateData = $this->getState(null);
        $characterId = $stateData['characterId'];
        $took = false;
        $tookCharacterId = '';
        $this->game->character->updateAllCharacterData(function (&$d) use ($characterId, $itemId, &$took, &$tookCharacterId) {
            if ($characterId === $d['id']) {
                $d['item'] = $itemId;
            } elseif ($d['item']['id'] === $itemId) {
                $tookCharacterId = $d['id'];
                $d['item'] = null;
                $took = true;
            }
        });
        if ($stateData['id'] === 'actSwapItem' && array_key_exists('swap', $stateData) && $stateData['swap'] === 'init') {
            $this->game->actions->spendActionCost('actSwapItem');
        }
        if ($took) {
            $this->game->eventLog(clienttranslate('${character_name} took the ${item}'), [
                'usedActionId' => 'actPickupToken',
                'item' => $this->game->data->getItems()[$itemId]['name'],
            ]);
            $items = array_map(function ($d) {
                return ['id' => $d];
            }, $this->game->getUnequippedItems());
            $this->initiateState(
                'itemSelection',
                [
                    'items' => $items,
                    'id' => $stateData['id'],
                ],
                $tookCharacterId,
                false
            );
        } else {
            $this->game->eventLog(clienttranslate('${character_name} picked up ${item}'), [
                'usedActionId' => 'actPickupToken',
                'item' => $this->game->data->getItems()[$itemId]['name'],
                'character_name' => $this->game->getCharacterHTML($characterId),
            ]);
        }
        $data = [
            'itemId' => $itemId,
            'characterId' => $characterId,
            'nextState' => $stateData['nextState'],
            'isInterrupt' => $stateData['isInterrupt'],
        ];
        $this->completeSelectionState($data);
    }
    public function actSelectCard(?string $cardId = null): void
    {
        if (!$cardId) {
            throw new BgaUserException(clienttranslate('Select a card'));
        }
        $stateData = $this->getState(null);
        $characterId = $stateData['characterId'];
        $data = [
            'cardId' => $cardId,
            'characterId' => $characterId,
            'nextState' => $stateData['nextState'],
            'isInterrupt' => $stateData['isInterrupt'],
        ];
        $this->game->hooks->onCardSelection($data);
        $this->completeSelectionState($data);
    }
    public function actSelectCharacter(?string $characterId = null): void
    {
        if (!$characterId) {
            throw new BgaUserException(clienttranslate('Select a character'));
        }
        $stateData = $this->getState(null);
        $currentCharacter = $stateData['currentCharacter'];
        $this->game->character->swapToCharacter($currentCharacter, $characterId);
        $this->game->endTurn();

        $data = [
            'characterId' => $characterId,
            'nextState' => false,
            'isInterrupt' => $stateData['isInterrupt'],
        ];
        $this->completeSelectionState($data);
    }
    public function cancelState(?string $stateName): void
    {
        if ($stateName) {
            $state = $this->game->gameData->get($stateName);
            if (array_key_exists('cancellable', $state) && !$state['cancellable']) {
                throw new BgaUserException(clienttranslate('This action cannot be cancelled'));
            }
            $this->game->gameData->set($stateName, [...$state, 'cancelled' => true]);
        }

        if (!$this->game->actInterrupt->onInterruptCancel()) {
            $this->game->nextState('playerTurn');
        }
        $this->initiatePendingState();
    }
    public function stateToStateNameMapping(?string $stateName = null): ?string
    {
        $stateName = $stateName ?? $this->game->gamestate->state(true, false, true)['name'];
        if ($stateName == 'cardSelection') {
            return 'cardSelectionState';
        } elseif ($stateName == 'crewMovement') {
            return 'crewMovementState';
        } elseif ($stateName == 'characterMovement') {
            return 'characterMovementState';
        } elseif ($stateName == 'itemSelection') {
            return 'itemSelectionState';
        } elseif ($stateName == 'characterSelection') {
            return 'characterSelectionState';
        } elseif ($stateName == 'revengeBattleSelection') {
            return 'revengeBattleSelectionState';
        }
        return null;
    }
    public function argSelectionState(): array
    {
        $stateName = $this->stateToStateNameMapping();
        $state = $this->getState();
        $result = [
            'actions' => [],
            'selectionState' => $this->game->gameData->get($stateName),
            'character_name' => $this->game->getCharacterHTML($state['characterId']),
            'activeTurnPlayerId' => 0,
            'title' => array_key_exists('title', $state) ? $state['title'] : '',
        ];
        // TODO this fixes the bug with day event selections, can be removed later
        if (
            array_key_exists('selectableCharacters', $result['selectionState']) &&
            sizeof($result['selectionState']['selectableCharacters']) > 0 &&
            gettype($result['selectionState']['selectableCharacters'][0]) == 'array'
        ) {
            $temp = $this->game->gameData->get($stateName);
            $temp['selectableCharacters'] = toId($temp['selectableCharacters']);
            $this->game->gameData->set($stateName, $temp);
            $result['selectionState'] = $temp;
        }

        $this->game->getGameData($result);
        if ($stateName === 'deckSelectionState') {
            $this->game->getDecks($result);
        }
        if ($stateName === 'eatSelection') {
            $this->game->getItemData($result);
        }
        return $result;
    }
    public function actCancel(): void
    {
        $stateName = $this->stateToStateNameMapping();
        $this->cancelState($stateName);
    }
    public function getState(?string $stateName = null): array
    {
        $stateNameState = $this->stateToStateNameMapping($stateName);
        return $this->game->gameData->get($stateNameState);
    }
    public function setState(?string $stateName, ?array $data): void
    {
        $stateNameState = $this->stateToStateNameMapping($stateName);
        $this->game->gameData->set($stateNameState, $data);
    }
    public function getPendingStates(): array
    {
        return $this->game->gameData->get('pendingStates') ?? [];
    }
    public function initiatePendingState(): void
    {
        $pendingStates = $this->getPendingStates();
        if (sizeof($pendingStates) > 0) {
            $this->initiateState(...$pendingStates[0]);
            array_shift($pendingStates);
            $this->game->gameData->set('pendingStates', $pendingStates);
        }
        $this->game->completeAction();
    }
    public function initiateState(
        string $stateName,
        array $state,
        string $characterId,
        bool $cancellable = true,
        string $nextState = 'playerTurn',
        ?string $title = null,
        bool $isInterrupt = false,
        bool $isPendingState = false,
        ?bool $setAsNextState = false
    ): void {
        if ($this->stateChanged || $this->stateToStateNameMapping() != null) {
            $pendingStates = $this->getPendingStates();
            // WARNING: Update if args change
            $args = [$stateName, $state, $characterId, $cancellable, $nextState, $title, $isInterrupt, true, $setAsNextState];
            if ($setAsNextState) {
                array_unshift($pendingStates, $args);
            } else {
                array_push($pendingStates, $args);
            }
            $this->game->gameData->set('pendingStates', $pendingStates);
        } else {
            $this->stateChanged = true;
            $stateNameState = $this->stateToStateNameMapping($stateName);

            $playerId = $this->game->getCurrentPlayer();
            $newState = [
                'cancellable' => $cancellable,
                'title' => $title,
                'currentPlayerId' => $playerId,
                'characterId' => $characterId,
                'nextState' => $nextState,
                'isInterrupt' => $isInterrupt,
                'isPendingState' => $isPendingState,
                ...$state,
            ];
            $this->game->gameData->addMultiActiveCharacter($characterId, true);

            $this->game->gameData->set($stateNameState, $newState);
            $this->game->nextState($stateName);
        }
    }
}
