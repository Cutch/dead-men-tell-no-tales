import { addClickListener } from './clickable';
export class Tooltip {
  constructor(gamePlayAreaElem) {
    gamePlayAreaElem.insertAdjacentHTML(
      'beforeend',
      `<div class="tooltip-overlay">
      <div class="scroll-overlay">
        <div class="close"><i class="fa fa-times fa-2x" aria-hidden="true"></i></div>
        <div class="inner">
          <div class="body">
        </div>
        </div></div></div>`,
    );
    this.tooltipElem = gamePlayAreaElem.querySelector('.tooltip-overlay');
    this.tooltipBody = gamePlayAreaElem.querySelector('.tooltip-overlay .body');
    this.isScrolling = false;
    this.hide();

    addClickListener(gamePlayAreaElem.querySelector('.tooltip-overlay .close'), 'Close', this.handleClick);
  }
  handleEscapeKey = (e) => {
    if (e.key === 'Escape') {
      this.hide();
    }
  };
  handleClick = () => {
    this.hide();
  };
  handleClickOutside = () => {
    if (!this.isScrolling) {
      this.hide();
    }
  };
  scroll = () => {
    this.isScrolling = true;
    clearTimeout(this.scrollTimeout);

    this.scrollTimeout = setTimeout(() => {
      this.isScrolling = false;
    }, 500); // Adjust the timeout duration as needed
  };
  show() {
    this.tooltipElem.style.display = '';
    document.body.style.overflow = 'hidden';
    setTimeout(() => {
      document.addEventListener('click', this.handleClickOutside);
      document.addEventListener('keydown', this.handleEscapeKey);
      this.tooltipElem.addEventListener('scroll', this.scroll);
    }, 0);
    this.tooltipElem.focus();
  }
  hide() {
    if (!this.tooltipBody.querySelector('.tooltip-overlay:not([style*="display: none"])')) {
      document.removeEventListener('click', this.handleClickOutside);
      document.removeEventListener('keydown', this.handleEscapeKey);
      this.tooltipElem.removeEventListener('scroll', this.scroll);
      this.tooltipElem.style.display = 'none';
      this.tooltipBody.innerHTML = '';
      const objects = [...document.querySelectorAll('.tooltip-overlay')];
      if (objects.filter((d) => d.style.display != 'none').length == 0) document.body.style.overflow = '';
    }
  }
  renderByHTML(html) {
    this.tooltipBody.innerHTML = html;
  }
  renderByElement() {
    return this.tooltipBody;
  }
}
