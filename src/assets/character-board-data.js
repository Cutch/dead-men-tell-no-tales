import dojo from 'dojo';
export default {
  getData: () => ({
    sprites: {
      'character-board': {
        frame: {
          x: 0,
          y: 0,
          w: 1024,
          h: 946,
        },
      },
    },
    meta: {
      version: '1.0',
      image: 'player-board.png',
      css: 'player-board-card',
      size: {
        w: 1024,
        h: 946,
      },
      scale: '1',
    },
  }),
};
