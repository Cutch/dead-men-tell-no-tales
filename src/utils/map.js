import on from 'dojo/on';
import fx from 'dojo/fx';
import { renderImage } from './images';
import Panzoom from '@panzoom/panzoom';
import { Dice } from './dice';
import { addClickListener } from './clickable';
import dojo from 'dojo';
import { v4 } from 'uuid';
export class Map {
  constructor(game, gameData) {
    this.game = game;
    this.positions = {};
    this.deckhandSelection = [];
    this.placeListeners = [];
    this.selectionListeners = [];
    this.deckhandListeners = [];
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
        `<div id="map-wrapper" class="map-wrapper" style="min-height: 60vh;"><div id="map-container" style="width: 0;"></div><div id="new-card-container" style="display: none"></div></div>`,
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
      bounds: true,
      boundsPadding: 0.2,
      startScale: defaultScale,
      // transformOrigin: { x: 0.5, y: 0.5 },
    });
    renderImage('tracker', this.container, {
      pos: 'append',
      card: false,
      scale: 1,
      baseData: this.getKey({ x: 0, y: -1 }),
      styles: { left: `-400px`, bottom: `-192px`, position: 'absolute' },
    });
    this.container
      .querySelector('.tracker-base')
      .insertAdjacentHTML(
        'beforeend',
        `<div class="tokens characters"></div><div class="tile-selector" style="display: none"><div class="dot dot--number counter"></div></div>`,
      );

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
  hideTileSelectionScreen() {
    document.querySelectorAll('.tile-selector').forEach((e) => {
      e.classList.remove('tile-selected');
      e.style.display = 'none';
    });
    this.selectionListeners.forEach((d) => d());
    this.selectionListeners = [];
    this.selectionPosition = null;
  }
  showTileSelectionScreen(type, selection, tokenImage) {
    document.querySelectorAll('.tile-selector').forEach((e) => e.classList.remove('tile-selected'));
    if (Array.isArray(selection)) selection = selection.reduce((acc, d) => ({ ...acc, [d]: d }), {});
    Object.entries(selection).forEach(([tileId, count]) => {
      // if (tileId == 'tracker') {
      //   return;
      // }
      const elem = this.container.querySelector(`.${tileId}-base .tile-selector`);
      elem.style.display = '';
      const dot = elem.querySelector('.dot');
      if (type == 'actMove') {
        dot.innerHTML = `<div class="fa6 fa6-solid fa6-person-running"></div>${count}`;
        dot.style.display = '';
      } else if (type == 'actFightFire') {
        dot.innerHTML = `<div class="fa6 fa6-solid fa6-droplet"></div>`;
        dot.style.display = '';
      } else if (type == 'actMoveCrew') {
        dot.innerHTML = `<div class="token-image"></div>`;
        renderImage(tokenImage + '-token', this.container.querySelector(`.${tileId}-base .tile-selector .token-image`), {
          pos: 'append',
          card: false,
          scale: 2,
          styles: { '--color': '#000' },
        });
        dot.style.display = '';
      } else {
        dot.style.display = 'none';
      }

      this.selectionListeners.push(
        addClickListener(elem, 'Select', () => {
          const { x, y } = this.getXY(elem.parentNode.getAttribute('data-data'));
          if (x == this.selectionPosition?.x && y == this.selectionPosition?.y) return;
          document.querySelectorAll('.tile-selector').forEach((e) => e.classList.remove('tile-selected'));
          elem.classList.add('tile-selected');
          this.selectionPosition = { x, y };
        }),
      );
    });
  }
  getSelectionPosition() {
    return this.selectionPosition;
  }
  clearNewCard() {
    this.newCardPhase = false;
    this.newCardContainer.style.display = 'none';
    document.querySelectorAll('.new-card').forEach((d) => d.remove());
    document.querySelectorAll('.place-new').forEach((e) => e.classList.remove('place-new'));
    this.placeListeners.forEach((d) => d());
    this.cardRotation = 0;
    this.cardPosition = null;
  }
  setNewCard(id) {
    this.newCardPhase = true;
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
  showDeckhandSelection() {
    const deckhandTargetCount = this.game.gamedatas.deckhandTargetCount;
    document.querySelectorAll('.tile-card-base').forEach((baseElem) => {
      const { x, y } = this.getXY(baseElem.getAttribute('data-data'));
      baseElem.querySelectorAll('.deckhand-card').forEach((elem) => {
        elem.classList.add('selectable');
        const uuid = elem.parentNode.getAttribute('data-data');

        this.deckhandListeners.push(
          addClickListener(elem.parentNode, 'Select', () => {
            const i = this.deckhandSelection.findIndex(({ uuid: d }) => d == uuid);
            if (i !== -1) {
              elem.classList.remove('selected');
              this.deckhandSelection.splice(i, 1);
            } else {
              if (this.deckhandSelection.length >= deckhandTargetCount) {
                document
                  .querySelector(
                    `.deckhand-card-base[data-data="${this.deckhandSelection[this.deckhandSelection.length - 1].uuid}"] .selected`,
                  )
                  .classList.remove('selected');
                this.deckhandSelection.splice(this.deckhandSelection.length - 1, 1);
              }
              elem.classList.add('selected');
              this.deckhandSelection.push({ x, y, uuid });
            }
          }),
        );
      });
    });
  }
  hideDeckhandSelection() {
    document.querySelectorAll('.deckhand-card').forEach((elem) => {
      elem.classList.remove('selectable');
      elem.classList.remove('selected');
    });

    this.deckhandListeners.forEach((d) => d());
    this.deckhandListeners = [];
    this.deckhandSelection = [];
  }
  getDeckhandSelection() {
    return this.deckhandSelection.map(({ x, y }) => ({ x, y }));
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
    } else if (count) {
      container.innerHTML = Array(parseInt(count, 10))
        .fill(0)
        .map(() => `<div class="deckhand"></div>`)
        .join('');
    }
    container.querySelectorAll('.deckhand').forEach((elem) => {
      renderImage('deckhand', elem, { pos: 'insert', card: false, scale: 3, baseData: v4() });
    });
  }
  getWindowRelativeOffset(parentElem, elem) {
    return {
      left: (elem.getBoundingClientRect().left - parentElem.getBoundingClientRect().left) / this.panzoom.getScale(),
      top: (elem.getBoundingClientRect().top - parentElem.getBoundingClientRect().top) / this.panzoom.getScale(),
    };
  }
  renderTokens(container, positions, x, y) {
    // container.innerHTML = '';
    const id = this.getKey({ x, y });
    container.style.setProperty('--count', positions[id]?.length ?? 0);
    positions[id]?.forEach(({ name, id, type }) => {
      const key = this.getKey({ x, y });
      const currentElem = this.container.querySelector(`.id${id}`);

      if (currentElem) {
        if (currentElem.getAttribute('data-data') !== key) {
          container.insertAdjacentHTML('beforeend', '<div class="temp-mover"></div>');
          currentElem.setAttribute('data-data', key);
          const tempMover = container.querySelector('.temp-mover');
          const targetOffset = this.getWindowRelativeOffset(this.container, tempMover);
          const currentOffset = this.getWindowRelativeOffset(this.container, currentElem);
          this.container.appendChild(currentElem);
          currentElem.style.position = 'absolute';
          currentElem.style.left = currentOffset.left + 'px';
          currentElem.style.top = currentOffset.top + 'px';
          const animationId = fx
            .slideTo({
              node: currentElem,
              ...targetOffset,
              units: 'px',
              duration: 750,
            })
            .play();
          dojo.connect(animationId, 'onEnd', () => {
            tempMover.remove();
            container.appendChild(currentElem);
            currentElem.style.position = '';
            currentElem.style.left = '';
            currentElem.style.top = '';
          });
          animationId.play();
        }
      } else {
        const color = this.game.gamedatas.characters.find((d) => d.id === name)?.characterColor;
        renderImage(name + '-token', container, {
          pos: 'append',
          card: false,
          scale: 1.5,
          baseData: this.getKey({ x, y }),
          baseCss: 'id' + id,
          styles: { '--color': color ?? '#000' },
        });
      }
    });
  }
  update({ tiles, explosions, characterPositions, tokenPositions }) {
    if (this.newCardPhase) return;
    this.container.querySelectorAll('.ocean-base').forEach((e) => e.remove());
    this.maxX = 0;
    this.minX = 0;
    this.maxY = 0;
    this.minY = 0;
    console.log('tiles', tiles ?? this.game.gamedatas.tiles);
    (tiles ?? this.game.gamedatas.tiles)?.forEach(
      ({ id: name, x, y, rotate, fire, fire_color: fireColor, deckhand, has_trapdoor: hasTrapdoor, exploded, destroyed, escape }) => {
        if (escape == 1) return;
        this.minX = Math.min(this.minX, x);
        this.maxX = Math.max(this.maxX, x);
        this.minY = Math.min(this.minY, y);
        this.maxY = Math.max(this.maxY, y);
        const tileKey = this.getKey({ x, y });
        this.positions[tileKey] = name;
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
            baseData: tileKey,
            styles: {
              left: `${x * 300}px`,
              bottom: `${y * 300}px`,
              position: 'absolute',
              '--rotate': rotate * 90,
            },
          });
          if (destroyed == 1) return; // Stop rendering here if destroyed
          tileElem = this.container.querySelector(`.${name}-base`);
          tileElem.insertAdjacentHTML(
            'beforeend',
            `<div class="trapdoor"></div>
            <div class="dice"></div>
            <div class="barrel-marker"></div>
            <div class="deckhands"></div>
            <div class="tokens treasures"></div>
            <div class="tokens characters"></div>
            <div class="tile-selector" style="display: none"><div class="dot dot--number counter"></div></div>`,
          );
          const diceElem = tileElem.querySelector(`.dice`);
          const die = new Dice(this.game, diceElem, fireColor);
          this.dice[tileKey] = die;
          const trapdoorElem = tileElem.querySelector(`.trapdoor`);
          if (hasTrapdoor == 1 && name !== 'tile004') {
            renderImage('trapdoor', trapdoorElem, { scale: 1.5 });
          }
        }
        if (exploded == 1)
          renderImage('explosion-barrel', tileElem.querySelector(`.barrel-marker`), {
            pos: 'replace',
            card: false,
            scale: 1.5,
          });
        const deckhandElem = tileElem.querySelector(`.deckhands`);
        const charactersElem = tileElem.querySelector(`.characters`);
        const treasuresElem = tileElem.querySelector(`.treasures`);
        this.renderDeckhands(deckhandElem, deckhand);
        console.log(tokenPositions);
        if (characterPositions) this.renderTokens(charactersElem, characterPositions, x, y);
        if (tokenPositions) this.renderTokens(treasuresElem, tokenPositions, x, y);
        if (fire === 0) this.dice[tileKey]._hide();
        else {
          this.dice[tileKey]._show();
          this.dice[tileKey]._set({ roll: fire });
        }
      },
    );
    const trackerElem = this.container.querySelector('.tracker-base .characters');
    this.renderTokens(trackerElem, characterPositions, 0, -1);
    for (let x = this.minX - 1; x <= this.maxX + 1; x++) {
      for (let y = this.minY - 1; y <= this.maxY + 1; y++) {
        if (y <= -1) continue;
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
    if (explosions != null) {
      this.explosion.style.left = `${-325 + 150 * (explosions - 1)}px`;
      this.explosion.style.display = explosions === 0 ? 'none' : '';
    }
  }
}
