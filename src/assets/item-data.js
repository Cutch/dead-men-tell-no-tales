import dojo from 'dojo';
export default {
  getData: () => ({
    sprites: {
      blanket: {
        frame: {
          x: 0,
          y: 0,
          w: 480,
          h: 344,
        },
        text: [{ title: _('Blanket') }, _('Cost: 1 Action'), _('May lower a Fire Die by 2 once per turn')],
      },
      bucket: {
        frame: {
          x: 480,
          y: 0,
          w: 480,
          h: 344,
        },
        text: [{ title: _('Bucket') }, _('Cost: 1 Action'), _('May lower a Fire Die in an adjacent room once per turn')],
      },
      compass: {
        frame: {
          x: 0,
          y: 344,
          w: 480,
          h: 344,
        },
        text: [{ title: _('Compass') }, _('Cost: None'), _('One free Walk or Run Action per turn')],
      },
      dagger: {
        frame: {
          x: 480,
          y: 344,
          w: 480,
          h: 344,
        },
        text: [{ title: _('Dagger') }, _('Cost: None'), _('One free Eliminate Deckhand Action per turn')],
      },
      pistol: {
        frame: {
          x: 0,
          y: 688,
          w: 480,
          h: 344,
        },
        text: [
          { title: _('Pistol') },
          _('Cost: 1 Action'),
          _('May attack from an adjacent room for one Action, once per turn'),
          _('No fatigue is lost for a failed attack'),
        ],
      },
      rum: {
        frame: {
          x: 480,
          y: 688,
          w: 480,
          h: 344,
        },
        text: [{ title: _('Rum') }, _('Cost: None'), _('One free Rest Action per turn')],
      },
      sword: {
        frame: {
          x: 960,
          y: 0,
          w: 480,
          h: 344,
        },
        text: [{ title: _('Sword') }, _('Cost: None'), _('Add 1 to Strength in Battle')],
      },
    },
    meta: {
      version: '1.0',
      image: 'item-spritesheet.png',
      css: 'item-card',
      size: {
        w: 1440,
        h: 1032,
      },
      scale: '1',
    },
  }),
};
