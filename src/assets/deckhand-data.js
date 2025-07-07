import dojo from 'dojo';
export default {
  getData: () => ({
    sprites: {
      'deckhand-red': {
        frame: {
          x: 0,
          y: 0,
          w: 150,
          h: 150,
        },
      },
      deckhand: {
        frame: {
          x: 150,
          y: 0,
          w: 150,
          h: 150,
        },
      },
    },
    meta: {
      version: '1.0',
      image: 'deckhand.png',
      css: 'deckhand-card',
      size: {
        w: 300,
        h: 150,
      },
      scale: '1',
    },
  }),
};
