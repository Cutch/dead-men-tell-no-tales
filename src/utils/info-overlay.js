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
  addMessage(args) {
    const { usedActionId, usedActionName, character_id } = args;
    const translatedActionName = this.game.getActionMappings()[usedActionId];
    if (!translatedActionName) return;
    this.messageId++;
    const id = `message_${this.messageId}`;
    const text = dojo.string.substitute(_('${character_name} used ${action}'), {
      character_id,
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
