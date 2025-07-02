<?php
declare(strict_types=1);

namespace Bga\Games\DeadMenTellNoTales;
require_once dirname(__DIR__) . '/php/data-files/DMTNT_Utils.php';
require_once dirname(__DIR__) . '/php/data-files/DMTNT_Characters.php';
require_once dirname(__DIR__) . '/php/data-files/DMTNT_RevengeDeck.php';
require_once dirname(__DIR__) . '/php/data-files/DMTNT_Items.php';
require_once dirname(__DIR__) . '/php/data-files/DMTNT_Tile.php';
class DMTNT_Data
{
    private Game $game;
    private array $decks;
    private array $characters;
    private array $tile;
    private array $items;

    public function __construct(Game $game)
    {
        $this->game = $game;
    }
    private function expansionFilter(array $data)
    {
        if (array_key_exists('disabled', $data)) {
            return false;
        }
        if (!array_key_exists('expansion', $data)) {
            return true;
        }
        return $this->game->isValidExpansion($data['expansion']);
    }
    private function get($name)
    {
        if (!isset($this->revengeDeck)) {
            $revengeDeckData = (new DMTNT_RevengeDeckData())->getData();
            $charactersData = (new DMTNT_CharactersData())->getData();
            $tileData = (new DMTNT_TileData())->getData();
            $itemsData = (new DMTNT_ItemsData())->getData();
            $this->decks = array_merge(addId($revengeDeckData), addId($tileData));
            $this->characters = addId($charactersData);
            $this->tile = addId($tileData);
            $this->items = addId($itemsData);
        }

        return array_filter($this->$name, [$this, 'expansionFilter']);
    }
    public function getDecks()
    {
        return $this->get('decks');
    }
    public function getCharacters()
    {
        return $this->get('characters');
    }
    public function getTile()
    {
        return $this->get('tile');
    }
    public function getItems()
    {
        return $this->get('items');
    }
}
