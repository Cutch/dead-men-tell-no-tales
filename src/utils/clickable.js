export const addClickListener = function (elem, name, callback, addPadding = false) {
  elem.tabIndex = '0';
  const click = (e) => {
    if (!elem.classList.contains('disabled')) {
      callback(e);
      e.preventDefault();
      e.stopPropagation();
    }
  };
  elem.addEventListener('click', click);
  const keydown = (e) => {
    if (e.key === 'Enter' && !elem.classList.contains('disabled')) {
      callback(e);
      e.preventDefault();
    }
  };
  elem.addEventListener('keydown', keydown);
  elem.classList.add('dmtnt__clickable');
  if (addPadding) {
    elem.classList.add('dmtnt__clickable-padding');
  }
  elem.role = 'button';
  elem['aria-label'] = name;
  return () => {
    elem.classList.remove('dmtnt__clickable');
    elem.classList.remove('dmtnt__clickable-padding');
    elem.tabIndex = '';
    elem.removeEventListener('click', click);
    elem.removeEventListener('keydown', keydown);
  };
};
