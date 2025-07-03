import characters from './character-data';
import revengeDeck from './revenge-deck-data';
import item from './item-data';
import tile from './tile-data';
import deckhand from './deckhand-data';
import dial from './dial-data';
import characterBoard from './character-board-data';
import characterTokens from './character-token';
import tracker from './tracker-data';
import token from './token-data';
let dataCache = null;
export const getAllData = () => {
  if (dataCache) return dataCache;
  dataCache = [characters, revengeDeck, dial, item, tile, deckhand, tracker, token, characterBoard, characterTokens].reduce((acc, data) => {
    const { sprites, meta } = data.getData();
    Object.keys(sprites).forEach((k) => ((sprites[k].meta = meta), (sprites[k].id = k)));
    return {
      ...acc,
      ...sprites,
    };
  }, {});
  return dataCache;
};
