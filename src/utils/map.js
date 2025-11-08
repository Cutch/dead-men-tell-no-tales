import on from 'dojo/on';
import fx from 'dojo/fx';
import { renderImage } from './images';
import Panzoom from '@panzoom/panzoom';
import { Dice } from './dice';
import { addClickListener } from './clickable';
import dojo from 'dojo';
import { v4 } from 'uuid';
import { addPassiveListener } from './utils';
export class Map {
  constructor(game, gameData) {
    this.game = game;
    this.positions = {};
    this.positionsLookup = {};
    this.deckhandSelection = [];
    this.placeListeners = [];
    this.selectionListeners = [];
    this.deckhandListeners = [];
    this.dice = {};
    this.maxX = 0;
    this.minX = 0;
    this.maxY = 0;
    this.minY = 0;
    this.firstLoad = true;
    const buttonHTML = `<div class="map-buttons-wrapper"><div class="map-buttons">
      <button id="zoom-in"><i class="fa6 fa6-solid fa6-magnifying-glass-plus"></i></button>
      <button id="zoom-out"><i class="fa6 fa6-solid fa6-magnifying-glass-minus"></i></button>
      <button id="locate"><i class="fa6 fa6-solid fa6-map-location-dot"></i></button>
      <button id="toggle-token"><i class="fa6 fa6-solid fa6-eye-slash"></i></button>
      <button id="fullscreen"><i class="fa6 fa6-solid fa6-down-left-and-up-right-to-center"></i></button>
    </div></div>`;
    document.getElementById('game_play_area').insertAdjacentHTML(
      'beforeend',
      `<div id="map-wrapper" class="map-wrapper" style="height: calc(60vh / var(--bga-game-zoom, 1));">
        <div id="map-container"></div>${buttonHTML}
        <div id="new-card-container" style="display: none"></div>
        <div id="left-card-container" style="display: none; opacity: 0"></div>
        </div>`,
    );
    on($('fullscreen'), 'click', () => {
      this.fullscreen = !this.fullscreen;
      this.updateFullscreen();
      this.savePanZoom();
    });
    on($('zoom-in'), 'click', () => {
      this.zoom('in');
      this.savePanZoom();
    });
    on($('zoom-out'), 'click', () => {
      this.zoom('out');
      this.savePanZoom();
    });
    on($('locate'), 'click', () => {
      this.panzoom.reset();
      this.flickerPlayerTokens();
      this.savePanZoom();
    });
    this.wrapper = $('map-wrapper');
    this.container = $('map-container');
    on($('toggle-token'), 'click', () => {
      if (this.wrapper.classList.contains('hide-tokens')) this.wrapper.classList.remove('hide-tokens');
      else this.wrapper.classList.add('hide-tokens');
    });
    this.newCardContainer = $('new-card-container');
    this.leftCardContainer = $('left-card-container');
    const defaultScale = 0.5;
    this.panzoom = Panzoom(this.container, {
      maxScale: 0.75,
      minScale: 0.25,
      startScale: defaultScale,
      canvas: true,
    });
    const originalZoomToPoint = this.panzoom.zoomToPoint;
    this.panzoom.zoomToPoint = (scale, point, options) => {
      point = {
        clientX: point.clientX * this.getBGAZoom(),
        clientY: point.clientY * this.getBGAZoom(),
      };
      originalZoomToPoint.apply(this.panzoom, [scale, point, options]);
    };
    addPassiveListener('resize', () => {
      this.setPanOptions();
    });
    renderImage('tracker', this.container, {
      pos: 'append',
      card: false,
      scale: 1,
      baseData: this.getKey({ x: 0, y: -1 }),
      styles: { position: 'absolute' },
    });
    this.container
      .querySelector('.tracker-base')
      .insertAdjacentHTML(
        'beforeend',
        `<div class="tokens characters"></div><div class="explosions-marker"></div><div class="tile-selector" style="display: none"><div class="dot dot--number counter"></div></div>`,
      );

    renderImage('explosion', this.container.querySelector('.explosions-marker'), {
      pos: 'replace',
      card: false,
      scale: 1.5,
      styles: {
        left: `${80 + 140 * ((this.game.gamedatas.explosions ?? 0) - 1)}px`,
        display: (this.game.gamedatas.explosions ?? 0) == 0 ? 'none' : '',
        top: `2px`,
        position: 'absolute',
        transition: '1s left',
      },
    });
    this.explosion = this.container.querySelector('.explosion-base');
    this.update(gameData);
    this.panzoom.pan(this.panzoom.getOptions().startX, this.panzoom.getOptions().startY);
    this.loadPanZoom();
  }
  updateFullscreen() {
    $('map-wrapper').style.height = this.fullscreen ? 'calc(90vh / var(--bga-game-zoom, 1))' : 'calc(60vh / var(--bga-game-zoom, 1))';
  }
  zoom(inOut = 'in', duration = 250) {
    const start = this.panzoom.getScale();
    const end = start * Math.exp((inOut === 'out' ? -1 : 1) * 0.3);
    const { x, y, width, height } = $('map-wrapper').getBoundingClientRect();
    const direction = Math.sign(end - start);
    const step = ((direction * Math.abs(end - start)) / duration) * 10;
    let scale = start;
    const intervalId = setInterval(() => {
      if ((direction === 1 && scale > end) || (direction === -1 && scale < end)) clearInterval(intervalId);
      scale += step;
      this.panzoom.zoomToPoint(scale, { clientX: x + width / 2, clientY: y + height / 2 }, { animate: false });
    }, 10);
    this.savePanZoom();
  }
  getBGAZoom() {
    return parseFloat($('overall-content').style.getPropertyValue('--bga-game-zoom')) || 1;
  }
  setPanOptions() {
    const center = this.getCharacterCenter();
    if (center) {
      this.panzoom.setOptions({
        startX: center.x,
        startY: center.y,
      });
    }
  }
  getMapSize() {
    return {
      width: (Math.abs(this.minX) + Math.abs(this.maxX) + 3) * 299,
      height: (Math.abs(this.minY) + Math.abs(this.maxY) + 2) * 299 + 250,
    };
  }
  setCurrentPlayerCenter() {
    const center = this.getCharacterCenter();
    if (center) {
      this.panzoom.setOptions({ startX: center.x, startY: center.y });
    }
  }
  getCharacterCenter() {
    const character = this.game.gamedatas.characters.find((d) => d.id == this.game.gamedatas.activeCharacter);
    if (!character) return null;
    const { x: tileX, y: tileY } = this.calcTilePosition(...character.pos);

    const mapSize = this.getMapSize();
    const mapCenter = this.getMapCenter();

    return {
      x: mapCenter.x + mapSize.width * 0.5 - (tileX + 150),
      y: mapCenter.y + (tileY + 150) - mapSize.height * 0.5,
    };
  }
  getMapCenter() {
    const wrapperBox = this.wrapper.getBoundingClientRect();
    const mapSize = this.getMapSize();
    const zoom = this.panzoom.getScale();
    return {
      x: ((wrapperBox.width / this.getBGAZoom() - mapSize.width) * 0.5) / zoom,
      y: ((wrapperBox.height / this.getBGAZoom() - mapSize.height) * 0.5) / zoom,
    };
  }
  savePanZoom() {
    const center = this.getCharacterCenter();
    if (center) {
      this.panzoom.setOptions({
        startX: center.x,
        startY: center.y,
        startScale: this.panzoom.getScale(),
      });

      const data = JSON.stringify({
        options: this.panzoom.getOptions(),
        // pan: this.panzoom.getPan(),
        scale: this.panzoom.getScale(),
        id: this.game.table_id,
        fullscreen: this.fullscreen,
      });
      localStorage.setItem('dmtnt_data', data);
    }
  }
  loadPanZoom() {
    this.setCurrentPlayerCenter();
    if (localStorage.getItem('dmtnt_data')) {
      const data = JSON.parse(localStorage.getItem('dmtnt_data'));
      if (data.id !== this.game.table_id) return;
      this.panzoom.setOptions(data.options);
      // this.panzoom.pan(data.pan.x, data.pan.y);
      this.panzoom.zoom(data.scale);
      this.fullscreen = data.fullscreen;
      this.updateFullscreen();
    }
  }
  flickerPlayerTokens() {
    document.querySelectorAll('.tokens.characters .character-token-card-base').forEach((e) => {
      e.classList.add('flash');
    });
    setTimeout(
      () => {
        document.querySelectorAll('.tokens.characters .character-token-card-base').forEach((e) => {
          e.classList.remove('flash');
        });
      },
      0.75 * 4 * 1000,
    );
  }
  hideTileSelectionScreen(showId) {
    if (showId && this.showId !== showId) return;
    document.querySelectorAll('.tile-selector').forEach((e) => {
      e.classList.remove('tile-selected');
      e.style.display = 'none';
    });
    this.selectionListeners.forEach((d) => d());
    this.selectionListeners = [];
    this.selectionPosition = null;
  }
  showTileSelectionScreen(type, selection, tokenImage) {
    this.showId = v4();
    document.querySelectorAll('.tile-selector').forEach((e) => {
      e.classList.remove('tile-selected');
      e.style.display = 'none';
    });
    this.selectionListeners.forEach((d) => d());
    this.selectionListeners = [];
    this.selectionPosition = null;
    if (Array.isArray(selection)) selection = selection.reduce((acc, d) => ({ ...acc, [d]: d }), {});
    Object.entries(selection).forEach(([tileId, count]) => {
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
          this.selectionPosition = { x, y, count };
        }),
      );
    });
  }
  getSelectionPosition() {
    return this.selectionPosition;
  }
  async setLeftCard(id) {
    renderImage(id, this.leftCardContainer, { pos: 'replace', scale: 1.5, card: false });
    this.leftCardContainer.insertAdjacentHTML('afterbegin', `<h3>${_('Revenge Card')}</h3>`);
    this.leftCardContainer.style.display = '';

    const anim = fx.chain([dojo.fadeIn({ node: this.leftCardContainer }), dojo.fadeOut({ node: this.leftCardContainer, delay: 3000 })]);
    dojo.connect(anim, 'onEnd', () => {
      this.leftCardContainer.style.display = 'none';
    });
    await this.game.bgaPlayDojoAnimation(anim);
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
      if (!this.game.gamedatas.validLocations.some((d) => d.x == x && d.y == y)) return;
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
              <div class="new-card-buttons" style="display: ${id !== 'dinghy' ? 'unset' : 'none'}">
                <button id="rotate-left"><i class="fa6 fa6-solid fa6-rotate-left"></i></button>
                <button id="rotate-right"><i class="fa6 fa6-solid fa6-rotate-right"></i></button>
              </div>
            </div>`,
          );
          if (id === 'dinghy') {
            const xy = this.positionsLookup[this.game.gamedatas.lastPlacedTileId];
            const { x: nx, y: ny } = this.getXY(xy);
            const dx = nx - x;
            const dy = ny - y;
            const [, , rotate] = [
              [0, -1, 180],
              [-1, 0, 270],
              [1, 0, 90],
              [0, 1, 0],
            ].find(([fx, fy]) => {
              return fx === dx && fy === dy;
            });
            console.log(dx, dy, rotate);
            this.cardRotation = rotate;
          }
          renderImage(id, elem.querySelector('.new-card-image'), { scale: 1, rotate: this.cardRotation, card: false });
          elem.querySelector('.new-card-image .tile-card').style.transform = `rotate(${this.cardRotation}deg)`;
          if (id !== 'dinghy') {
            addClickListener($('rotate-left'), 'Rotate Left', () => {
              this.cardRotation = (this.cardRotation ?? 0) - 90;
              elem.querySelector('.new-card-image .tile-card').style.transform = `rotate(${this.cardRotation}deg)`;
            });
            addClickListener($('rotate-right'), 'Rotate Right', () => {
              this.cardRotation = (this.cardRotation ?? 0) + 90;
              elem.querySelector('.new-card-image .tile-card').style.transform = `rotate(${this.cardRotation}deg)`;
            });
          }
        }),
      );
    });
  }
  showDeckhandSelection() {
    const deckhandTargetCount = this.game.gamedatas.deckhandTargetCount;
    const adjacentTiles = this.game.gamedatas.adjacentTiles;
    const currentPosition = this.getXY(this.game.gamedatas.currentPosition);
    document.querySelectorAll('.tile-card-base').forEach((baseElem) => {
      const { x, y } = this.getXY(baseElem.getAttribute('data-data'));
      if (!adjacentTiles.some((d) => d.x == x && d.y == y) && !(currentPosition.x == x && currentPosition.y == y)) return;
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
  checkIfAdjacent(x, y, toTileId = null) {
    return [
      [0, -1],
      [-1, 0],
      [1, 0],
      [0, 1],
    ].some(([nx, ny]) => {
      const tileId = this.positions[this.getKey({ x: x + nx, y: y + ny })];
      return toTileId ? toTileId === tileId : tileId;
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
      container.innerHTML = `<div class="deckhand"><div class="dot dot--number counter">${count - 1}</div></div><div class="deckhand"></div>`;
    } else if (count) {
      container.innerHTML = Array(parseInt(count, 10))
        .fill(0)
        .map(() => `<div class="deckhand"></div>`)
        .join('');
    } else if (count == 0) {
      container.innerHTML = '';
    }
    container.querySelectorAll('.deckhand').forEach((elem) => {
      renderImage('deckhand', elem, { pos: 'insert', card: false, scale: 3, baseData: v4() });
    });
  }
  getWindowRelativeOffset(elem, elem2) {
    return {
      left: (elem.getBoundingClientRect().left - elem2.getBoundingClientRect().left) / this.panzoom.getScale() / this.getBGAZoom(),
      top: (elem.getBoundingClientRect().top - elem2.getBoundingClientRect().top) / this.panzoom.getScale() / this.getBGAZoom(),
    };
  }
  renderTokens(container, positions, x, y) {
    const xyId = this.getKey({ x, y });
    container.style.setProperty('--count', positions[xyId]?.length ?? 0);
    positions[xyId]?.forEach(({ name, oldName, id, type }) => {
      const currentElem = this.container.querySelector(`.id${id}`);
      if (currentElem) {
        if (currentElem.classList.contains('token-flip')) return;
        if (currentElem.getAttribute('data-data') !== xyId) {
          container.insertAdjacentHTML('beforeend', '<div class="temp-mover"></div>');
          currentElem.setAttribute('data-data', xyId);
          const tempMover = container.querySelector('.temp-mover');
          const targetOffset = this.getWindowRelativeOffset(tempMover, currentElem);
          currentElem.style.position = 'absolute';
          currentElem.style.left = '0px';
          currentElem.style.top = '0px';
          currentElem.style.zIndex = '5';
          const animationId = fx.slideTo({
            node: currentElem,
            ...targetOffset,
            units: 'px',
            duration: 750,
          });
          dojo.connect(animationId, 'onEnd', () => {
            container.querySelector('.temp-mover').remove();
            container.appendChild(currentElem);
            currentElem.style.position = '';
            currentElem.style.left = '';
            currentElem.style.top = '';
          });
          animationId.play();
        } else if (
          type === 'treasure' &&
          container.querySelector(`.id${id}`) &&
          !container.querySelector(`.id${id}`).querySelector(`.${name}-token`)
        ) {
          container.querySelector(`.id${id}`).outerHTML =
            `<div class="token-flip id${id}"><div class="token-flip-inner"><div class="token-flip-front"></div><div class="token-flip-back"></div></div></div>`;

          renderImage(oldName + '-token', container.querySelector('.token-flip-front'), {
            pos: 'replace',
            card: false,
            scale: 1.5,
            baseData: xyId,
            baseCss: 'id' + id,
            styles: { '--color': '#000' },
          });
          renderImage(name + '-token', container.querySelector('.token-flip-back'), {
            pos: 'replace',
            card: false,
            scale: 1.5,
            baseData: xyId,
            baseCss: 'id' + id,
            styles: { '--color': '#000' },
          });
          setTimeout(() => {
            container.querySelector('.token-flip').classList.add('flip');
          }, 0);
          setTimeout(() => {
            if (container.querySelector(`.id${id}`))
              container.querySelector(`.id${id}`).outerHTML = renderImage(name + '-token', container, {
                pos: 'return',
                card: false,
                scale: 1.5,
                baseData: xyId,
                baseCss: 'id' + id,
                styles: { '--color': '#000' },
              });
          }, 1000);
        }
      } else {
        const color = this.game.gamedatas.characters.find((d) => d.id === name)?.characterColor;
        renderImage(name + '-token', container, {
          pos: 'append',
          card: false,
          scale: 1.5,
          baseData: xyId,
          baseCss: 'id' + id,
          styles: { '--color': color ?? '#000' },
        });
        if (color)
          addClickListener(this.container.querySelector(`.id${id}`), name, () => {
            this.game.tooltip.show();
            renderImage(name, this.game.tooltip.renderByElement(), { withText: true, type: 'tooltip-character', pos: 'replace' });
          });
      }
    });
  }
  calcTilePosition(x, y) {
    return {
      x: (x - this.minX + 1) * 299,
      y: (y - this.minY) * 299 + 250,
    };
  }
  update({ tiles, characters, refresh }) {
    if (this.newCardPhase) return;
    if (
      (this.game.gamedatas.gamestate?.name !== 'characterSelect' && this.lastCharacters !== characters.map((d) => d.id).join(',')) ||
      refresh
    ) {
      document.querySelectorAll('.tracker-base .character-token-card-base').forEach((el) => el.remove());
      document.querySelectorAll('.tile-back-base').forEach((el) => el.remove());
      document.querySelectorAll('.tile-card-base .character-token-card-base').forEach((el) => el.remove());
      document.querySelectorAll('.tile-flip').forEach((el) => el.remove());
    }
    this.lastCharacters = characters.map((d) => d.id).join(',');
    // if (this.newCardPhase && !this.game.refreshTiles) return;
    if ((tiles ?? this.game.gamedatas.tiles).length == 0) return;
    this.container.querySelectorAll('.ocean-base:not(.ignore)').forEach((e) => e.remove());
    this.maxX = 0;
    this.minX = 0;
    this.maxY = 0;
    this.minY = 0;
    (tiles ?? this.game.gamedatas.tiles)?.forEach(({ id: name, x, y }) => {
      if (name == 'tracker') return;
      this.minX = Math.min(this.minX, x);
      this.maxX = Math.max(this.maxX, x);
      this.minY = Math.min(this.minY, y);
      this.maxY = Math.max(this.maxY, y);
    });
    (tiles ?? this.game.gamedatas.tiles)?.forEach(
      ({ id: name, x, y, rotate, fire, fire_color: fireColor, deckhand, has_trapdoor: hasTrapdoor, exploded, destroyed, explosion }) => {
        if (name == 'tracker') return;
        const tileKey = this.getKey({ x, y });
        this.positions[tileKey] = name;
        this.positionsLookup[name] = tileKey;
        let tileElem = this.container.querySelector(`.${name}-base:not(.tile-flip)`);
        const { x: tileX, y: tileY } = this.calcTilePosition(x, y);
        if (destroyed == 1) {
          if (tileElem && !tileElem.querySelector('.token-flip')) {
            this.dice[tileKey] = null;
            tileElem.outerHTML = `<div class="token-flip tile-flip ${name}-base"><div class="token-flip-inner"><div class="token-flip-front"></div><div class="token-flip-back"></div></div></div>`;
            const newTileElem = this.container.querySelector(`.${name}-base`);
            newTileElem.style.left = `${tileX}px`;
            newTileElem.style.bottom = `${tileY}px`;
            newTileElem.style.position = 'absolute';
            renderImage(name, newTileElem.querySelector('.token-flip-front'), {
              pos: 'replace',
              card: false,
              scale: 1,
              rotate: rotate * 90,
              baseData: tileKey,
              styles: {
                '--rotate': rotate * 90,
              },
            });
            renderImage('tile-back', newTileElem.querySelector('.token-flip-back'), {
              pos: 'replace',
              card: false,
              scale: 1,
              rotate: rotate * 90,
              baseData: tileKey,
              styles: {
                '--rotate': rotate * 90,
              },
            });
            setTimeout(() => {
              newTileElem.classList.add('flip');
            }, 0);
          } else {
            name = 'tile-back';
            tileElem = null;
          }
        }
        if (!tileElem) {
          if (name === 'dinghy')
            renderImage('ocean', this.container, {
              pos: 'append',
              card: false,
              scale: 1,
              baseCss: 'ignore',
              styles: { left: `${tileX}px`, bottom: `${tileY}px`, position: 'absolute' },
            });
          renderImage(name, this.container, {
            pos: 'append',
            card: false,
            scale: 1,
            rotate: rotate * 90,
            baseData: tileKey,
            styles: {
              left: `${tileX}px`,
              bottom: `${tileY}px`,
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
            <div class="fire-warning" style="display: none"><i class="fa6 fa6-solid fa6-fire"></i></div>
            <div class="barrel-marker"></div>
            <div class="deckhands"></div>
            <div class="tokens treasures"></div>
            <div class="tokens characters"></div>
            <div class="tile-selector" style="display: none"><div class="dot dot--number counter"></div></div>`,
          );

          this.game.addHelpTooltip({
            node: tileElem.querySelector(`.trapdoor`),
            text: _('Trapdoors will fill the room with deckhands'),
            noIcon: true,
          });
          this.game.addHelpTooltip({
            node: tileElem.querySelector(`.barrel-marker`),
            text: _('Powder Kegs explode when the corresponding fire level is reached'),
            noIcon: true,
          });
          this.game.addHelpTooltip({
            node: tileElem.querySelector(`.fire-warning`),
            text: _('Warning, the next fire level will cause an explosion (either a powder keg or the room)'),
            noIcon: true,
          });
          this.game.addHelpTooltip({
            node: tileElem.querySelector(`.deckhands`),
            text: [
              [_('Deckhands will spread to other rooms without a trapdoor')],
              [{ bold: true, text: _('2 Deckhands in a room:') }, ' ', _('The Pirates cannot pick up any tokens in this room')],
              [{ bold: true, text: _('3 or more Deckhands in a room:') }, ' ', _('No Pirate can enter this room')],
            ],
            noIcon: true,
          });
          this.game.addHelpTooltip({
            node: tileElem.querySelector(`.treasures`),
            text: [
              [{ bold: true, text: _('Skeleton Crew:') }, ' ', _('Must be battled before the guards, can move')],
              [{ bold: true, text: _('Guards:') }, ' ', _('Never Move, drop treasure when defeated')],
              [
                { bold: true, text: _('Captain Fromm:') },
                ' ',
                _('Similar to the crew, however he goes back in the draw pile when defeated, and must be defeated to win the game'),
              ],
              [{ bold: true, text: _('Grog:') }, ' ', _('Recover your fatigue by the number listed on the token')],
              [{ bold: true, text: _('Cutlass:') }, ' ', _('Give permanent strength increase to the character holding it')],
              [{ bold: true, text: _('Treasure:') }, ' ', _('Escape the ship with these to win the game')],
            ],
            noIcon: true,
          });

          if (name !== 'dinghy') {
            const diceElem = tileElem.querySelector(`.dice`);
            const die = new Dice(this.game, diceElem, fireColor);
            this.dice[tileKey] = die;
          }
          const trapdoorElem = tileElem.querySelector(`.trapdoor`);
          if (hasTrapdoor == 1 && name !== 'tile004') {
            renderImage('trapdoor', trapdoorElem, { scale: 1.5 });
          }
        } else {
          tileElem.style.left = `${tileX}px`;
          tileElem.style.bottom = `${tileY}px`;
        }
        if (exploded == 1)
          renderImage('explosion-barrel', tileElem.querySelector(`.barrel-marker`), {
            pos: 'replace',
            card: false,
            scale: 1.5,
          });
        tileElem.querySelector(`.fire-warning`).style.display =
          exploded != 1 && destroyed != 1 && (parseInt(fire, 10) + 1 == explosion || fire == 5) ? '' : 'none';

        const deckhandElem = tileElem.querySelector(`.deckhands`);
        const charactersElem = tileElem.querySelector(`.characters`);
        const treasuresElem = tileElem.querySelector(`.treasures`);
        if (deckhandElem) this.renderDeckhands(deckhandElem, deckhand);
        if (treasuresElem) characters.forEach((d) => d.tokenItems.forEach((t) => treasuresElem.querySelector(`.id${t.id}`)?.remove()));
        if (this.game.gamedatas.characterPositions && charactersElem)
          this.renderTokens(charactersElem, this.game.gamedatas.characterPositions, x, y);
        if (this.game.gamedatas.tokenPositions && treasuresElem) {
          if (
            !Object.values(this.game.gamedatas.tokenPositions)
              .reduce((acc, d) => [...acc, ...d], [])
              .some((d) => d.name.includes('captain'))
          ) {
            document.querySelector('.captain-4-token-base')?.remove();
            document.querySelector('.captain-8-token-base')?.remove();
          }
          this.renderTokens(treasuresElem, this.game.gamedatas.tokenPositions, x, y);
        }
        if (this.dice[tileKey]) {
          if (fire == 0) {
            this.dice[tileKey]._show();
            this.dice[tileKey]._hide();
          } else {
            this.dice[tileKey]._show();
            this.dice[tileKey]._set({ roll: fire });
          }
        }
      },
    );
    const trackerCharactersElem = this.container.querySelector('.tracker-base .characters');
    this.renderTokens(trackerCharactersElem, this.game.gamedatas.characterPositions, 0, -1);
    for (let x = this.minX - 1; x <= this.maxX + 1; x++) {
      for (let y = this.minY - 1; y <= this.maxY + 1; y++) {
        if (y <= -1) continue;
        if (this.positions[this.getKey({ x, y })]) continue;
        const { x: tileX, y: tileY } = this.calcTilePosition(x, y);
        renderImage('ocean', this.container, {
          pos: 'append',
          card: false,
          scale: 1,
          baseData: this.getKey({ x, y }),
          styles: { left: `${tileX}px`, bottom: `${tileY}px`, position: 'absolute' },
        });
      }
    }
    const trackerElem = this.container.querySelector('.tracker-base');
    const mapSize = this.getMapSize();
    this.container.style.width = `${mapSize.width}px`;
    this.container.style.height = `${mapSize.height}px`;
    this.setPanOptions();
    trackerElem.style.left = `${Math.abs(this.minX) * 299 - 105}px`;
    trackerElem.style.top = `${(Math.abs(this.minY) + Math.abs(this.maxY) + 2) * 299}px`;

    if (this.game.gamedatas.explosions != null) {
      this.explosion.style.display = this.game.gamedatas.explosions == 0 ? 'none' : '';
      setTimeout(() => (this.explosion.style.left = `${80 + 140 * (this.game.gamedatas.explosions - 1)}px`), 0);
    }
    if (this.game.refreshTiles || this.firstLoad) {
      const mapCenter = this.getMapCenter();
      this.panzoom.pan(mapCenter.x, mapCenter.y);
      setTimeout(() => {
        const mapCenter = this.getMapCenter();
        this.panzoom.pan(mapCenter.x, mapCenter.y);
      }, 0);
      this.refreshTiles = false;
      this.firstLoad = false;
    }
  }
}
