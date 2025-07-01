<?php
declare(strict_types=1);

namespace Bga\Games\DeadMenTaleNoTales;

use BgaUserException;
use Error;
use Exception;

class DMTNT_Undo
{
    private Game $game;
    private array $initialState;
    private array $extraTablesList = ['stats', 'nightevent', 'dayevent', 'physicalhindrance', 'mentalhindrance'];
    private ?int $savedMoveId = null;
    private bool $actionWasCleared = false;
    public function __construct(Game $game)
    {
        $this->game = $game;
    }

    public function actUndo(): void
    {
        if ($this->game->gamestate->state(true, false, true)['name'] != 'playerTurn') {
            throw new BgaUserException(clienttranslate('Only player actions can be undone'));
        }
        if (!$this->canUndo()) {
            throw new BgaUserException(clienttranslate('Nothing left to undo, dice rolls, and deck pulls clear the undo history'));
        }
        $char = $this->game->character->getTurnCharacterId();
        $undoState = $this->game->getFromDB(
            'SELECT * FROM `undoState` a INNER JOIN (SELECT max(undo_id) max_last_id FROM `undoState`) b WHERE b.max_last_id = a.undo_id'
        );
        $storedCharacterId = $undoState['character_name'];
        if ($char != $storedCharacterId) {
            throw new BgaUserException(clienttranslate('Can\'t undo another player\'s action'));
        }
        $undoId = $undoState['undo_id'];
        $itemTable = json_decode($undoState['itemTable'], true);
        $characterTable = json_decode($undoState['characterTable'], true);
        $globalsTable = json_decode($undoState['globalsTable'], true);
        $extraTables = json_decode($undoState['extraTables'], true);
        foreach ($characterTable as $k => $v) {
            $this->game->character->_updateCharacterData($v['id'], $v);
        }
        $this->resetNotifications($undoState['gamelog_move_id']);
        foreach ($globalsTable as $k => $v) {
            $this->game->gameData->set($k, $v);
        }
        $this->game->gameData->setItems($itemTable);
        foreach ($this->extraTablesList as $table) {
            $this->game::DbQuery("DELETE FROM `$table` where 1 = 1");
            if (sizeof($extraTables[$table]) > 0) {
                $this->game::DbQuery(buildInsertQuery($table, $extraTables[$table]));
            }
        }
        $this->game->markChanged('token');
        $this->game->markChanged('player');
        $this->game->markChanged('knowledge');
        $this->game->markChanged('actions');
        $this->game::DbQuery("DELETE FROM `undoState` where pending OR undo_id = $undoId");
        $currentState = $this->game->gamestate->state(true, false, true)['name'];
        $this->game->nextState('undo');
        $this->game->nextState($currentState);
        $this->game->completeAction(false);
    }

    public function getLastMoveId(): int
    {
        $data = $this->game->getFromDB('SELECT max(gamelog_move_id) as last_move_id FROM `gamelog`');
        return array_key_exists('last_move_id', $data) ? (int) $data['last_move_id'] : 0;
    }
    public function resetNotifications($moveId): void
    {
        $this->game::DbQuery("DELETE FROM `gamelog` WHERE gamelog_move_id > $moveId");
        $this->game->notify('resetNotifications', '', ['moveId' => $moveId]);
    }

    public function loadInitialState(): void
    {
        $moveId = $this->getLastMoveId();
        $itemsData = json_encode($this->game->gameData->getCreatedItems());
        $globalsData = json_encode($this->game->gameData->getAll());
        $characterData = [];
        try {
            $characterData = json_encode($this->game->character->getAllCharacterData());
        } catch (Exception $e) {
        }
        $extraTablesData = [];
        foreach ($this->extraTablesList as $table) {
            $extraTablesData[$table] = array_values($this->game->getCollectionFromDB("select * from $table"));
        }
        $extraTables = json_encode($extraTablesData);
        $stateName = '';
        try {
            $stateName = $this->game->gamestate->state(true, false, true)['name'];
        } catch (Exception $e) {
        }
        $this->initialState = [
            'moveId' => $moveId,
            'itemsData' => $itemsData,
            'characterData' => $characterData,
            'globalsData' => $globalsData,
            'extraTables' => $extraTables,
            'stateName' => $stateName,
        ];
    }
    public function saveState(): void
    {
        $char = $this->game->character->getTurnCharacterId();
        if (
            !$this->actionWasCleared &&
            $this->initialState &&
            $this->initialState['stateName'] == 'playerTurn' &&
            $char == $this->game->character->getSubmittingCharacterId()
        ) {
            if ($this->savedMoveId != null) {
                $this->game::DbQuery('DELETE FROM `undoState` where pending OR gamelog_move_id=' . $this->savedMoveId);
            }
            $moveId = $this->initialState['moveId'];
            $itemsData = $this->game::escapeStringForDB($this->initialState['itemsData']);
            $characterData = $this->game::escapeStringForDB($this->initialState['characterData']);
            $globalsData = $this->game::escapeStringForDB($this->initialState['globalsData']);
            $extraTables = $this->game::escapeStringForDB($this->initialState['extraTables']);
            $this->savedMoveId = $moveId;

            $pending = 'false';
            if ($this->game->gamestate->state(true, false, true)['name'] != 'playerTurn') {
                $pending = 'true';
            }
            $this->game::DbQuery(
                'INSERT INTO `undoState` (`character_name`, `gamelog_move_id`, `pending`, `itemTable`, `characterTable`, `globalsTable`, `extraTables`) VALUES ' .
                    "('$char', $moveId, $pending, '$itemsData', '$characterData', '$globalsData', '$extraTables')"
            );
        }
        if (
            !$this->actionWasCleared &&
            $this->initialState &&
            $this->initialState['stateName'] != 'playerTurn' &&
            $char == $this->game->character->getSubmittingCharacterId()
        ) {
            if ($this->game->gamestate->state(true, false, true)['name'] == 'playerTurn') {
                $this->game::DbQuery('UPDATE `undoState` SET pending=false WHERE pending=true');
            }
        }
    }

    public function clearUndoHistory(): void
    {
        $this->game::DbQuery('DELETE FROM `undoState` WHERE pending OR undo_id > 0');
        $this->actionWasCleared = true;
    }
    public function canUndo(): bool
    {
        return $this->game->getFromDB('SELECT count(1) as `count` FROM `undoState` WHERE NOT pending', true)['count'] > 0;
    }
}
