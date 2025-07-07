const sideNames = ['one', 'two', 'three', 'four', 'five', 'six'];
import dojo from 'dojo';
export class Dice {
  constructor(game, div, color = 'black') {
    this.game = game;
    this.div = div;
    this.queue = [];
    this.isRolling = false;
    // if (color === 'black') color = '#0d0d0d';
    // else if (color === 'yellow') color = '#eea92e';
    // else if (color === 'red') color = '#9d2124';
    let html = `<div class="dice-container ${color}"><div class="dice-mover"><div class="dice-base"></div><div class="dice">`;
    for (let i = 1; i <= 6; i++) {
      html += `<div class="side-wrapper ${sideNames[i - 1]}"><div class="side">`;
      html += `<div class="image"></div>`;
      html += `</div></div>`;
    }
    html += `</div></div></div>`;
    this.div.insertAdjacentHTML('beforeend', html);
    this.container = this.div.querySelector('.dice-container');
    this.diceBase = this.div.querySelector('.dice-mover');
    this.dice = this.div.querySelector('.dice');
    this.dice.addEventListener('animationEnd', () => {
      this.dice.style.animationPlayState = 'paused';
      this.diceBase.style.animationPlayState = 'paused';
    });
  }
  _show() {
    this.container.style['visibility'] = 'unset';
    this.dice.classList.add('no-roll');
  }
  _hide() {
    this.container.style['visibility'] = 'hidden';
  }
  _set({ roll }) {
    this.dice.style['transition'] = 'transform 0.75s';
    this.dice.style.animationPlayState = 'running';
    for (let i = 1; i <= 6; i++) {
      this.dice.classList.remove('show-' + i);
    }
    this.dice.classList.add('show-' + roll);

    const animation = new dojo.Animation({
      curve: [0, 1],
      duration: 750,
      onEnd: () => {
        this.dice.style['transition'] = 'unset';
      },
    });

    this.game.bgaPlayDojoAnimation(animation);
  }
  _roll({ args, callback }) {
    this.isRolling = true;
    this.container.style['visibility'] = 'unset';
    this.dice.style['transition'] = 'transform 1s';
    this.diceBase.style['transition'] = 'left 1s, top 1s';
    this.diceBase.style.animationPlayState = 'running';
    this.dice.style.animationPlayState = 'running';
    this.dice.classList.add('show-' + args.roll);
    this.diceBase.style['left'] = '20%';
    this.diceBase.style['top'] = '20%';

    const animation = new dojo.Animation({
      curve: [0, 1],
      duration: 3000,
      onEnd: () => {
        this.container.style['visibility'] = 'hidden';
        this.dice.style['transition'] = 'unset';
        this.diceBase.style['transition'] = 'unset';
        this.diceBase.style['left'] = '80%';
        this.diceBase.style['top'] = '80%';
        for (let i = 1; i <= 6; i++) {
          this.dice.classList.remove('show-' + i);
        }
        callback && callback();
        if (this.queue.length > 0) {
          setTimeout(() => {
            this._roll(this.queue.shift());
          }, 250);
        } else {
          this.isRolling = false;
        }
      },
    });

    this.game.bgaPlayDojoAnimation(animation);
  }
  async roll(args) {
    return new Promise((resolve) => {
      if (this.queue.length == 0 && !this.isRolling) {
        this._roll({ args, callback: resolve });
      } else {
        this.queue.push({ args, callback: resolve });
      }
    });
  }
}
