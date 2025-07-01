import { addClickListener } from './clickable';

export class Selector {
  constructor(gamePlayAreaElem) {
    gamePlayAreaElem.insertAdjacentHTML(
      'beforeend',
      `<div id="selector-overlay"></div>
      <div id="selector-show-hide" class="dmtnt__show-hide-button">
        <i class="fa fa-eye-slash fa-3x" aria-hidden="true"></i>
      </div>`,
    );
    this.selectorElem = $('selector-overlay');
    this.showHideElem = $('selector-show-hide');
    this.screenName = null;
    this.hide(null);
    addClickListener(this.showHideElem, 'Show/Hide', () => {
      const fa = this.showHideElem.querySelector('.fa');
      if (this.selectorElem.style.visibility === 'hidden') {
        this.selectorElem.style.visibility = '';
        fa.classList.add('fa-eye-slash');
        fa.classList.remove('fa-eye');
      } else {
        this.selectorElem.style.visibility = 'hidden';
        fa.classList.remove('fa-eye-slash');
        fa.classList.add('fa-eye');
      }
    });
  }
  show(name) {
    if (this.screenName) {
      this.selectorElem.innerHTML = '';
    }
    this.screenName = name;
    this.selectorElem.style.display = '';
    this.showHideElem.style.display = '';
  }
  hide(name) {
    if (this.screenName === name) {
      this.selectorElem.style.display = 'none';
      this.showHideElem.style.display = 'none';
      this.selectorElem.innerHTML = '';
      this.screenName = null;
      const fa = this.showHideElem.querySelector('.fa');
      this.selectorElem.style.visibility = '';
      fa.classList.add('fa-eye-slash');
      fa.classList.remove('fa-eye');
    }
  }
  renderByHTML(html) {
    this.selectorElem.innerHTML = html;
  }
  renderByElement() {
    return this.selectorElem;
  }
}
