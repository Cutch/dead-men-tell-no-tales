import dojo from 'dojo';
export default {
  getData: () => ({
    sprites: {
      dinghy: {
        frame: {
          x: 0,
          y: 0,
          w: 300,
          h: 215,
        },
      },
    },
    meta: {
      version: '1.0',
      image: 'dinghy.png',
      css: 'dinghy-card',
      size: {
        w: 300,
        h: 215,
      },
      scale: '1',
    },
  }),
};
