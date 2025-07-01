// try to handle passive events
let passiveEventSupported = false;
try {
  const opts = Object.defineProperty({}, 'passive', {
    get() {
      passiveEventSupported = true;
    },
  });
  window.addEventListener('test', null, opts);
} catch (e) {}
// if they are supported, setup the optional params
// FALSE doubles as the default CAPTURE value

const passiveEvent = passiveEventSupported ? { capture: false, passive: true } : false;

export const addPassiveListener = (type, callback) => {
  window.addEventListener(type, callback, passiveEvent);
  return () => {
    window.removeEventListener(type, callback, passiveEvent);
  };
};

export const scrollArrow = (content, arrowElem) => {
  const { y, height } = content.getBoundingClientRect();
  arrowElem.style['top'] = `calc(${Math.max(
    0,
    window.scrollY - (window.scrollY + y) - height + window.innerHeight / 2,
  )}px / var(--bga-game-zoom, 1))`;
  const startHeight = 125 + (document.querySelector('.mobile_version #right-side')?.getBoundingClientRect()?.height ?? 0);
  arrowElem.style['display'] =
    Math.max(0, window.scrollY - (window.scrollY + y) - height + window.innerHeight / 2) == 0 || window.scrollY <= startHeight
      ? 'none'
      : '';
};

export const isStudio = () => {
  return window.location.hostname === 'studio.boardgamearena.com';
};
