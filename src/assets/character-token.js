import dojo from 'dojo';
export default {
  getData: () => ({
    sprites: {
      'lamore-token': {
        frame: {
          x: 0,
          y: 0,
          w: 150,
          h: 150,
        },
      },
      'fallen-token': {
        frame: {
          x: 150,
          y: 0,
          w: 150,
          h: 150,
        },
      },
      'garret-token': {
        frame: {
          x: 300,
          y: 0,
          w: 150,
          h: 150,
        },
      },
      'whitebeard-token': {
        frame: {
          x: 450,
          y: 0,
          w: 150,
          h: 150,
        },
      },
      'flynn-token': {
        frame: {
          x: 600,
          y: 0,
          w: 150,
          h: 150,
        },
      },
      'titian-token': {
        frame: {
          x: 750,
          y: 0,
          w: 150,
          h: 150,
        },
      },
      'jade-token': {
        frame: {
          x: 900,
          y: 0,
          w: 150,
          h: 150,
        },
      },
    },
    meta: {
      version: '1.0',
      image: 'character-token-spritesheet.png',
      css: 'character-token-card',
      size: {
        w: 1050,
        h: 150,
      },
      scale: '1',
    },
  }),
};
