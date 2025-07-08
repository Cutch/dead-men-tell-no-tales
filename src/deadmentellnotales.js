/**
 *------
 * BGA framework: Gregory Isabelli & Emmanuel Colin & BoardGameArena
 * DeadMenTellNoTales implementation : © <Your name here> <Your email address here>
 *
 * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
 * See http://en.boardgamearena.com/#!doc/Studio for more information.
 * -----
 *
 * deadmentellnotales.js
 *
 * DeadMenTellNoTales user interface script
 *
 * In this file, you are describing the logic of your user interface, in Javascript language.
 *
 */
import dojo from 'dojo'; // Loads the dojo object using dojoConfig if needed
import declare from 'dojo/_base/declare'; // Add 'declare' to dojo if needed
import Gamegui from 'ebg/core/gamegui'; // Loads Gamegui class onto ebg.core.gamegui if needed
import 'ebg/counter'; // Loads Counter class onto ebg.counter if needed
import { getAllData } from './assets/index';
import { CardSelectionScreen } from './screens/card-selection-screen';
import { CharacterSelectionScreen } from './screens/character-selection-screen';
import { ItemsScreen } from './screens/items-screen';
import { addClickListener, Deck, Dice, isStudio, renderImage, renderText, Selector, Tooltip, Tweening } from './utils/index';
import { Map } from './utils/map';

declare('bgagame.deadmentellnotales', Gamegui, {
  constructor: function () {
    // Used For character selection
    this.reloadShown = false;
    this.selectedCharacters = [];
    this.mySelectedCharacters = [];
    this.data = [];
    this.selector = null;
    this.tooltip = null;
    this.decks = {};
    this.clickListeners = [];
    this.cardSelectionScreen = new CardSelectionScreen(this);
    this.characterSelectionScreen = new CharacterSelectionScreen(this);
    this.itemsScreen = new ItemsScreen(this);

    this.currentResources = { prevResources: {}, resources: {} };
    this.animations = [];
  },
  getActionMappings() {
    return {
      actMove: _('Move'),
      actFightFire: _('Fight Fire'),
      actEliminateDeckhand: _('Eliminate Deckhand'),
      actPickupToken: _('Pickup Token'),
      actRest: _('Rest'),
      actIncreaseBattleStrength: _('Increase Strength'),
      actDrop: _('Drop'),
      actSwapItem: _('Swap Item'),
      actSelectCharacter: _('Select Character'),
      actSelectCard: _('Select Card'),
      actPlaceTile: _('Place Tile'),
      actUndo: _('Undo'),
      actEndTurn: _('End Turn'),
    };
  },

  /*
          setup:
          
          This method must set up the game user interface according to current game situation specified
          in parameters.
          
          The method is called each time the game interface is displayed to a player, ie:
          _ when the game starts
          _ when a player refreshes the game page (F5)
          
          "gameData" argument contains all datas retrieved by your "getAllDatas" PHP method.
      */
  updatePlayers: function (gameData) {
    // If character selection, keep removing characters
    const characters = gameData?.characters
      ? Object.values(gameData?.characters)
      : Object.values(this.selectedCharacters).sort((a, b) => a.id.localeCompare(b.id));
    // if (gameData.gamestate?.name === 'characterSelect' || this.refreshCharacters) {
    //   document.querySelectorAll('.character-side-container').forEach((el) => el.remove());
    //   document.querySelectorAll('.player-card').forEach((el) => el.remove());
    //   if (this.gamedatas.characters) characters = this.gamedatas.characters;
    //   this.refreshCharacters = false;
    // }
    const scale = 3;
    characters.forEach((character, i) => {
      // Player side board
      const playerPanel = this.getPlayerPanelElement(character.playerId);
      const item = character.item;
      const characterSideId = `player-side-${character.playerId}-${character.id}`;
      let playerSideContainer = $(characterSideId);
      if (!playerSideContainer) {
        playerPanel.insertAdjacentHTML(
          'beforeend',
          `<div id="${characterSideId}" class="character-side-container">
            <div class="character-image"></div>
            <div>
              <div class="character-name">${this.data[character.id].options.name}</div>
              <div class="fatigue line"><div class="fa6 fa6-solid fa6-person-running"></div><span class="label">${_(
                'Fatigue',
              )}: </span><span class="value"></span></div>
              <div class="actions line"><div class="fa6 fa6-solid fa6-bolt"></div><span class="label">${_(
                'Actions',
              )}: </span><span class="value"></span></div>
              <div class="strength line"><div class="fa6 fa6-solid fa6-hand-fist"></div><span class="label">${_(
                'Strength',
              )}: </span><span class="value"></span></div>
              <div class="item line"><div class="fa6 fa6-solid fa6-toolbox"></div><span class="label">${_('Item')}: </span><span class="value"></span></div>
            </div>
          </div>`,
        );
        playerSideContainer = $(characterSideId);
        addClickListener(playerSideContainer.querySelector(`.character-name`), character.id, () => {
          this.tooltip.show();
          renderImage(character.id, this.tooltip.renderByElement(), { withText: true, type: 'tooltip-character', pos: 'replace' });
        });
        renderImage(character.id + '-token', playerSideContainer.querySelector(`.character-image`), {
          scale: 2,
          pos: 'replace',
          styles: { '--color': character.characterColor },
        });
        addClickListener(playerSideContainer.querySelector(`.character-image`), character.id, () => {
          this.tooltip.show();
          renderImage(character.id, this.tooltip.renderByElement(), { withText: true, type: 'tooltip-character', pos: 'replace' });
        });
      }
      playerSideContainer.querySelector(`.fatigue .value`).innerHTML = `${character.fatigue ?? 0}/${character.maxFatigue ?? 0}`;
      playerSideContainer.querySelector(`.actions .value`).innerHTML = `${character.actions ?? 0}/${character.maxActions ?? 0}`;
      const cutlassCount = character.tokenItems['cutlass'] ?? 0;
      const strength = cutlassCount + parseInt(character.tempStrength ?? 0, 10);
      playerSideContainer.querySelector(`.strength .value`).innerHTML = `${strength ?? 0}`;

      playerSideContainer.querySelector(`.item .value`).innerHTML = item
        ? `<span class="item-item item-${item.itemId}">${_(item.name)}</span>`
        : _('None');
      if (gameData.gamestate?.name !== 'characterSelect') playerSideContainer.style['background-color'] = character?.isActive ? '#fff' : '';
      if (item)
        addClickListener(playerSideContainer.querySelector(`.item-${item.itemId}`), _(item.name), () => {
          this.tooltip.show();
          renderImage(item.id, this.tooltip.renderByElement(), {
            withText: true,
            type: 'tooltip-item',
            pos: 'replace',
            rotate: item.rotate,
            centered: true,
          });
        });

      // Player main board
      if (gameData.gamestate.name !== 'characterSelect') {
        const container = $(`players-container`);
        if (!$(`player-${character.id}`)) {
          container.insertAdjacentHTML(
            'beforeend',
            `<div id="player-${character.id}" class="player-card">
                <div class="card">
                  <div class="extra-token"></div>
                  <div class="fatigue-dial"></div>
                  <div class="actions-marker"></div>
                  <div class="cutlass-markers"></div>
                  <div class="strength-marker"></div>
                  <div class="character"></div>
                </div>
                <div class="card-container">
                  <div class="item"></div>
                </div>
              </div>`,
          );
          renderImage(`character-board`, document.querySelector(`#player-${character.id} .card`), { scale, pos: 'insert' });
        }
        document.querySelector(`#player-${character.id} .card`).style['outline'] = character?.isActive
          ? `5px solid #fff` //#${character.playerColor}
          : '';

        const extraTokenElem = document.querySelector(`#player-${character.id} .extra-token`);
        extraTokenElem.innerHTML = '';
        const degs = [-2, 19, 35, 57, 76, 98, 118, 138, 156, 181, 208, 225, 248, 272, 294, 320, 341];
        const dial = document.querySelector(`#player-${character.id} .fatigue-dial .dial-base`);
        if (dial) dial.style.setProperty('--rotate', `${degs[character.fatigue]}deg`);
        else
          renderImage('dial', document.querySelector(`#player-${character.id} .fatigue-dial`), {
            scale,
            pos: 'replace',
            card: false,
            styles: { '--rotate': `${degs[character.fatigue]}deg` },
          });
        renderImage('token-action', document.querySelector(`#player-${character.id} .actions-marker`), {
          scale,
          pos: 'replace',
          card: false,
        });

        document
          .querySelector(`#player-${character.id} .actions-marker .image`)
          .insertAdjacentHTML('beforeend', `<div class="counter dot dot--number">${character.actions ?? 0}</div>`);

        character.actions ?? 0;

        renderImage(character.id + '-token', document.querySelector(`#player-${character.id} .strength-marker`), {
          scale,
          pos: 'replace',
          styles: { '--color': character.characterColor },
        });
        const cutlassMarkersElem = document.querySelector(`#player-${character.id} .cutlass-markers`);
        cutlassMarkersElem.innerHTML = '';
        for (let i = 0; i < cutlassCount; i++) {
          renderImage('cutlass', cutlassMarkersElem, {
            scale,
            pos: 'replace',
            baseCss: 'cutlass-marker',
            styles: { '--color': character.characterColor, left: `${i * 62 + 18}px` },
          });
        }
        document.querySelector(`#player-${character.id} .strength-marker`).style.left = `${strength * 62 + 18}px`;
        const characterElem = document.querySelector(`#player-${character.id} .character`);
        renderImage(character.id, characterElem, { scale: 5, pos: 'replace' });
        addClickListener(characterElem, character.id, () => {
          this.tooltip.show();
          renderImage(character.id, this.tooltip.renderByElement(), { withText: true, type: 'tooltip-character', pos: 'replace' });
        });

        if (item) {
          renderImage(item.id, document.querySelector(`#player-${character.id} .item`), {
            scale: scale,
            pos: 'replace',
          });
          addClickListener(document.querySelector(`#player-${character.id} .item`), _(item.id), () => {
            this.tooltip.show();
            renderImage(item.id, this.tooltip.renderByElement(), { withText: true, type: 'tooltip-item', pos: 'replace' });
          });
        } else {
          document.querySelector(`#player-${character.id} .item`).innerHTML = '';
        }
      }
    });

    const selections = $('player_boards');
    [...selections.children].forEach((elem) => {
      if (elem.id?.includes('overall_player_board_')) {
        elem.style.order = this.gamedatas.players[elem.id.replace('overall_player_board_', '')].player_no;
      } else if (elem.id == 'token-container') {
        elem.style.order = 5;
      }
    });
  },
  enableClick: function (elem) {
    if (elem.classList.contains('disabled')) {
      elem.classList.remove('disabled');
    }
  },
  disableClick: function (elem) {
    if (!elem.classList.contains('disabled')) elem.classList.add('disabled');
  },
  noForResourceChange: function (gameData, resourceName) {
    const prevResources = gameData.prevResources;
    return (
      this.currentResources['prevResources'][resourceName] == prevResources[resourceName] &&
      this.currentResources['resources'][resourceName] == gameData.resources[resourceName] &&
      this.currentResources['prevResources'][resourceName + '-cooked'] == prevResources[resourceName + '-cooked'] &&
      this.currentResources['resources'][resourceName + '-cooked'] == gameData.resources[resourceName + '-cooked']
    );
  },
  updateItems: function (gameData) {
    // Shared Resource Pool
    // Available Resource Pool
    let availableElem = document.querySelector(`#items-container .items`);
    if (!availableElem) {
      $('game_play_area').insertAdjacentHTML(
        'beforeend',
        `<div id="items-container" class="dmtnt__container"><h3>${_('Items')}</h3><div class="items"></div></div>`,
      );
      availableElem = document.querySelector(`#items-container .items`);
    }
    availableElem.innerHTML = '';
    gameData.availableItems?.forEach((name) => this.updateItem(name, availableElem));
    if (!gameData.availableItems?.length) {
      availableElem.innerHTML = `<b>${_('None')}</b>`;
    }
  },
  updateItem: function (name, elem) {
    elem.insertAdjacentHTML('beforeend', `<div class="token ${name}"></div>`);
    renderImage(name, elem.querySelector(`.token.${name}`), { scale: 2, pos: 'insert' });
    addClickListener(elem.querySelector(`.token.${name}`), name, () => {
      this.tooltip.show();
      renderImage(name, this.tooltip.renderByElement(), { withText: true, pos: 'insert', type: 'tooltip-item' });
    });
  },
  setupBoard: function (gameData) {
    this.firstPlayer = Object.values(gameui.gamedatas.players).find((d) => d.player_no == 1).id;
    // Main board

    this.map = new Map(this, gameData);
    // renderImage(`board`, document.querySelector(`#board-container > .board`), { scale: 2, pos: 'insert' });
  },
  setupDecks: function (gameData, playArea) {
    const decks = [
      { name: 'revenge', expansion: 'base', scale: 2 },
      { name: 'tile', expansion: 'base', scale: 1.5, style: 'noDiscard' },
    ].filter((d) => this.expansions.includes(d.expansion));

    playArea.insertAdjacentHTML(
      'beforeend',
      `<div class="decks-container"><h3>${_('Decks')}</h3><div class="decks">${decks.map((d) => `<div class="deck-${d.name}"></div>`).join('')}</div></div>`,
    );
    decks.forEach(({ name: deck, scale, style }) => {
      if (!this.decks[deck] && gameData.decks[deck]) {
        this.decks[deck] = new Deck(this, deck, gameData.decks[deck], playArea.querySelector(`.deck-${deck}`), scale, style);
        if (!this.decks[deck].isAnimating() && gameData.decksDiscards)
          this.decks[deck].setDiscard(gameData.decksDiscards[deck]?.name ?? gameData.decksDiscards[deck]?.[0]);
        if (gameData.game.partials && gameData.game.partials[deck]) {
          this.decks[deck].drawCard(gameData.game.partials[deck].id, true);
        }
      }
    });
  },
  setupCharacterSelections: function (gameData) {
    const playArea = $('game_play_area');
    playArea.parentElement.insertAdjacentHTML('beforeend', `<div id="character-selector" class="dmtnt__container"></div>`);
    const elem = $('character-selector');
    if (gameData.gamestate.name === 'characterSelect') playArea.style.display = 'none';
    else elem.style.display = 'none';
    Object.keys(this.data)
      .filter((d) => this.data[d].options.type === 'character')
      .sort()
      .forEach((characterId) => {
        renderImage(characterId, elem, { scale: 1.5, pos: 'append' });
        addClickListener(elem.querySelector(`.${characterId}`), characterId, () => {
          const saved = [...this.mySelectedCharacters];
          const i = this.mySelectedCharacters.indexOf(characterId);
          if (i >= 0) {
            // Remove selection
            this.mySelectedCharacters.splice(i, 1);
          } else {
            if (this.mySelectedCharacters.length >= this.selectCharacterCount) {
              this.mySelectedCharacters[this.mySelectedCharacters.length - 1] = characterId;
            } else {
              this.mySelectedCharacters.push(characterId);
            }
          }
          this.bgaPerformAction('actCharacterClicked', {
            character1: this.mySelectedCharacters?.[0],
            character2: this.mySelectedCharacters?.[1],
            character3: this.mySelectedCharacters?.[2],
            character4: this.mySelectedCharacters?.[3],
            character5: this.mySelectedCharacters?.[4],
          }).catch(() => {
            this.mySelectedCharacters = saved;
          });
        });
        this.addHelpTooltip({
          node: elem.querySelector(`.${characterId}`),
          tooltipText: characterId,
        });
      });
  },
  updateCharacterSelections: function (gameData) {
    const elem = $('character-selector');
    const myCharacters = this.selectedCharacters
      .filter((d) => d.playerId == gameui.player_id)
      .map((d) => d.id)
      .sort((a, b) => this.mySelectedCharacters.indexOf(a) - this.mySelectedCharacters.indexOf(b));
    this.mySelectedCharacters = myCharacters;
    const characterLookup = this.selectedCharacters.reduce((acc, d) => ({ ...acc, [d.id]: d }), {});
    elem.querySelectorAll('.character-card').forEach((card) => {
      const character = characterLookup[card.getAttribute('name')];
      console.log(character, characterLookup, card.getAttribute('name'));
      if (character) {
        card.style.setProperty('--player-color', '#' + character.playerColor);
        card.classList.add('selected');
        if (character.playerId != gameui.player_id) this.disableClick(card);
      } else {
        card.classList.remove('selected');
        this.enableClick(card);
      }
    });
    this.updatePlayers(gameData);
  },
  setup: function (gameData) {
    dojo.subscribe('addMoveToLog', gameui, () => {
      const addButtonListener = (node) => {
        addClickListener(node, 'Card', () => {
          this.tooltip.show();
          renderImage(node.getAttribute('data-id'), this.tooltip.renderByElement(), {
            withText: true,
            ...(node.getAttribute('data-type') ? { type: 'tooltip-' + node.getAttribute('data-type') } : {}),
            pos: 'replace',
          });
        });
      };
      const nodes = document.querySelectorAll(`.dmtnt__log-button:not(.dmtnt__clickable)`);
      setTimeout(() => {
        nodes.forEach((node) => {
          node.innerHTML = _(node.innerHTML);
          addButtonListener(node);
        });
      }, 0);
    });

    $('game_play_area_wrap').classList.add('dmtnt');
    $('right-side').classList.add('dmtnt');
    this.replayFrom = new URLSearchParams(window.location.search).get('replayFrom');
    this.expansionList = gameData.expansionList;
    this.expansion = gameData.expansion;
    const expansionI = this.expansionList.indexOf(this.expansion);
    this.expansions = this.expansionList.slice(0, expansionI + 1);
    this.difficulty = gameData.difficulty;
    this.trackDifficulty = gameData.trackDifficulty;
    this.data = Object.keys(getAllData()).reduce((acc, k) => {
      const d = getAllData()[k];
      d.options = d.options ?? {};
      if (d.options.expansion && this.expansionList.indexOf(d.options.expansion) > expansionI) return acc;
      return { ...acc, [k]: d };
    }, {});

    const playArea = $('game_play_area');
    this.tweening = new Tweening(this, playArea);
    this.selector = new Selector(playArea);
    this.tooltip = new Tooltip($('game_play_area_wrap'));
    this.setupCharacterSelections(gameData);
    this.setupBoard(gameData);
    this.dice = new Dice(this, this.map.container);
    window.dice = this.dice;
    // this.dice.roll(5);
    // renderImage(`board`, playArea);
    playArea.insertAdjacentHTML('beforeend', `<div id="players-container" class="dmtnt__container"></div>`);
    this.updatePlayers(gameData);
    // Setting up player boards
    this.updateItems(gameData);
    this.setupDecks(gameData, playArea);

    // Setup game notifications to handle (see "setupNotifications" method below)
    this.setupNotifications();
  },
  updateGameDatas: function (gameData) {
    if (gameData?.version && this.gamedatas.version < gameData?.version && !this.reloadShown) {
      this.infoDialog(_('There is a new version available.'), _('Reload'), () => window.location.reload());
      this.reloadShown = true;
    }
    const clone = { ...gameData };
    delete clone.gamestate;
    Object.assign(this.gamedatas, clone);
  },
  isActive: function () {
    return (
      this.getActivePlayers()
        .map((d) => d.toString())
        .includes(this.player_id.toString()) || gameui.isPlayerActive()
    );
  },
  ///////////////////////////////////////////////////
  //// Game & client states

  // onEnteringState: this method is called each time we are entering into a new game state.
  //                  You can use this method to perform some user interface changes at this moment.
  //
  onEnteringState: async function (stateName, args = {}) {
    args.args = args.args ?? {};
    args.args['gamestate'] = { name: stateName };
    if (args.args) {
      this.updateGameDatas(args.args);
    }
    const isActive = this.isActive();
    if (isStudio())
      console.log('Entering state: ' + stateName, args, isActive, this.getActivePlayers(), gameui.isPlayerActive(), this.player_id);
    switch (stateName) {
      case 'itemSelection':
        if (isActive) this.itemsScreen.show(args.args);
        break;
      case 'finalizeTile':
        this.map.update(this.gamedatas);
        break;
      case 'placeTile':
        if (isActive) this.map.setNewCard(this.gamedatas.newTile.id);
        // if (isActive) this.cardSelectionScreen.show(args.args);
        break;
      case 'cardSelection':
        if (isActive) this.cardSelectionScreen.show(args.args);
        break;
      case 'characterSelect':
        this.selectedCharacters = args.args.characters ?? [];
        this.updateCharacterSelections(args.args);
        break;
      case 'playerTurn':
        if (args.args.characters) this.updatePlayers(args.args);
        this.updateItems(args.args);
        break;
      // case 'drawCard':
      //   if (!args.args.resolving) {
      //     this.decks[args.args.deck].drawCard(args.args.card.id);
      //     this.decks[args.args.deck].updateDeckCounts(args.args.decks[args.args.deck]);
      //   }
      //   break;
    }
  },

  // onLeavingState: this method is called each time we are leaving a game state.
  //                 You can use this method to perform some user interface changes at this moment.
  //
  onLeavingState: async function (stateName) {
    if (isStudio()) console.log('Leaving state: ' + stateName);
    switch (stateName) {
      case 'itemSelection':
        this.itemsScreen.hide();
        break;
      case 'placeTile':
        this.map.clearNewCard();
        // if (isActive) this.cardSelectionScreen.show(args.args);
        break;
      case 'cardSelection':
        this.cardSelectionScreen.hide();
        break;
      case 'characterSelect':
        dojo.style('character-selector', 'display', 'none');
        dojo.style('game_play_area', 'display', '');
        this.refreshCharacters = true;
        this.selectedCharacters = [];
        break;
    }
  },

  // onUpdateActionButtons: in this method you can manage "action buttons" that are displayed in the
  //                        action status bar (ie: the HTML links in the status bar).
  //
  getActionSuffixHTML: function (action) {
    let suffix = '';
    if (action['name_suffix']) suffix += ` ${action['name_suffix']}`;
    if (action['skillOption']) suffix += ` (${_(action['skillOption'].name)})`;
    else if (action['character'] != null && !action['global']) suffix += ` (${action['character']})`;
    else if (action['characterId'] != null && !action['global']) suffix += ` (${action['characterId']})`;
    if (action['actions'] != null) suffix += ` <i class="fa6 fa6-solid fa6-bolt dmtnt__stamina"></i> ${action['actions']}`;
    if (action['fatigue'] != null) suffix += ` <i class="fa6 fa6-solid fa6-person-running dmtnt__health"></i> ${action['fatigue']}`;
    if (action['random'] != null) suffix += ` <i class="fa6 fa6-solid fa6-dice-d6 dmtnt__dice"></i>`;
    if (action['perTurn'] != null)
      suffix += ` <i class="fa fa-sun-o dmtnt__sun"></i> ` + _('${remaining} left').replace(/\$\{remaining\}/, action['perTurn']);
    return suffix;
  },
  clearActionButtons: function () {
    this.removeActionButtons();
    this.clickListeners.forEach((clear) => clear());
    this.clickListeners = [];
    document.querySelectorAll(`.action-cost`).forEach((d) => {
      d.innerHTML = '';
      d.style.display = 'none';
    });
  },
  onUpdateActionButtons: async function (stateName, args) {
    this.updateGameDatas(args);
    const actions = args?.actions;
    const isActive = this.isActive();
    if (isStudio()) console.log('onUpdateActionButtons', isActive, stateName, actions);
    if (isActive && stateName && actions != null) {
      this.clearActionButtons();

      // Add test action buttons in the action status bar, simulating a card click:
      if (actions) {
        actions
          .sort((a, b) => {
            return (a?.actions ?? 9) - (b?.actions ?? 9);
          })
          .forEach((action) => {
            const actionId = action.action;
            const suffix = this.getActionSuffixHTML(action);
            return this.statusBar.addActionButton(`${this.getActionMappings()[actionId]}${suffix}`, () => {
              if (actionId === 'actUseSkill' || actionId === 'actUseItem') {
                this.clearActionButtons();
                Object.values(actionId === 'actUseSkill' ? this.gamedatas.availableSkills : this.gamedatas.availableItemSkills).forEach(
                  (skill) => {
                    const suffix = this.getActionSuffixHTML(skill);
                    this.statusBar.addActionButton(`${_(skill.name)}${suffix}`, () => {
                      return this.bgaPerformAction(actionId, { skillId: skill.id, skillSecondaryId: skill.secondaryId });
                    });
                  },
                );
                this.statusBar.addActionButton(_('Cancel'), () => this.onUpdateActionButtons(stateName, args), { color: 'secondary' });
              } else if (actionId === 'actPlaceTile') {
                this.bgaPerformAction('actPlaceTile', this.map.getNewCardPosition());
              } else if (actionId === 'actSwapItem') {
                this.bgaPerformAction('actInitSwapItem');
                // this.clearActionButtons();
                // this.itemsScreen.show(this.gamedatas);
                // this.statusBar.addActionButton(this.getActionMappings().actSwapItem + `${suffix}`, () => {
                //   this.bgaPerformAction('actSwapItem', this.itemsScreen.getSelection())
                //     .then(() => this.itemsScreen.hide())
                //     .catch(console.error);
                // });
                // this.statusBar.addActionButton(
                //   _('Cancel'),
                //   () => {
                //     this.onUpdateActionButtons(stateName, args);
                //     this.itemsScreen.hide();
                //   },
                //   { color: 'secondary' },
                // );
              } else if (actionId === 'actMove') {
                this.clearActionButtons();
                this.map.showTileSelectionScreen('actMove', this.gamedatas.moves);
                this.statusBar.addActionButton(this.getActionMappings().actMove + `${suffix}`, () => {
                  this.bgaPerformAction('actMove', this.map.getSelectionPosition())
                    .then(() => this.map.hideTileSelectionScreen())
                    .catch(console.error);
                });
                this.statusBar.addActionButton(
                  _('Cancel'),
                  () => {
                    this.onUpdateActionButtons(stateName, args);
                    this.map.hideTileSelectionScreen();
                  },
                  { color: 'secondary' },
                );
              } else if (actionId === 'actFightFire') {
                this.clearActionButtons();
                this.map.showTileSelectionScreen('actFightFire', this.gamedatas.fires);
                this.statusBar.addActionButton(this.getActionMappings().actFightFire + `${suffix}`, () => {
                  this.bgaPerformAction('actFightFire', this.map.getSelectionPosition())
                    .then(() => this.map.hideTileSelectionScreen())
                    .catch(console.error);
                });
                this.statusBar.addActionButton(
                  _('Cancel'),
                  () => {
                    this.onUpdateActionButtons(stateName, args);
                    this.map.hideTileSelectionScreen();
                  },
                  { color: 'secondary' },
                );
              } else if (actionId === 'actEliminateDeckhand') {
                this.clearActionButtons();
                this.map.showDeckhandSelection();
                this.statusBar.addActionButton(this.getActionMappings().actEliminateDeckhand + `${suffix}`, () => {
                  this.bgaPerformAction('actEliminateDeckhand', { data: JSON.stringify(this.map.getDeckhandSelection()) })
                    .then(() => this.map.hideDeckhandSelection())
                    .catch(console.error);
                });
                this.statusBar.addActionButton(
                  _('Cancel'),
                  () => {
                    this.onUpdateActionButtons(stateName, args);
                    this.map.hideDeckhandSelection();
                  },
                  { color: 'secondary' },
                );
              } else {
                return this.bgaPerformAction(actionId);
              }
            });
          });
      }
      const addSelectionCancelButton = () => {
        if (isActive && this.gamedatas.selectionState?.cancellable === true)
          this.statusBar.addActionButton(
            _('Cancel'),
            () => {
              this.bgaPerformAction('actCancel').then(() => this.selector.hide());
            },
            { color: 'secondary' },
          );
      };
      switch (stateName) {
        case 'characterSelection':
          this.statusBar.addActionButton(this.getActionMappings().actSelectCharacter, () => {
            this.bgaPerformAction('actSelectCharacter', { characterId: this.characterSelectionScreen.getSelectedId() });
          });
          addSelectionCancelButton();
          break;
        case 'cardSelection':
          this.statusBar.addActionButton(this.getActionMappings().actSelectCard, () => {
            this.bgaPerformAction('actSelectCard', { cardId: this.cardSelectionScreen.getSelectedId() });
          });
          addSelectionCancelButton();
          break;
        case 'itemSelection':
          this.statusBar.addActionButton(_('Select Item'), () => {
            this.bgaPerformAction('actSelectItem', { itemId: this.itemsScreen.getSelection()?.id });
          });
          addSelectionCancelButton();
          break;
        case 'interrupt':
          if (!this.gamedatas.availableSkills.some((d) => d.cancellable === false))
            this.statusBar.addActionButton(_('Skip'), () => this.bgaPerformAction('actDone'), { color: 'secondary' });
          break;
        case 'postEncounter':
          this.statusBar.addActionButton(_('Done'), () => this.bgaPerformAction('actDone'), { color: 'secondary' });
          break;
        case 'characterSelect':
          this.selectCharacterCount = args.selectionCount;
          this.statusBar.addActionButton(_('Confirm ${x} character(s)').replace('${x}', this.selectCharacterCount), () =>
            this.bgaPerformAction('actChooseCharacters'),
          );
          this.statusBar.addActionButton(_('Randomize') + ` <i class="fa6 fa6-solid fa6-dice-d6 dmtnt__dice"></i>`, () => {
            const saved = [...this.mySelectedCharacters];
            const otherCharacters = this.selectedCharacters.filter((d) => d.playerId != gameui.player_id).map((d) => d.id);
            const validCharacters = Object.keys(this.data).filter(
              (d) => this.data[d].options.type === 'character' && !otherCharacters.includes(d),
            );
            this.mySelectedCharacters = [];
            for (let i = 0; i < this.selectCharacterCount; i++) {
              let choice = validCharacters[Math.floor(Math.random() * validCharacters.length)];
              while (!choice || this.mySelectedCharacters.includes(choice)) {
                choice = validCharacters[Math.floor(Math.random() * validCharacters.length)];
              }
              this.mySelectedCharacters.push(choice);
            }
            this.bgaPerformAction('actCharacterClicked', {
              character1: this.mySelectedCharacters?.[0],
              character2: this.mySelectedCharacters?.[1],
              character3: this.mySelectedCharacters?.[2],
              character4: this.mySelectedCharacters?.[3],
              character5: this.mySelectedCharacters?.[4],
            }).catch(() => {
              this.mySelectedCharacters = saved;
            });
          });
          break;
        case 'playerTurn':
          if (isActive) {
            if (this.gamedatas.canUndo)
              this.statusBar.addActionButton(_('Undo'), () => this.bgaPerformAction('actUndo'), { color: 'secondary' });
            this.statusBar.addActionButton(
              _('End Turn'),
              () => this.confirmationDialog(_('End Turn'), () => this.bgaPerformAction('actEndTurn')),
              { color: 'secondary' },
            );
          }
          break;
      }
      // if (isActive && this.gamedatas.cancellable === true)
      //   this.statusBar.addActionButton(
      //     _('Cancel'),
      //     () => {
      //       this.bgaPerformAction('actCancel').then(() => this.selector.hide());
      //     },
      //     { color: 'secondary' },
      //   );
    } else if (!this.isSpectator && stateName) {
      const skipOthersActions = () => {
        if (!this.gamedatas.isRealTime)
          this.statusBar.addActionButton(
            _("Skip Other's Selection"),
            () => {
              this.bgaPerformAction('actForceSkip', null, { checkAction: false });
            },
            { color: 'red' },
          );
      };
      const backAction = () => {
        this.statusBar.addActionButton(
          _('Back'),
          () => {
            this.bgaPerformAction('actUnBack', null, { checkAction: false });
          },
          { color: 'secondary' },
        );
      };
      switch (stateName) {
        case 'characterSelect':
          backAction();
          break;
        case 'interrupt':
          if (!this.gamedatas.availableSkills.some((d) => d.cancellable === false))
            if (gameui.gamedatas.activeTurnPlayerId == gameui.player_id && !this.gamedatas.isRealTime) {
              actions
                .sort((a, b) => (a?.actions ?? 9) - (b?.actions ?? 9))
                .forEach((action) => {
                  const actionId = action.action;
                  if (actionId === 'actUseSkill' || actionId === 'actUseItem') {
                    return (actionId === 'actUseSkill' ? this.gamedatas.availableSkills : this.gamedatas.availableItemSkills)?.forEach(
                      (skill) => {
                        const suffix = this.getActionSuffixHTML(skill);
                        this.statusBar.addActionButton(`${_(skill.name)}${suffix}`, () => {}, { disabled: true });
                      },
                    );
                  }
                });
              skipOthersActions();
            }
          break;
      }
    }
  },

  addHelpTooltip: function ({ node, text = '', tooltipText = '', iconCSS, tooltipElem = this.tooltip }) {
    // game.addTooltip(id, helpString, actionString);
    if (!node.querySelector('.tooltip')) {
      node.insertAdjacentHTML(
        'beforeend',
        `<div class="tooltip"><div class="dot"><i class="${iconCSS ?? 'fa fa-question'}"></i></div></div>`,
      );

      addClickListener(
        node.querySelector('.tooltip'),
        'Tooltip',
        () => {
          tooltipElem.show();
          tooltipElem
            .renderByElement()
            .insertAdjacentHTML(
              'beforeend',
              `<div class="tooltip-box"><i class="fa fa-question-circle-o fa-2x" aria-hidden="true"></i><span>${tooltipText ? renderText({ name: tooltipText }) : text}</span></div>`,
            );
        },
        true,
      );
    }
  },
  ///////////////////////////////////////////////////
  //// Reaction to cometD notifications

  /*
          setupNotifications:
          
          In this method, you associate each of your game notifications with your local method to handle it.
          
          Note: game notification names correspond to "notifyAllPlayers" and "notifyPlayer" calls in
                your deadmentellnotales.game.php file.
      
      */
  setupNotifications: function () {
    // this.bgaSetupPromiseNotifications({
    //   prefix: 'notif_', // default is 'notif_'
    //   minDuration: 500,
    //   minDurationNoText: 1,
    //   logger: console.log, // show notif debug informations on console. Could be console.warn or any custom debug function (default null = no logs)
    //   ignoreNotifications: ['updateAutoPlay'], // the notif_updateAutoPlay function will be ignored by bgaSetupPromiseNotifications. You'll need to subscribe to it manually
    //   // onStart: (notifName, msg, args) => $('pagemaintitletext').innerHTML = `${_('Animation for:')} ${msg}`,
    //   // onEnd: (notifName, msg, args) => $('pagemaintitletext').innerHTML = '',
    // });
    // dojo.subscribe('startSelection', this, 'notif_startSelection');
    dojo.subscribe('characterClicked', this, 'notif_characterClicked');
    dojo.subscribe('updateCharacterData', this, 'notif_updateCharacterData');
    dojo.subscribe('updateActionButtons', this, 'notif_updateActionButtons');
    dojo.subscribe('notify', this, 'notif_actionNotification');

    // Example 1: standard notification handling
    // dojo.subscribe( 'tokenUsed', this, "notif_tokenUsed" );

    // Example 2: standard notification handling + tell the user interface to wait
    //            during 3 seconds after calling the method in order to let the players
    //            see what is happening in the game.

    dojo.subscribe('zombieBackDLD', this, 'notif_zombieBack');
    dojo.subscribe('zombieChange', this, 'notif_zombieChange');
    dojo.subscribe('activeCharacter', this, 'notif_tokenUsed');
    dojo.subscribe('updateMap', this, 'notif_updateMap');
    dojo.subscribe('tokenUsed', this, 'notif_tokenUsed');
    dojo.subscribe('shuffle', this, 'notif_shuffle');
    dojo.subscribe('cardDrawn', this, 'notif_cardDrawn');
    dojo.subscribe('rollBattleDie', this, 'notif_rollBattleDie');
    dojo.subscribe('resetNotifications', this, 'notif_resetNotifications');
    this.notifqueue.setSynchronous('cardDrawn', 1000);
    this.notifqueue.setSynchronous('rollBattleDie', 3250);
    this.notifqueue.setSynchronous('shuffle', 1500);
    this.notifqueue.setSynchronous('tokenUsed', 300);
  },
  notificationWrapper: async function (notification) {
    notification.args = notification.args ?? {};
    const state = notification.gamestate ?? notification.args.gamestate;
    if (notification.gameData) {
      notification.gameData.gamestate = state;
    }
    if (notification.args) {
      notification.args.gamestate = state;
    }
    if (notification.args.gameData) {
      notification.args.gameData.gamestate = state;
    }
    if (notification.gameData) {
      notification.gameData.gamestate = state;
    }
    if (notification.args.gameData) {
      this.updateGameDatas(notification.args.gameData);
    }
    return this.replayFrom > notification.move_id;
  },
  notif_actionNotification: async function (notification) {
    const usedActionId = notification.args.usedActionId;
    if (usedActionId) {
    }
  },
  // notif_startSelection: async function (notification) {
  //   await this.notificationWrapper(notification);
  //   if (isStudio()) console.log('notif_startSelection', notification);
  //   this.onEnteringState(notification.args.stateName, notification.args.gameData);
  // },
  notif_resetNotifications: async function (notification) {
    await this.notificationWrapper(notification);
    const lastMoveId = parseInt(notification.args.moveId, 10);
    for (const logId of Object.keys(gameui.log_to_move_id)) {
      const moveId = parseInt(gameui.log_to_move_id[logId], 10);
      if (moveId > lastMoveId) {
        try {
          $(`log_${logId}`).remove();
        } catch (e) {}
        try {
          $(`dockedlog_${logId - 1}`).remove();
        } catch (e) {}
      }
    }
  },
  notif_rollBattleDie: async function (notification) {
    if (await this.notificationWrapper(notification)) return;
    if (isStudio()) console.log('notif_rollBattleDie', notification);
    return this.dice.roll(notification.args);
  },
  notif_cardDrawn: async function (notification) {
    if (isStudio()) console.log('notif_cardDrawn', notification);
    const gameData = notification.args.gameData;
    if (await this.notificationWrapper(notification)) {
      if (!notification.args.partial) this.decks[notification.args.deck].setDiscard(notification.args.card.id);
    } else {
      await this.decks[notification.args.deck].drawCard(notification.args.card.id, notification.args.partial);
    }
    this.decks[notification.args.deck].updateDeckCounts(gameData.decks[notification.args.deck]);
  },
  notif_shuffle: async function (notification) {
    if (await this.notificationWrapper(notification)) return;
    if (isStudio()) console.log('notif_shuffle', notification);
    const gameData = notification.args.gameData;
    this.decks[notification.args.deck].updateDeckCounts(gameData.decks[notification.args.deck]);
    return this.decks[notification.args.deck].shuffle(notification.args);
  },
  notif_zombieChange: async function (notification) {
    await this.notificationWrapper(notification);
    if (isStudio()) console.log('notif_zombieChange', notification);
    document.querySelectorAll('.character-side-container').forEach((node) => node.remove());
    document.querySelectorAll('#players-container .player-card').forEach((node) => node.remove());

    this.updatePlayers(notification.args.gameData);
  },
  notif_zombieBack: async function (notification) {
    await this.notificationWrapper(notification);
    if (isStudio()) console.log('notif_zombieBack', notification);
    $('zombieBack').style.display = 'none';
    document.querySelectorAll('.character-side-container').forEach((node) => node.remove());
    document.querySelectorAll('#players-container .player-card').forEach((node) => node.remove());

    this.updatePlayers(notification.args.gameData);
  },

  notif_updateActionButtons: async function (notification) {
    if (isStudio()) console.log('notif_updateActionButtons', notification);
    await this.notificationWrapper(notification);
    await this.onUpdateActionButtons(notification.args.gamestate.name, notification.args.gameData);
  },

  notif_characterClicked: async function (notification) {
    await this.notificationWrapper(notification);
    if (isStudio()) console.log('notif_characterClicked', notification);
    this.selectedCharacters = notification.args.gameData.characters ?? [];
    this.updateCharacterSelections(notification.args);
  },
  notif_updateMap: async function (notification) {
    await this.notificationWrapper(notification);
    if (isStudio()) console.log('notif_updateMap', notification);
    this.map.update(notification.args.gameData);
  },
  notif_updateCharacterData: async function (notification) {
    await this.notificationWrapper(notification);
    if (isStudio()) console.log('notif_updateCharacterData', notification);
    this.updatePlayers(notification.args.gameData);
    this.updateItems(notification.args.gameData);
    this.map.update(notification.args.gameData);
  },
  notif_tokenUsed: async function (notification) {
    await this.notificationWrapper(notification);
    if (isStudio()) console.log('notif_tokenUsed', notification);
    this.updateItems(notification.args.gameData);
  },
});
