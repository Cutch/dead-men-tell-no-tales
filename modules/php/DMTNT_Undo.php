<?php
declare(strict_types=1);

namespace Bga\Games\DeadMenTellNoTales;

use BgaUserException;
use Error;
use Exception;

class DMTNT_Undo
{
    private Game $game;
    private bool $stateSaved = false;
    private array $initialState;
    private array $extraTablesList = ['stats', 'map'];
    private array $validStates = ['playerTurn', 'battleSelection'];
    private ?int $savedMoveId = null;
    private bool $actionWasCleared = false;
    public function __construct(Game $game)
    {
        $this->game = $game;
    }

    public function actUndo(): void
    {
        if (!$this->canUndo()) {
            throw new BgaUserException(clienttranslate('Nothing left to undo, dice rolls, and deck pulls clear the undo history'));
        }
        $char = $this->game->character->getTurnCharacterId();
        $undoState = $this->game->getFromDB(
            'SELECT * FROM `undoState` a INNER JOIN (SELECT max(undo_id) max_last_id FROM `undoState`) b WHERE b.max_last_id = a.undo_id'
        );
        $storedCharacterId = $undoState['character_id'];
        if ($char != $storedCharacterId) {
            throw new BgaUserException(clienttranslate('Can\'t undo another player\'s action'));
        }
        $undoId = $undoState['undo_id'];
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
        foreach ($this->extraTablesList as $table) {
            $this->game::DbQuery("DELETE FROM `$table` where 1 = 1");
            if (sizeof($extraTables[$table]) > 0) {
                $this->game::DbQuery(buildInsertQuery($table, $extraTables[$table]));
            }
        }
        $this->game->map->reloadCache();
        $this->game->markChanged('token');
        $this->game->markChanged('player');
        $this->game->markChanged('map');
        $this->game->markChanged('actions');
        $this->game::DbQuery("DELETE FROM `undoState` where pending OR undo_id = $undoId");
        $currentState = $this->game->gamestate->state(true, false, true)['name'];
        if ($this->game->gamestate->state(true, false, true)['name'] != 'playerTurn') {
            $currentState = 'playerTurn';
        }
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
            'characterData' => $characterData,
            'globalsData' => $globalsData,
            'extraTables' => $extraTables,
            'stateName' => $stateName,
        ];
    }

    public function saveState(): void
    {
        if ($this->stateSaved) {
            return;
        }
        $this->stateSaved = true;
        $char = $this->game->character->getTurnCharacterId();
        if (
            !$this->actionWasCleared &&
            $this->initialState &&
            in_array($this->initialState['stateName'], $this->validStates) &&
            $char == $this->game->character->getSubmittingCharacterId()
        ) {
            if ($this->savedMoveId != null) {
                $this->game::DbQuery('DELETE FROM `undoState` where pending OR gamelog_move_id=' . $this->savedMoveId);
            }
            $moveId = $this->initialState['moveId'];
            $characterData = $this->game::escapeStringForDB($this->initialState['characterData']);
            $globalsData = $this->game::escapeStringForDB($this->initialState['globalsData']);
            $extraTables = $this->game::escapeStringForDB($this->initialState['extraTables']);
            $this->savedMoveId = $moveId;

            $pending = 'false';
            if (!in_array($this->game->gamestate->state(true, false, true)['name'], $this->validStates)) {
                $pending = 'true';
            }
            $this->game::DbQuery(
                'INSERT INTO `undoState` (`character_id`, `gamelog_move_id`, `pending`, `characterTable`, `globalsTable`, `extraTables`) VALUES ' .
                    "('$char', $moveId, $pending, '$characterData', '$globalsData', '$extraTables')"
            );
        }
        if (
            !$this->actionWasCleared &&
            $this->initialState &&
            !in_array($this->initialState['stateName'], $this->validStates) &&
            $char == $this->game->character->getSubmittingCharacterId()
        ) {
            if (in_array($this->game->gamestate->state(true, false, true)['name'], $this->validStates)) {
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
