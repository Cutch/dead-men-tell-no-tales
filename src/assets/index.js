import characters from './character-data';
import revengeDeck from './revenge-deck-data';
import tile from './tile-data';
import ocean from './ocean-data';
import tracker from './ocean-data';
import token from './token-data';
let dataCache = null;
export const getAllData = () => {
  if (dataCache) return dataCache;
  dataCache = [characters, revengeDeck, tile, ocean, tracker, token].reduce((acc, data) => {
    const { sprites, meta } = data.getData();
    Object.keys(sprites).forEach((k) => ((sprites[k].meta = meta), (sprites[k].id = k)));
    return {
      ...acc,
      ...sprites,
    };
  }, {});
  return dataCache;
};
