import { addClickListener, addPassiveListener, renderImage, scrollArrow } from '../utils/index';

export class CardSelectionScreen {
  constructor(game) {
    this.game = game;
  }
  getSelectedId() {
    return this.itemSelected;
  }
  hasError() {
    return false;
  }
  scroll() {
    scrollArrow(this.cardSelectionElem, this.arrowElem);
  }
  hide() {
    this.game.selector.hide('cardSelection');
    this.itemSelected = null;
  }
  show(gameData) {
    this.itemSelected = null;
    const deckScaling = {
      'day-event': 2,
      'mental-hindrance': 2,
      'physical-hindrance': 2,
    };
    let cardSelectionElem = document.querySelector(`#card-selection-screen .cards`);
    if (!cardSelectionElem) {
      this.game.selector.show('cardSelection');
      this.game.selector.renderByElement().insertAdjacentHTML(
        'beforeend',
        `<div id="card-selection-screen" class="dmtnt__container">
            <div id="card-selection-screen" class="dmtnt__container"><h3>${_(
              gameData.selectionState.title ?? 'Select a Card',
            )}</h3><div class="cards"></div></div>
            <div class="arrow"><i class="fa fa-arrow-up fa-5x" aria-hidden="true"></i></div>
        </div>`,
      );
      cardSelectionElem = document.querySelector(`#card-selection-screen .cards`);
      this.cardSelectionElem = cardSelectionElem;
      this.arrowElem = document.querySelector(`#card-selection-screen .arrow`);
      this.arrowElem.style['display'] = 'none';
      this.cleanup = addPassiveListener('scroll', () => this.scroll());
    }
    cardSelectionElem.innerHTML = '';
    const renderItem = (cardName, deckName, elem, selectCallback) => {
      elem.insertAdjacentHTML(
        'beforeend',
        `<div class="token-number-counter ${cardName}">
            <div class="token ${cardName}"></div>
          <div>`,
      );
      renderImage(cardName, elem.querySelector(`.token.${cardName}`), { scale: deckScaling[deckName] ?? 1, pos: 'insert' });
      addClickListener(elem.querySelector(`.token.${cardName}`), 'Card', () => selectCallback());
    };
    gameData.selectionState.cards.forEach(({ deck, id: card }) => {
      renderItem(card, deck, cardSelectionElem, () => {
        if (this.itemSelected) {
          document.querySelector(`#card-selection-screen .token.${this.itemSelected} .card`).style['outline'] = '';
        }
        this.itemSelected = card;
        if (this.itemSelected) {
          document.querySelector(`#card-selection-screen .token.${card} .card`).style['outline'] = `5px solid #fff`;
        }
      });
    });
    this.scroll();
  }
}
