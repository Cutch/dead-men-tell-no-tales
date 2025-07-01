import { renderImage } from './images';
import dojo from 'dojo';
export class InfoOverlay {
  constructor(game, gamePlayAreaElem) {
    this.game = game;
    this.messageId = 0;
    gamePlayAreaElem.insertAdjacentHTML(
      'beforeend',
      `<div class="info-overlay" style="display:none">
          <div class="body">
            <div class="messages"></div>
            <div class="turn-order"></div>
          </div>
        </div>`,
    );
    this.infoOverlayElem = gamePlayAreaElem.querySelector('.info-overlay');
    this.infoOverlayMessages = gamePlayAreaElem.querySelector('.info-overlay .messages');
    this.infoOverlayTurnNumber = gamePlayAreaElem.querySelector('.info-overlay .turn-order');
    this.activeMessages = [];
  }
  updateTurnOrder() {
    if (this.game.gamedatas.characters.length < 4 || gameui.gamedatas.gamestate.name === 'characterSelect') {
      this.infoOverlayElem.style.display = 'none';
      return;
    }
    this.infoOverlayElem.style.display = '';
    const activeCharacter = this.game.gamedatas.characters.find((d) => d.isActive)?.name;
    const html = this.game.gamedatas.game.turnOrder
      .map((characterName, i) => {
        const character = this.game.gamedatas.characters.find((c) => c.name === characterName);
        return (
          `<span class="${characterName == activeCharacter ? 'active' : ''} ${character?.incapacitated ? 'incapacitated' : ''} turn-character" style="--color: #${character?.playerColor}">${characterName}</span>` +
          (i === 0 ? '&nbsp;<span class="first-player-marker"></span>' : '')
        );
      })
      .join('&nbsp;<i class="fa fa-arrow-right" aria-hidden="true"></i>&nbsp;');
    this.infoOverlayTurnNumber.innerHTML = html;
    const elem = this.infoOverlayTurnNumber.querySelector(`.first-player-marker`);
    if (elem)
      renderImage('skull', elem, {
        scale: 20,
        pos: 'replace',
        card: false,
      });
  }
  addMessage(args) {
    const { usedActionId, usedActionName, character_name } = args;
    const translatedActionName = this.game.getActionMappings()[usedActionId];
    if (!translatedActionName) return;
    this.messageId++;
    const id = `message_${this.messageId}`;
    const text = dojo.string.substitute(_('${character_name} used ${action}'), {
      character_name,
      action: usedActionName ? _(usedActionName) : translatedActionName,
    });
    this.infoOverlayMessages.insertAdjacentHTML('beforeend', `<div class="message" id="${id}">${text}</div>`);
    this.activeMessages.push(id);
    if (this.activeMessages.length > 3) {
      const [removedId] = this.activeMessages.splice(0, 1);
      $(removedId).classList.remove('spawn');
      setTimeout(() => {
        $(removedId).remove();
      }, 2000);
    }
    setTimeout(() => {
      $(id).classList.add('spawn');
    }, 0);
  }
}
