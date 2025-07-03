import on from 'dojo/on';
import { renderImage } from './images';
import Panzoom from '@panzoom/panzoom';
import { Dice } from './dice';
export class Board {
  constructor(game, { tiles, explosions }) {
    this.game = game;
    this.positions = {};
    this.dice = {};
    this.maxX = 0;
    this.minX = 0;
    this.maxY = 0;
    this.minY = 0;
    document
      .getElementById('game_play_area')
      .insertAdjacentHTML(
        'beforeend',
        `<div class="board-buttons-wrapper"><div class="board-buttons"><button id="zoom-in"><i class="fa6 fa6-solid fa6-magnifying-glass-plus"></i></button><button id="zoom-out"><i class="fa6 fa6-solid fa6-magnifying-glass-minus"></i></button><button id="reset"><i class="fa6 fa6-solid fa6-map-location-dot"></i></button></div></div>`,
      );
    document
      .getElementById('game_play_area')
      .insertAdjacentHTML(
        'beforeend',
        `<div id="board-wrapper" class="board-wrapper" style="min-height: 70vh;"><div id="board-container" style="width: 0;"></div></div>`,
      );
    on($('zoom-in'), 'click', () => this.panzoom.zoomIn());
    on($('zoom-out'), 'click', () => this.panzoom.zoomOut());
    on($('reset'), 'click', () => this.panzoom.reset());
    this.wrapper = $('board-wrapper');
    this.container = $('board-container');
    const defaultScale = 0.5;
    this.panzoom = Panzoom(this.container, {
      maxScale: 5,
      startScale: defaultScale,
    });
    renderImage('tracker', this.container, {
      pos: 'append',
      card: false,
      scale: 1,
      styles: { left: `-400px`, bottom: `-192px`, position: 'absolute' },
    });
    renderImage('explosion', this.container, {
      pos: 'append',
      card: false,
      scale: 1.5,
      styles: {
        left: `${-325 + 150 * (explosions - 1)}px`,
        display: explosions === 0 ? 'none' : '',
        bottom: `-100px`,
        position: 'absolute',
      },
    });
    this.explosion = this.container.querySelector('.explosion-base');
    this.update({ tiles, explosions });
    setTimeout(() => {
      this.panzoom.pan(
        ((this.minX - 1) * 300) / 2 + this.wrapper.getBoundingClientRect().width,
        (this.minY * 300) / 2 + this.wrapper.getBoundingClientRect().height,
      );
      this.panzoom.setOptions({
        startX: ((this.minX - 1) * 300) / 2 + this.wrapper.getBoundingClientRect().width,
        startY: (this.minY * 300) / 2 + this.wrapper.getBoundingClientRect().height,
      });
    }, 0);
  }
  getKey({ x, y }) {
    return `${x}${y}`;
  }
  renderDeckhands(container, count) {
    container.innerHTML = Array(parseInt(count, 0))
      .fill(0)
      .map(() => `<div class="deckhand"></div>`)
      .join('');
    container.querySelectorAll('.deckhand').forEach((elem) => renderImage('deckhand', elem, { card: false }));
  }
  update({ tiles, explosions }) {
    this.container.querySelectorAll('.ocean').forEach((e) => e.remove());
    this.maxX = 0;
    this.minX = 0;
    this.maxY = 0;
    this.minY = 0;
    tiles?.forEach(({ map_id: name, x, y, rotate, fire, fire_color: fireColor, deckhand, has_trapdoor: hasTrapdoor, destroyed }) => {
      this.minX = Math.min(this.minX, x);
      this.maxX = Math.max(this.maxX, x);
      this.minY = Math.min(this.minY, y);
      this.maxY = Math.max(this.maxY, y);
      this.positions[`${x}${y}`] = name;
      let tileElem = this.container.querySelector(`.${name}-base`);
      if (destroyed == 1) {
        if (tileElem) {
          tileElem.remove();
        }
        name = 'tile-back';
        tileElem = null;
      }
      if (!tileElem) {
        renderImage(name, this.container, {
          pos: 'append',
          card: false,
          scale: 1,
          rotate,
          styles: {
            left: `${x * 300}px`,
            bottom: `${y * 300}px`,
            position: 'absolute',
          },
        });
        if (destroyed == 1) return; // Stop rendering here if destroyed
        tileElem = this.container.querySelector(`.${name}-base`);
        tileElem.insertAdjacentHTML('beforeend', '<div class="trapdoor"></div><div class="deckhands"></div><div class="dice"></div>');
        const diceElem = tileElem.querySelector(`.dice`);
        const die = new Dice(this.game, diceElem, fireColor);
        this.dice[this.getKey({ x, y })] = die;
        const trapdoorElem = tileElem.querySelector(`.trapdoor`);
        if (hasTrapdoor == 1 && name !== 'tile004') {
          renderImage('trapdoor', trapdoorElem, { scale: 1.5 });
        }
      }
      const deckhandElem = tileElem.querySelector(`.deckhands`);
      this.renderDeckhands(deckhandElem, deckhand);
      if (fire === 0) this.dice[this.getKey({ x, y })]._hide();
      else {
        this.dice[this.getKey({ x, y })]._show();
        this.dice[this.getKey({ x, y })]._set({ roll: fire });
      }
    });
    for (let x = this.minX - 1; x <= this.maxX + 1; x++) {
      for (let y = this.minY - 1; y <= this.maxY + 1; y++) {
        if (y == -1 && x >= -2 && x <= 2) continue;
        if (this.positions[this.getKey({ x, y })]) continue;
        renderImage('ocean', this.container, {
          pos: 'append',
          card: false,
          scale: 1,
          styles: { left: `${x * 300}px`, bottom: `${y * 300}px`, position: 'absolute' },
        });
      }
    }
    this.explosion.style.left = `${-325 + 150 * (explosions - 1)}px`;
    this.explosion.style.display = explosions === 0 ? 'none' : '';
  }
}
