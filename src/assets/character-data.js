import dojo from 'dojo';
export default {
  getData: () => ({
    sprites: {
      garret: {
        frame: {
          x: 0,
          y: 0,
          w: 344,
          h: 480,
        },
        text: [
          { title: _('Black Gus Garrett') },
          _('When this player is in a room with Skeleton Crew, he may choose to make them flee in the direction of his choice'),
        ],
      },
      flynn: {
        frame: {
          x: 344,
          y: 0,
          w: 344,
          h: 480,
        },
        text: [
          { title: _('Crimson Flynn') },
          _('This player can lower Fire Levels in adjacent rooms in addition to the room that he is in'),
        ],
      },
      whitebeard: {
        frame: {
          x: 688,
          y: 0,
          w: 344,
          h: 480,
        },
        text: [{ title: _('Whitebeard') }, _('This player is fatigued by 1 less than normal (except during Battle)')],
      },
      lamore: {
        frame: {
          x: 0,
          y: 480,
          w: 344,
          h: 480,
        },
        text: [{ title: _('LySia Lamore') }, _('This player has one additional Action to use each turn')],
      },
      jade: {
        frame: {
          x: 344,
          y: 480,
          w: 344,
          h: 480,
        },
        text: [{ title: _('Jade') }, _('This player can eliminate 2 Deckhands for each Eliminate Deckhands Action')],
      },
      titian: {
        frame: {
          x: 688,
          y: 480,
          w: 344,
          h: 480,
        },
        text: [
          { title: _('Five-Fingered Titian') },
          _('When drawing a Revenge Card, this player instead draws 2, choose one and puts the other bock on the deck'),
        ],
      },
      fallen: {
        frame: {
          x: 1032,
          y: 0,
          w: 344,
          h: 480,
        },
        text: [{ title: _('Cobalt Fallen') }, _('While looting this player is Fatigued as if he were not looting')],
      },
    },
    meta: {
      version: '1.0',
      image: 'character-spritesheet.png',
      css: 'character-card',
      size: {
        w: 1376,
        h: 960,
      },
      scale: '1',
    },
  }),
};
