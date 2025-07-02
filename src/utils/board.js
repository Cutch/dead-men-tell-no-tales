import on from 'dojo/on';
import { renderImage } from './images';
import Panzoom from '@panzoom/panzoom';
export class Board {
  constructor(game, tiles) {
    this.game = game;
    this.positions = {};
    this.maxX = 0;
    this.minX = 0;
    this.maxY = 0;
    this.minY = 0;
    document
      .getElementById('game_play_area')
      .insertAdjacentHTML(
        'beforeend',
        `<div class="board-buttons"><button id="zoom-in">Zoom In</button><button id="zoom-out">Zoom Out</button><button id="reset">Reset</button></div>`,
      );
    document
      .getElementById('game_play_area')
      .insertAdjacentHTML('beforeend', `<div class="board-wrapper"><div id="board-container" style="min-height: 90vh;"></div></div>`);
    on($('zoom-in'), 'click', () => this.panzoom.zoomIn());
    on($('zoom-out'), 'click', () => this.panzoom.zoomOut());
    on($('reset'), 'click', () => this.panzoom.reset());
    this.container = $('board-container');
    const defaultScale = 0.5;
    this.panzoom = Panzoom(this.container, {
      maxScale: 5,
      startScale: defaultScale,
    });
    this.update(tiles);
    setTimeout(() => this.panzoom.pan(-(this.minX - 1) * 300, -(this.minY - 1) * 300), 0);
  }
  update(tiles) {
    this.container.querySelectorAll('.ocean').forEach((e) => e.remove());
    this.maxX = 0;
    this.minX = 0;
    this.maxY = 0;
    this.minY = 0;
    tiles.forEach(({ name, x, y }) => {
      this.minX = Math.min(this.minX, x);
      this.maxX = Math.max(this.maxX, x);
      this.minY = Math.min(this.minY, y);
      this.maxY = Math.max(this.maxY, y);
      this.positions[`${x}${y}`] = name;
      const tileElem = this.container.querySelector(`.${name}`);
      if (!tileElem)
        renderImage(name, this.container, {
          pos: 'append',
          card: false,
          scale: 1,
          styles: {
            left: `${x * 300}px`,
            top: `${y * 300}px`,
            position: 'absolute',
          },
        });
    });
    for (let x = this.minX - 1; x <= this.maxX + 1; x++) {
      for (let y = this.minY - 1; y <= this.maxY + 1; y++) {
        if (this.positions[`${x}${y}`]) continue;
        renderImage('ocean', this.container, {
          pos: 'append',
          card: false,
          scale: 1,
          styles: { left: `${x * 300}px`, top: `${y * 300}px`, position: 'absolute' },
        });
      }
    }
    this.panzoom.setOptions({ startX: -(this.minX - 1) * 300, startY: -(this.minY - 1) * 300 });
  }
}
