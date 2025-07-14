import { addClickListener, addPassiveListener, renderImage, scrollArrow } from '../utils/index';

export class CharacterSelectionScreen {
  constructor(game) {
    this.game = game;
  }
  getSelectedId() {
    return this.characterSelected;
  }
  hasError() {
    return false;
  }
  scroll() {
    scrollArrow(this.characterSelectionElem, this.arrowElem);
  }
  hide() {
    this.game.selector.hide('characterSelection');
    this.characterSelected = null;
  }
  show(gameData) {
    this.characterSelected = null;
    let characterSelectionElem = document.querySelector(`#character-selection-screen .cards`);
    if (!characterSelectionElem) {
      this.game.selector.show('characterSelection');
      this.game.selector.renderByElement().insertAdjacentHTML(
        'beforeend',
        `<div id="character-selection-screen" class="dmtnt__container">
            <div id="character-selection-screen" class="dmtnt__container"><h3>${_(
              gameData.selectionState.title ?? 'Select a Character',
            )}</h3><div class="cards"></div></div>
            <div class="arrow"><i class="fa fa-arrow-up fa-5x" aria-hidden="true"></i></div>
        </div>`,
      );
      characterSelectionElem = document.querySelector(`#character-selection-screen .cards`);
      this.characterSelectionElem = characterSelectionElem;
      this.arrowElem = document.querySelector(`#character-selection-screen .arrow`);
      this.arrowElem.style['display'] = 'none';
      this.cleanup = addPassiveListener('scroll', () => this.scroll());
    }
    characterSelectionElem.innerHTML = '';
    const renderItem = (characterName, elem, selectCallback) => {
      elem.insertAdjacentHTML(
        'beforeend',
        `<div class="token-number-counter ${characterName}">
            <div class="token ${characterName}"></div>
          <div>`,
      );
      renderImage(characterName, elem.querySelector(`.token.${characterName}`), { scale: 2, pos: 'insert' });
      addClickListener(elem.querySelector(`.token.${characterName}`), characterName, () => selectCallback());
      this.game.addHelpTooltip({
        node: elem.querySelector(`.token.${characterName}`),
        tooltipText: characterName,
      });
    };
    gameData.selectionState.selectableCharacters.forEach((character) => {
      renderItem(character, characterSelectionElem, () => {
        if (this.characterSelected) {
          document.querySelector(`#character-selection-screen .token.${this.characterSelected} .card`).style['outline'] = '';
        }
        this.characterSelected = character;
        if (this.characterSelected) {
          document.querySelector(`#character-selection-screen .token.${character} .card`).style['outline'] = `5px solid #fff`;
        }
      });
    });
    this.scroll();
  }
}
