import { getAllData } from '../assets';
export const getSpriteSize = (name, scale = 2) => {
  const {
    frame: { w, h },
    rotate,
  } = getAllData()[name];
  if (rotate) return { width: h / scale, height: w / scale };
  else return { width: w / scale, height: h / scale };
};
const scaleLookups = {
  'tooltip-character': 2,
  'tooltip-item': 2,
  'tooltip-day-event': 2,
  'tooltip-card': 1,
  'tooltip-night-event': 1,
  'tooltip-hindrance': 2,
  'tooltip-unlock': 0.75,
};
export const renderText = ({ name }) => {
  const text = getAllData()[name]?.options?.text;
  return text
    ? `<div class="tooltip-text">${text.map((d) => (d.title ? `<div class="tooltip-line"><b class="tooltip-title">${d.title}</b></div>` : `<div class="tooltip-line">${d}</div>`)).join('')}</div>`
    : '';
};
export const renderImage = (
  name,
  div = null,
  {
    type = '',
    scale = 2,
    pos = 'append',
    card = true,
    css: extraCss = '',
    baseCss: extraBaseCss = '',
    overridePos = null,
    rotate: rotateAPI = 0,
    centered = false,
    withText = false,
    textOnly = false,
  } = {},
) => {
  if (scaleLookups[type]) scale = scaleLookups[type];
  // example of adding a div for each player
  if (!getAllData()[name]) throw new Error(`Missing image ${name}`);
  let html = '';
  if (!textOnly) {
    const {
      meta: {
        css = '',
        size: { w: spriteWidth, h: spriteHeight },
      },
      frame: { x, y, w, h },
      rotate,
    } = getAllData()[name];
    let scaledX = Math.round(x / scale);
    let scaledY = Math.round(y / scale);
    let scaledWidth = Math.round(w / scale);
    let scaledHeight = Math.round(h / scale);
    const scaledSpriteWidth = Math.ceil(spriteWidth / scale);
    const scaledSpriteHeight = Math.ceil(spriteHeight / scale);
    if (overridePos) {
      scaledX = scaledX + scaledWidth * overridePos.x;
      scaledY = scaledY + scaledHeight * overridePos.y;
      scaledWidth = scaledWidth * Math.abs(overridePos.w - overridePos.x);
      scaledHeight = scaledHeight * Math.abs(overridePos.h - overridePos.y);
    }
    if (rotate || rotateAPI)
      html = `<div class="tooltip-image-and-text ${extraBaseCss}"><div class="card-rotator" style="transform: rotate(${rotate || rotateAPI}deg) ${
        centered ? ';transform-origin: center;' : `translate(${scaledWidth + 3}px, ${-scaledHeight / 2}px);transform-origin:top;`
      }height: ${scaledWidth}px;width: ${scaledHeight}px;">
    <div name="${name}-${rotate || rotateAPI}" data-scale="${scale}" class="image card ${css} ${extraCss} ${
      card ? 'card' : ''
    } ${name}" style="background-size: ${scaledSpriteWidth}px ${scaledSpriteHeight}px;background-position: -${scaledX}px -${scaledY}px;width: ${scaledWidth}px;height: ${
      scaledHeight - 1
    }px;"></div>
    </div>`;
    else
      html = `<div class="tooltip-image-and-text ${extraBaseCss}"><div name="${name}" data-scale="${scale}" class="image ${css} ${extraCss} ${
        card ? 'card' : ''
      } ${name}" style="background-size: ${scaledSpriteWidth}px ${scaledSpriteHeight}px;background-position: -${scaledX}px -${scaledY}px;width: ${scaledWidth}px;height: ${scaledHeight}px;"></div>`;
  }
  if (withText || textOnly) {
    html += renderText({ name });
    html += '</div>';
  } else {
    html += '</div>';
  }
  if (pos === 'replace') div.innerHTML = html;
  else if (pos === 'return') return html;
  else if (pos === 'insert') div.insertAdjacentHTML('afterbegin', html);
  else div.insertAdjacentHTML('beforeend', html);
};
