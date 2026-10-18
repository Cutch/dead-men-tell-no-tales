import dojo from 'dojo';
export default {
  getData: () => ({
    sprites: {
      garrett: {
        frame: {
          x: 0,
          y: 0,
          w: 344,
          h: 480,
        },
        options: {
          name: 'Black Gus Garrett',
          type: 'character',
          color: 'var(--garrett)',
          text: [
            { title: 'Black Gus Garrett' },
            _('When this player is in a room with Skeleton Crew, he may choose to make them flee in the direction of his choice'),
          ],
        },
      },
      flynn: {
        frame: {
          x: 344,
          y: 0,
          w: 344,
          h: 480,
        },
        options: {
          name: 'Crimson Flynn',
          type: 'character',
          color: 'var(--flynn)',
          text: [
            { title: 'Crimson Flynn' },
            _('This player can lower Fire Levels in adjacent rooms in addition to the room that he is in'),
          ],
        },
      },
      whitebeard: {
        frame: {
          x: 688,
          y: 0,
          w: 344,
          h: 480,
        },
        options: {
          name: 'Whitebeard',
          type: 'character',
          color: 'var(--whitebeard)',
          text: [{ title: 'Whitebeard' }, _('This player is fatigued by 1 less than normal (except during Battle)')],
        },
      },
      lamore: {
        frame: {
          x: 0,
          y: 480,
          w: 344,
          h: 480,
        },
        options: {
          name: 'Lydia Lamore',
          type: 'character',
          color: 'var(--lamore)',
          text: [{ title: 'Lydia Lamore' }, _('This player has one additional Action to use each turn')],
        },
      },
      jade: {
        frame: {
          x: 344,
          y: 480,
          w: 344,
          h: 480,
        },
        options: {
          name: 'Jade',
          type: 'character',
          color: 'var(--jade)',
          text: [{ title: 'Jade' }, _('This player can eliminate 2 Deckhands for each Eliminate Deckhands Action')],
        },
      },
      titian: {
        frame: {
          x: 688,
          y: 480,
          w: 344,
          h: 480,
        },
        options: {
          name: 'Five-Fingered Titian',
          type: 'character',
          color: 'var(--titian)',
          text: [
            { title: 'Five-Fingered Titian' },
            _('When drawing a Revenge Card, this player instead draws 2, choose one and puts the other back on the deck'),
          ],
        },
      },
      fallen: {
        frame: {
          x: 1032,
          y: 0,
          w: 344,
          h: 480,
        },
        options: {
          name: 'Cobalt Kallen',
          type: 'character',
          color: 'var(--fallen)',
          text: [{ title: 'Cobalt Kallen' }, _('While looting this player is Fatigued as if he were not looting')],
        },
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
