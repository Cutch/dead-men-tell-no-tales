import { renderImage } from './images';

export class Tweening {
  constructor(game, container) {
    this.game = game;
    this.container = container;
    this.tweenId = 0;
  }
  getBoundingClientRect(elem) {
    const { x, y, left, top, right, bottom, width, height } = elem.getBoundingClientRect();
    return {
      x: x + window.scrollX,
      y: y + window.scrollY,
      left: left + window.scrollX,
      top: top + window.scrollY,
      right: right + window.scrollX,
      bottom: bottom + window.scrollY,
      width,
      height,
    };
  }
  async addTween(elem1, elem2, image, scale, count = 1, time = 500) {
    if (elem1 && elem2) {
      for (let i = 0; i < count; i++) {
        this.game.slideTemporaryObject(renderImage(image, null, { scale, pos: 'return' }), this.container, elem1, elem2, time, i * 300);
      }
      await this.game.wait((count - 1) * 300 + time);
    }
  }
  addDestroyTween(elem1, elem2, time = 500) {
    if (elem1 && elem2) this.game.slideToObjectAndDestroy(elem1, elem2, time);
  }
}
