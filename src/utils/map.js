import on from 'dojo/on';
import { renderImage } from './images';
import Panzoom from '@panzoom/panzoom';
import { Dice } from './dice';
import { addClickListener } from './clickable';
export class Map {
  constructor(game, gameData) {
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
        `<div class="map-buttons-wrapper"><div class="map-buttons"><button id="zoom-in"><i class="fa6 fa6-solid fa6-magnifying-glass-plus"></i></button><button id="zoom-out"><i class="fa6 fa6-solid fa6-magnifying-glass-minus"></i></button><button id="reset"><i class="fa6 fa6-solid fa6-map-location-dot"></i></button></div></div>`,
      );
    document
      .getElementById('game_play_area')
      .insertAdjacentHTML(
        'beforeend',
        `<div id="map-wrapper" class="map-wrapper" style="min-height: 70vh;"><div id="map-container" style="width: 0;"></div><div id="new-card-container" style="display: none"></div></div>`,
      );
    on($('zoom-in'), 'click', () => this.panzoom.zoomIn());
    on($('zoom-out'), 'click', () => this.panzoom.zoomOut());
    on($('reset'), 'click', () => this.panzoom.reset());
    this.wrapper = $('map-wrapper');
    this.container = $('map-container');
    this.newCardContainer = $('new-card-container');
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
    this.container.querySelector('.tracker-base').insertAdjacentHTML('beforeend', `<div class="characters"></div>`);

    renderImage('explosion', this.container, {
      pos: 'append',
      card: false,
      scale: 1.5,
      styles: {
        left: `${-325 + 150 * (gameData.explosions - 1)}px`,
        display: gameData.explosions === 0 ? 'none' : '',
        bottom: `-100px`,
        position: 'absolute',
      },
    });
    this.explosion = this.container.querySelector('.explosion-base');
    this.update(gameData);
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
  setNewCard(id) {
    renderImage(id, this.newCardContainer, { pos: 'replace', scale: 1.5, card: false });
    this.newCardContainer.insertAdjacentHTML('afterbegin', `<h3>${_('New Tile')}</h3>`);
    this.newCardContainer.style.display = '';
    this.placeListeners = [];
    this.container.querySelectorAll('.ocean-base').forEach((elem) => {
      const { x, y } = this.getXY(elem.getAttribute('data-data'));
      if (!this.checkIfAdjacent(x, y)) return;
      elem.classList.add('place-new');

      this.placeListeners.push(
        addClickListener(elem, 'Place', () => {
          const { x, y } = this.getXY(elem.getAttribute('data-data'));
          if (x == this.cardPosition?.x && y == this.cardPosition?.y) return;
          this.cardPosition = { x, y };
          document.querySelectorAll('.new-card').forEach((d) => d.remove());
          elem.insertAdjacentHTML(
            'beforeend',
            `<div class="new-card">
              <div class="new-card-image"></div>
              <div class="new-card-buttons">
                <button id="rotate-left"><i class="fa6 fa6-solid fa6-rotate-left"></i></button>
                <button id="rotate-right"><i class="fa6 fa6-solid fa6-rotate-right"></i></button>
              </div>
            </div>`,
          );
          renderImage(id, elem.querySelector('.new-card-image'), { scale: 1, rotate: this.cardRotation, card: false });
          addClickListener($('rotate-left'), 'Rotate Left', () => {
            this.cardRotation = (this.cardRotation ?? 0) - 90;
            elem.querySelector('.new-card-image .tile-card').style.transform = `rotate(${this.cardRotation}deg)`;
          });
          addClickListener($('rotate-right'), 'Rotate Right', () => {
            this.cardRotation = (this.cardRotation ?? 0) + 90;
            elem.querySelector('.new-card-image .tile-card').style.transform = `rotate(${this.cardRotation}deg)`;
          });
        }),
      );
    });
  }
  getNewCardPosition() {
    return { rotate: ((((this.cardRotation ?? 0) % 360) + 360) % 360) / 90, ...this.cardPosition };
  }
  checkIfAdjacent(x, y) {
    return [
      [1, 0],
      [0, 1],
      [-1, 0],
      [0, -1],
    ].some(([nx, ny]) => {
      return !!this.positions[this.getKey({ x: x + nx, y: y + ny })];
    });
  }
  clearNewCard() {
    this.newCardContainer.style.display = 'none';
    document.querySelectorAll('.place-new').forEach((e) => e.classList.remove('place-new'));
    this.placeListeners.forEach((d) => d());
    this.cardRotation = 0;
    this.cardPosition = null;
  }
  getKey({ x, y }) {
    return `${x}x${y}`;
  }
  getXY(key) {
    const [x, y] = key.split('x');
    return { x: parseInt(x, 10), y: parseInt(y, 10) };
  }
  renderDeckhands(container, count) {
    if (count > 10) {
      container.innerHTML = `<div class="deckhand"><div class="dot dot--number counter">${count}</div></div>`;
    } else {
      container.innerHTML = Array(parseInt(count, 0))
        .fill(0)
        .map(() => `<div class="deckhand"></div>`)
        .join('');
    }
    container.querySelectorAll('.deckhand').forEach((elem) => renderImage('deckhand', elem, { pos: 'insert', card: false, scale: 3 }));
  }
  renderCharacters(container, characterPositions, x, y) {
    container.innerHTML = '';
    const id = this.getKey({ x, y });
    container.style.setProperty('--count', characterPositions[id]?.length ?? 0);
    characterPositions[id]?.forEach((id) => {
      renderImage(id + '-token', container, {
        pos: 'append',
        card: false,
        scale: 1.5,
        styles: { '--color': this.game.gamedatas.characters.find((d) => d.id === id).characterColor },
      });
    });
  }
  update({ tiles, explosions, characterPositions }) {
    this.container.querySelectorAll('.ocean').forEach((e) => e.remove());
    this.maxX = 0;
    this.minX = 0;
    this.maxY = 0;
    this.minY = 0;
    tiles?.forEach(({ id: name, x, y, rotate, fire, fire_color: fireColor, deckhand, has_trapdoor: hasTrapdoor, destroyed }) => {
      this.minX = Math.min(this.minX, x);
      this.maxX = Math.max(this.maxX, x);
      this.minY = Math.min(this.minY, y);
      this.maxY = Math.max(this.maxY, y);
      this.positions[this.getKey({ x, y })] = name;
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
          rotate: rotate * 90,
          styles: {
            left: `${x * 300}px`,
            bottom: `${y * 300}px`,
            position: 'absolute',
          },
        });
        if (destroyed == 1) return; // Stop rendering here if destroyed
        tileElem = this.container.querySelector(`.${name}-base`);
        tileElem.insertAdjacentHTML(
          'beforeend',
          '<div class="trapdoor"></div><div class="deckhands"></div><div class="dice"></div><div class="characters"></div>',
        );
        const diceElem = tileElem.querySelector(`.dice`);
        const die = new Dice(this.game, diceElem, fireColor);
        this.dice[this.getKey({ x, y })] = die;
        const trapdoorElem = tileElem.querySelector(`.trapdoor`);
        if (hasTrapdoor == 1 && name !== 'tile004') {
          renderImage('trapdoor', trapdoorElem, { scale: 1.5 });
        }
      }
      const deckhandElem = tileElem.querySelector(`.deckhands`);
      const charactersElem = tileElem.querySelector(`.characters`);
      this.renderDeckhands(deckhandElem, deckhand);
      this.renderCharacters(charactersElem, characterPositions, x, y);
      if (fire === 0) this.dice[this.getKey({ x, y })]._hide();
      else {
        this.dice[this.getKey({ x, y })]._show();
        this.dice[this.getKey({ x, y })]._set({ roll: fire });
      }
    });
    const trackerElem = this.container.querySelector('.tracker-base .characters');
    this.renderCharacters(trackerElem, characterPositions, 0, -1);
    for (let x = this.minX - 1; x <= this.maxX + 1; x++) {
      for (let y = this.minY - 1; y <= this.maxY + 1; y++) {
        if (y == -1 && x >= -2 && x <= 2) continue;
        if (this.positions[this.getKey({ x, y })]) continue;
        renderImage('ocean', this.container, {
          pos: 'append',
          card: false,
          scale: 1,
          baseData: this.getKey({ x, y }),
          styles: { left: `${x * 300}px`, bottom: `${y * 300}px`, position: 'absolute' },
        });
      }
    }
    this.explosion.style.left = `${-325 + 150 * (explosions - 1)}px`;
    this.explosion.style.display = explosions === 0 ? 'none' : '';
  }
}
