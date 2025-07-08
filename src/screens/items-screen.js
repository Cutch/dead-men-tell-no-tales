import { addClickListener, addPassiveListener, renderImage, scrollArrow } from '../utils/index';
export class ItemsScreen {
  constructor(game) {
    this.game = game;
  }
  getSelection() {
    return this.selection;
  }
  hasError() {
    return false;
  }
  scroll() {
    scrollArrow(this.isElem, this.arrowElem);
  }
  hide() {
    this.game.selector.hide('itemsScreen');
  }
  renderCharacter(container, characterId) {
    container.insertAdjacentHTML('beforeend', `<div class="character-image"></div>`);
    renderImage(characterId + '-token', container.querySelector(`.character-image`), {
      scale: 3,
    });
    addClickListener(container.querySelector(`.character-image`), characterId, () => {
      this.game.tooltip.show();
      renderImage(characterId, this.game.tooltip.renderByElement(), { withText: true, type: 'tooltip-character', pos: 'replace' });
    });
  }
  show(gameData) {
    this.selection = null;
    let isElem = document.querySelector(`#is-items .items`);
    if (!isElem) {
      this.game.selector.show('itemsScreen');
      this.game.selector.renderByElement().insertAdjacentHTML(
        'beforeend',
        `<div id="items-screen" class="dlid__container">
            <div id="is-items" class="dlid__container"><h3>${_('Select Item')}</h3><div class="items"></div></div>
            <div class="arrow"><i class="fa fa-arrow-up fa-5x" aria-hidden="true"></i></div>
        </div>`,
      );
      isElem = document.querySelector(`#is-items .items`);
      this.isElem = isElem;
      this.arrowElem = document.querySelector(`#items-screen .arrow`);
      this.arrowElem.style['display'] = 'none';
      this.cleanup = addPassiveListener('scroll', () => this.scroll());
    }
    isElem.innerHTML = '';
    const renderItem = (id, characterId, elem, selectCallback) => {
      elem.insertAdjacentHTML('beforeend', `<div class="token id${id}"></div>`);
      renderImage(id, document.querySelector(`#items-screen .token.id${id}`), { scale: 1.5, pos: 'append' });
      if (characterId) this.renderCharacter(document.querySelector(`#items-screen .token.id${id}`), characterId);
      addClickListener(document.querySelector(`#items-screen .token.id${id}`), this.game.data[id].options.name, selectCallback);
      this.game.addHelpTooltip({
        node: document.querySelector(`#items-screen .token.id${id}`),
        tooltipText: id,
      });
    };
    (
      gameData.selectionState?.items ?? [...gameData.availableItems.map((d) => ({ id: d })), ...gameData.characters.map((d) => d.item)]
    ).forEach(({ id, characterId }) => {
      renderItem(id, characterId, isElem, () => {
        if (this.selection) {
          document.querySelector(`#items-screen .token.id${this.selection.id} .item-card`).style['outline'] = '';
        }
        this.selection = { id, characterId };
        if (this.selection) {
          document.querySelector(`#items-screen .token.id${id} .item-card`).style['outline'] = `5px solid #fff`;
        }
      });
    });
    this.scroll();
  }
}
