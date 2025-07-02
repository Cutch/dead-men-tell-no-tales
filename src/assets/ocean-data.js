import dojo from 'dojo';
export default {
  getData: () => ({
    sprites: {
      ocean: {
        frame: {
          x: 0,
          y: 0,
          w: 300,
          h: 300,
        },
      },
    },
    meta: {
      version: '1.0',
      image: 'ocean.png',
      css: 'ocean-card',
      size: {
        w: 300,
        h: 300,
      },
      scale: '1',
    },
  }),
};
