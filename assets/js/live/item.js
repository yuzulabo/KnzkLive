const kit = require('../components/kanzakit');
const api = require('../components/api');

class item {
  static updateMoneyDisp(item) {
    let point = 0;
    if (item === 'emoji') {
      point += parseInt(kit.elemId('item_emoji_count').value) * 2;
      point += kit.elemId('item_emoji_dir').value === 'random' ? 10 : 0;
      point += kit.elemId('item_emoji_spin').checked ? 10 : 0;
      point += kit.elemId('item_emoji_big').checked ? 10 : 0;
    } else if (item === 'voice') {
      const id = kit.elemId('item_voice').value;
      point = kit.elemId('item_voice_' + id).dataset.point;
    }
    kit.elemId(`item_${item}_point`).textContent = point;
  }

  static checkEmoji(obj) {
    kit.elemId('item_emoji').value = obj.title;
    kit.elemId('emojiDropdown_img').src = obj.src;
  }

  static buyItem(type, is_confirmed = false) {
    const body = {
      live_id: config.live.id,
      type,
      confirm: is_confirmed ? 1 : 0
    };
    if (type === 'emoji') {
      body['count'] = parseInt(kit.elemId('item_emoji_count').value);
      body['dir'] = kit.elemId('item_emoji_dir').value;
      body['emoji'] = kit.elemId('item_emoji').value;
      body['spin'] = kit.elemId('item_emoji_spin').checked ? 1 : 0;
      body['big'] = kit.elemId('item_emoji_big').checked ? 1 : 0;
    } else if (type === 'voice') {
      body['voice'] = kit.elemId('item_voice').value;
    } else if (type === 'knzk_kongyo_kami') {
    } else {
      return null;
    }

    api.request('client/item_buy', 'POST', body).then(json => {
      if (json['confirm']) {
        if (confirm(`${json['point']}KP消費します。よろしいですか？`)) {
          const p = $('.now_user_point');
          p.html(parseInt(p.html()) - json['point']);
          item.buyItem(type, true);
        }
      }
      if (json['success']) {
        $('#itemModal').modal('hide');
      }
    });
  }
}

module.exports = item;
