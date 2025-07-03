import dojo from 'dojo';
export default {
  getData: () => ({
    sprites: {
      deckhand: {
        frame: {
          x: 0,
          y: 0,
          w: 150,
          h: 300,
        },
      },
    },
    meta: {
      version: '1.0',
      image: 'deckhand.png',
      css: 'deckhand-card',
      size: {
        w: 150,
        h: 300,
      },
      scale: '1',
    },
  }),
};
