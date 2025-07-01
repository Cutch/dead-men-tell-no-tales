import { getAllData } from '../assets';
import { addClickListener } from './clickable';
import { getSpriteSize, renderImage } from './images';
import { Tooltip } from './tooltip';
export class Deck {
  constructor(game, deck, countData, div, scale = 4, style = 'vertical') {
    this.game = game;
    this.countData = countData;
    this.div = div;
    this.scale = scale;
    this.style = style;
    this.deck = deck;
    this.div.classList.add('deck');
    this.div.classList.add(this.style === 'vertical' ? 'vertical' : 'horizontal');
    renderImage(`${this.deck}-back`, this.div, { scale: this.scale, pos: 'replace' });
    this.div.insertAdjacentHTML(
      'beforeend',
      `<div class="flipped-card"></div><div class="deck-marker"></div><div class="shuffle shuffle-1"></div><div class="shuffle shuffle-2"></div>`,
    );
    renderImage(`${this.deck}-back`, this.div.querySelector(`.shuffle-1`), { scale: this.scale, pos: 'replace' });
    renderImage(`${this.deck}-back`, this.div.querySelector(`.shuffle-2`), { scale: this.scale, pos: 'replace' });
    this.div
      .querySelector(`.${this.deck}-back`)
      .insertAdjacentHTML(
        'beforeend',
        `<div class="deck-counter dot dot--number counter">${this.countData.count}</div><div class="action-cost cost-with-text card-cost"></div>`,
      );
    this.drawing = [];
    this.partialDrawCard = null;
    this.topDiscard = null;
    this.setDiscard(this.topDiscard);
  }
  async shuffle(args) {
    return new Promise((resolve) => {
      this.topDiscard = null;
      this.setDiscard(this.game.gamedatas.decksDiscards?.[args.deck]?.name ?? this.game.gamedatas.decksDiscards[args.deck]?.[0]);
      this.div.querySelector(`.shuffle-1`).classList.add('enable');
      this.div.querySelector(`.shuffle-2`).classList.add('enable');
      if (this.partialCleanup) {
        this.partialCleanup();
        this.partialCleanup = null;
        this.div.querySelector('.flip-card').remove();
      }
      setTimeout(() => {
        this.div.querySelector(`.shuffle-1`).classList.remove('enable');
        this.div.querySelector(`.shuffle-2`).classList.remove('enable');
        resolve();
      }, 1500);
    });
  }
  updateDeckCounts(countData) {
    this.countData = countData;
    this.div.querySelector(`.deck-counter`).innerHTML = this.countData.count;
    this.div.querySelector(`.discard-counter`).innerHTML = this.countData.discardCount;
  }
  setDiscard(cardId) {
    if (this.cleanup) {
      this.cleanup();
      this.cleanup = null;
    }
    if (!cardId) {
      const { width, height } = getSpriteSize(`${this.deck}-back`, this.scale);
      this.div.querySelector(`.flipped-card`).innerHTML = `<div class="empty-discard" style="width: ${width}px;height: ${height}px;">${_(
        'Discard',
      )}</div>`;
    } else {
      renderImage(cardId, this.div.querySelector(`.flipped-card`), { scale: this.scale, pos: 'replace' });
      this.cleanup = addClickListener(this.div.querySelector(`.flipped-card`), cardId, this.discardTooltip(cardId));
    }
    this.div
      .querySelector(`.flipped-card`)
      .insertAdjacentHTML('beforeend', `<div class="discard-counter dot dot--number counter">${this.countData.discardCount}</div>`);
    this.topDiscard = cardId;
  }
  updateMarker({ tokens }) {
    const marker = this.div.querySelector(`.deck-marker`);
    marker.innerHTML = '';
    tokens?.forEach((token) => {
      renderImage(token, marker, { scale: 2, pos: 'replace' });
      if (token === 'trap') {
        this.game.addHelpTooltip({
          node: marker.querySelector(`.${token}`),
          text: _('If rolling equal or greater than a Danger! cards life, trap it to remove it and this token from the game.'),
        });
      }
    });
  }
  async drawCard(cardId, partial = false) {
    this.drawing.push([cardId, partial]);
    if (this.drawing.length === 1) {
      await this._drawCard(...this.drawing[0]);
    }
  }
  async _drawCard(cardId, partial = false) {
    if (!this.partialDrawCard) {
      if (!partial) {
        this.isDrawing = true;
      }
      await this.partialDraw(cardId);
    }
    if (!partial) {
      await this.finishPartialDraw(cardId);
    } else {
      this.drawing.splice(0, 1);
      if (this.drawing.length > 0) {
        await this._drawCard(...this.drawing[0]);
      }
    }
  }
  discardTooltip(cardId, isPartial = false) {
    return () => {
      this.game.tooltip.show();
      const {
        frame: { w, h },
        rotate,
      } = getAllData()[cardId];

      renderImage(cardId, this.game.tooltip.renderByElement(), {
        withText: true,
        scale: (rotate ? h : w) < 300 ? 1 : 2,
        pos: 'replace',
      });
      if (!isPartial) {
        this.game.tooltip
          .renderByElement()
          .insertAdjacentHTML('beforeend', `<div id="see-all" class="see-all see-all-button">${_('See All')}</div>`);
        addClickListener($('see-all'), _('See All'), () => {
          this.game.tooltip.renderByElement().innerHTML = '';
          const cardInnerTooltip = new Tooltip(this.game.tooltip.renderByElement());
          const renderItem = (name, elem) => {
            elem.insertAdjacentHTML('beforeend', `<div class="token ${name}"></div>`);
            renderImage(name, elem.querySelector(`.token.${name}`), { scale: this.scale / 2, pos: 'replace' });
            this.game.addHelpTooltip({
              node: elem.querySelector(`.token.${name}`),
              tooltipText: name,
              tooltipElem: cardInnerTooltip,
            });
          };
          this.game.gamedatas.decksDiscards[this.deck].forEach((name) => {
            renderItem(name, this.game.tooltip.renderByElement());
          });
        });
      }
    };
  }
  async partialDraw(cardId) {
    this.div.insertAdjacentHTML(
      'beforeend',
      `<div class="flip-card">
  <div class="flip-card-inner">
  <div class="flip-card-front"></div>
  <div class="flip-card-back"></div>
  </div>
  </div>`,
    );
    renderImage(`${this.deck}-back`, this.div.querySelector(`.flip-card-front`), { scale: this.scale, pos: 'replace' });
    renderImage(cardId, this.div.querySelector(`.flip-card-back`), { scale: this.scale, pos: 'replace' });
    await this.game.wait(100);
    this.div.querySelector(`.flip-card`).classList.add('flip');
    this.div.querySelector(`.flip-card`).classList.add('partial');
    await this.game.wait(1000);
    this.partialDrawCard = cardId;
    this.partialCleanup = addClickListener(this.div.querySelector(`.flip-card`), cardId, this.discardTooltip(cardId, true));
  }
  isAnimating() {
    return this.isDrawing;
  }
  async finishPartialDraw(cardId) {
    if (this.partialCleanup) {
      this.partialCleanup();
      this.partialCleanup = null;
    }
    this.partialDrawCard = null;
    this.div.querySelector(`.flip-card`).classList.remove('partial');
    this.div.querySelector(`.flip-card`).classList.add('discard');
    await this.game.wait(1000);
    this.setDiscard(cardId);
    this.isDrawing = false;
    this.div.querySelector('.flip-card').remove();
    this.drawing.splice(0, 1);
    if (this.drawing.length > 0) {
      await this._drawCard(...this.drawing[0]);
    }
  }
}
