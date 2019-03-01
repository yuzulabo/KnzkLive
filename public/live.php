<?php
require_once("../lib/bootloader.php");

$id = s($_GET["id"]);
if (!$id) {
  http_response_code(421);
  exit("ERR:配信IDを入力してください。");
}

$live = getLive($id);
if (!$live) {
  http_response_code(404);
  exit("ERR:この配信は存在しません。");
}

$slot = getSlot($live["slot_id"]);
$my = getMe();
$blocking = blocking_user($live["user_id"], $_SERVER["REMOTE_ADDR"], $my ? $my["acct"] : null);
if ((!$my && $live["privacy_mode"] == "3") || !empty($blocking["is_blocking_watch"])) {
  http_response_code(403);
  exit("ERR:この配信は非公開です。| " . ($my ? "" : "<a href='".u("login")."'>ログイン</a>"));
}

if ($my["id"] != $live["user_id"] && $live["is_started"] == "0") {
  http_response_code(403);
  exit("ERR:この配信はまだ開始されていません。 | " . ($my ? "" : "<a href='".u("login")."'>ログイン</a>"));
}

if (isset($_POST["sensitive"])) $_SESSION["sensitive_allow"] = true;

$liveUser = getUser($live["user_id"]);

$new_live = ($liveUser["live_current_id"] !== 0 && $liveUser["live_current_id"] !== $live["id"]) ? getLive($liveUser["live_current_id"]) : null;
if (!empty($new_live) && ($new_live["privacy_mode"] !== 1 || $new_live["is_started"] !== 1)) $new_live = null;

$liveurl = liveUrl($live["id"]);

$vote = loadVote($live["id"]);
?>
<!doctype html>
<html lang="ja" data-page="live">
<head>
  <?php include "../include/header.php"; ?>
  <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.4.1/css/all.css" integrity="sha384-5sAR7xN1Nv6T6+dT2mhtzEpVJvfS3NScPQTrOxhwjIuvcA67KV2R5Jz6kr4abQsz" crossorigin="anonymous">
  <title id="title-name"><?=$live["name"]?> - <?=$env["Title"]?></title>

  <meta property="og:title" content="<?=$live["name"]?>"/>
  <meta property="og:type" content="website"/>
  <meta content="summary" property="twitter:card" />
  <meta property="og:url" content="<?=$liveurl?>"/>
  <meta property="og:image" content="<?=$liveUser["misc"]["avatar"]?>"/>
  <meta property="og:site_name" content="<?=$env["Title"]?>"/>
  <meta property="og:description" content="<?=s($live["description"])?>"/>
  <meta name="description" content="<?=s($live["description"])?> by <?=s($liveUser["name"])?>">

  <script>
    window.config.live = {
      id: <?=$live["id"]?>,
      hashtag_o: "<?=liveTag($live)?>",
      hashtag: " #" + this.hashtag_o + (config.account && config.account.domain === "twitter.com" ? " - <?=$liveurl?>" : ""),
      url: "<?=$liveurl?>",
      is_broadcaster: <?=$live["user_id"] === $liveUser["id"] ? "true" : "false"?>,
      created_at: "<?=dateHelper($live["created_at"])?>"
      account: {
        id: <?=$liveUser["id"]?>,
        acct: "<?=$liveUser["acct"]?>",
        name: "<?=$liveUser["name"]?>"
      }
    }
    window.onload = function() {
      window.live = knzk.live;
      live.ready();
<?php if (!$live["misc"]["able_comment"]) : ?>
      $(".comment_block").hide();
<?php endif; ?>
    }
  </script>
</head>
<body>
<?php $navmode = "fluid"; include "../include/navbar.php"; ?>
<?php if (!empty($new_live)) : ?>
  <div class="container">
    <div class="alert alert-info" role="alert">
      <h4>この配信者は現在配信中です！</h4>
      <a href="<?=liveUrl($new_live["id"])?>"><b><?=$new_live["name"]?></b></a> <small>(<?=date("Y/m/d H:i", strtotime($new_live["created_at"]))?> に開始)</small>
    </div>
  </div>
<?php endif; ?>
<?php if ($live["misc"]["is_sensitive"] && !isset($_SESSION["sensitive_allow"])) : ?>
<div class="container">
  <h1>警告！</h1>
  この配信はセンシティブな内容を含む配信の可能性があります。本当に視聴しますか？
  <p>
    「<b><?=$live["name"]?></b>」 by <?=$liveUser["name"]?>
  </p>
  <form method="post">
    <input type="hidden" name="csrf_token" value="<?=$_SESSION['csrf_token']?>">
    <input type="hidden" name="sensitive" value="1">
    <button type="submit" class="btn btn-danger btn-lg btn-block">:: 視聴する ::</button>
  </form>
</div>
<?php else : ?>
<div class="container-fluid">
  <div class="row">
    <div class="col-md-9">
      <div id="err_live" class="text-warning"></div>
      <div id="is_not_started" class="invisible">* この配信はまだ開始されていません。現在はあなたのみ視聴できます。<a href="<?=u("live_manage")?>">配信開始はこちらから</a></div>
      <?php if ($my["id"] === $live["user_id"]) : ?>
        <div class="text-warning">* これは自分の放送です。ハウリング防止の為自動でミュートしています。</div>
      <?php endif; ?>
      <div class="embed-responsive embed-responsive-16by9" id="live">
        <iframe class="embed-responsive-item" src="<?=u("live_embed")?>?id=<?=$id?>&rtmp=<?=$slot["server"]?>" allowfullscreen id="iframe" allow="autoplay; fullscreen"></iframe>
      </div>
      <span class="float-right">
        <span class="live-info" id="time"></span>
        <span class="live-info"><i class="fas fa-hat-wizard"></i> <b class="point_count"><?=$live["point_count"]?></b>KP</span>
        <span class="live-info"><i class="fas fa-comments"></i> <b id="comment_count"><?=s($live["comment_count"])?></b></span>

        <span id="count_open">
          <i class="fas fa-users"></i> <b class="count"><?=$live["viewers_count"]?></b> / <span class="max"><?=$live["viewers_max"]?></span>
        </span>
        <span id="count_end" class="invisible">
          総視聴者数: <span class="max"><?=$live["viewers_max"]?></span>人    最大同時視聴者数: <span id="max_c"><?=$live["viewers_max_concurrent"]?></span>人
        </span>
      </span>
      <br>
      <div class="float-right">
        <?php if ($live["is_live"] !== 0 && $my["id"] === $live["user_id"]) : ?>
          <button type="button" class="btn btn-outline-warning live_edit invisible" onclick="undo_edit_live()"><i class="fas fa-times"></i> 編集廃棄</button>
          <button type="button" class="btn btn-outline-success live_edit invisible" onclick="edit_live()" style="margin-right:10px"><i class="fas fa-check"></i> 編集完了</button>
        <?php endif; ?>
        <?php if (!empty($my) && $live["is_live"] !== 0) : ?>
          <button type="button" class="btn btn-outline-success" data-toggle="modal" data-target="#itemModal"><i class="fas fa-hat-wizard"></i> アイテム</button>
        <?php endif; ?>

        <?php if (donation_url($liveUser["id"], false) && $live["is_live"] !== 0) : ?>
          <button type="button" class="btn btn-outline-warning" data-toggle="modal" data-target="#chModal"><i class="fas fa-donate"></i> 支援 (CH)</button>
        <?php elseif (donation_url($liveUser["id"])) : ?>
          <a class="btn btn-outline-warning" href="<?=donation_url($liveUser["id"])?>" target="_blank"><i class="fas fa-donate"></i> 支援</a>
        <?php endif; ?>

        <button type="button" class="btn btn-link side-buttons" onclick="live.share.share()"><i class="fas fa-share-square"></i> 共有</button>
      </div>
      <p></p>
      <h4 id="live-name" class="live_info"><?=$live["name"]?></h4>

      <div class="input-group col-md-6 invisible live_edit" style="margin-bottom:20px">
        <div class="input-group-prepend">
          <span class="input-group-text" id="edit_title_label">タイトル</span>
        </div>
        <input type="text" class="form-control" placeholder="タイトル (100文字以下)" value="<?=$live["name"]?>" id="edit_name">
      </div>
      <span class="text-secondary">
        <?php if ($live["is_live"] !== 0) : ?>
          <?=date("Y/m/d H:i", strtotime($live["created_at"]))?> に開始
        <?php else : ?>
          <?=date("Y/m/d H:i", strtotime($live["created_at"]))?> - <?=date("Y/m/d H:i", strtotime($live["ended_at"]))?>
        <?php endif; ?>
      </span>
      <p id="live-description" class="live_info"><?=HTMLHelper($live["description"])?></p>

      <div class="input-group col-md-8 invisible live_edit">
        <div class="input-group-prepend">
          <span class="input-group-text">説明</span>
        </div>
        <textarea class="form-control" id="edit_desc" rows="4"><?=$live["description"]?></textarea>
      </div>

      <?php if ($live["is_live"] !== 0 && $my["id"] === $live["user_id"]) : ?>
      <hr>
      <?php include "../include/live/adminpanel.php"; ?>
      <?php endif; ?>
      <p>
        <a href="<?=u("report")?>?liveid=<?=$live["id"]?>" target="_blank" class="text-danger">配信を通報する</a>
      </p>
    </div>
    <div class="col-md-3" id="comment">
      <?php include "../include/live/comment.php"; ?>
    </div>
  </div>
</div>

<?php include "../include/live/modals.php"; ?>
<?php if ($my["id"] === $live["user_id"]) include "../include/live/add_blocking.php"; ?>
<script id="com_tmpl" type="text/x-handlebars-template">
  <div id="post_{{id}}" class="comment card mb-2">
    <div class="content card-body">
      <div class="float-left">
        <img src="{{account.avatar}}" class="avatar rounded" width="50" height="50" onclick="userDropdown(this, '{{id}}', '{{account.acct}}', '{{account.url}}')"/>
      </div>
      <div class="float-right card-text">
        <span onclick="userDropdown(this, '{{id}}', '{{account.acct}}', '{{account.url}}')" class="name text-truncate">
          {{#if donator_color}}
          <span class="badge badge-pill" style="background:{{donator_color}}">
          {{/if}}
          <b>{{account.display_name}}</b>
          {{#if donator_color}}
          </span>
          {{/if}}
        </span>
        <div class="postcontent card-text">
          {{{content}}}
        </div>
      </div>
    </div>
  </div>
</script>
<?php include "../include/footer.php"; ?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/handlebars.js/4.0.12/handlebars.min.js" integrity="sha256-qlku5J3WO/ehJpgXYoJWC2px3+bZquKChi4oIWrAKoI=" crossorigin="anonymous"></script>
<script>
  /*
  const inst = "<?=$env["masto_login"]["domain"]?>";
  let login_inst = "<?=isset($_SESSION["login_domain"]) ? s($_SESSION["login_domain"]) : ""?>";
  if (!login_inst) login_inst = inst;
  const hashtag_o = "<?=liveTag($live)?>";
  const hashtag = " #" + hashtag_o + (login_inst === "twitter.com" ? " via <?=$liveurl?>" : "");
  const token = "<?=$my && $_SESSION["token"] ? s($_SESSION["token"]) : ""?>";
  const config = {nw: [], nu: [], dn: {}};
  const acct = "<?=$my ? $my["acct"] : ""?>";
  var heartbeat, cm_ws, watch_data = {}, io, w_heartbeat;
  var api_header = {'content-type': 'application/json'};
  if (token) api_header["Authorization"] = 'Bearer ' + token;
  */
  var frame_url = "";

  function loadComment() {
    elemId("err_comment").className = "invisible";

    fetch('https://' + inst + '/api/v1/timelines/tag/' + hashtag_o, {
      headers: {'content-type': 'application/json'},
      method: 'GET'
    })
    .then(function(response) {
      if (response.ok) {
        return response.json();
      } else {
        throw response;
      }
    })
    .then(function(json) {
      let reshtml = "";
      let ws_url = 'wss://' + inst + '/api/v1/streaming/?stream=hashtag&tag=' + hashtag_o;

      cm_ws = new WebSocket(ws_url);
      cm_ws.onopen = function() {
        heartbeat = setInterval(() => cm_ws.send("ping"), 5000);
        cm_ws.onmessage = ws_onmessage;

        cm_ws.onclose = function() {
          clearInterval(heartbeat);
          loadComment();
        };
      };

      io = new WebSocket("<?=($env["is_testing"] ? "ws://localhost:3000/api/streaming" : "wss://" . $env["domain"] . $env["RootUrl"] . "api/streaming")?>/live/<?=s($live["id"])?>");
      io.onopen = function() {
        w_heartbeat = setInterval(function () {
          if (io.readyState !== 0 && io.readyState !== 1) io.close();
          io.send("ping");
        }, 5000);
      };

      io.onmessage = function (e) {
        const data = JSON.parse(e.data);
        if (data.type === "pong" || !data.payload) return;
        if (data.event === "delete") {
          const del_toot = elemId('post_' + data.payload);
          if (del_toot) del_toot.parentNode.removeChild(del_toot);
          return;
        }
        const msg = JSON.parse(data.payload);
        if (data.event === "prop") {
          if (msg.type === "vote_start") {
            elemId("vote_title").textContent = msg.title;
            elemId("vote1").textContent = msg.vote[0];
            elemId("vote2").textContent = msg.vote[1];
            if (msg.vote[2]) {
              elemId("vote3").textContent = msg.vote[2];
              $("#vote3").removeClass("invisible");
            } else {
              $("#vote3").addClass("invisible");
            }

            if (msg.vote[3]) {
              elemId("vote4").textContent = msg.vote[3];
              $("#vote4").removeClass("invisible");
            } else {
              $("#vote4").addClass("invisible");
            }

            elemId("prop_vote").className = "";
          } else if (msg.type === "vote_end") {
            elemId("prop_vote").className = "invisible";
            fetch('<?=u("api/client/vote/reset")?>?id=<?=s($live["id"])?>', {
              method: 'GET',
              credentials: 'include'
            });
          } else if (msg.type === "item") {
            if (msg.item_type === "knzk_kongyo") {
              const volume = localStorage.getItem('kplayer_volume');
              const mute = localStorage.getItem('kplayer_mute');
              const audio = new Audio('https://static.knzk.me/knzklive/kongyo.mp3');
              audio.volume = volume ? volume * 0.01 : 0.8;
              audio.muted = parseInt(mute === null ? 0 : mute);
              audio.play();
              return;
            }
            document.getElementById('iframe').contentWindow.run_item(msg.item_type, msg.item, 10);
          } else if (msg.type === "change_config") {
            if (msg.mode === "sensitive" && msg.result) {
              const frame = document.getElementById('iframe');
              frame_url = frame.src;
              frame.src = "";
              $('#sensitiveModal').modal('show');
            } else if (msg.mode === "comment") {
              if (msg.result) {
                $(".comment_block").show();
              } else {
                $(".comment_block").hide();
              }
            } else if (msg.mode === "ngs") {
              getNgs();
            }
          } else if (msg.type === "donate") {
            add_donator(msg);
          }
        } else if (data.event === "update") {
          ws_onmessage(msg, "update");
        }
      };

      io.onclose = function() {
        io = null;
        clearInterval(w_heartbeat);
        w_heartbeat = null;
        loadComment();
      };

      fetch('<?=u("api/client/comment_get")?>?id=<?=s($live["id"])?>', {
        method: 'GET',
        credentials: 'include'
      }).then(function(response) {
        if (response.ok) {
          return response.json();
        } else {
          throw response;
        }
      }).then(function(c) {
        if (c) {
          json = json.concat(c);
          json.sort(function(a,b) {
            return (Date.parse(a["created_at"]) < Date.parse(b["created_at"]) ? 1 : -1);
          });
        }
        if (json) {
          let i = 0;
          const tmpl = Handlebars.compile(document.getElementById("com_tmpl").innerHTML);
          while (json[i]) {
            if (config.np.indexOf(json[i]["id"]) === -1) {
              reshtml += check_data(json[i]) ? tmpl(buildCommentData(json[i], inst)) : "";
            }
            i++;
          }
        }

        elemId("comments").innerHTML = reshtml;
      }).catch(function(error) {
        console.error(error);
        elemId("err_comment").className = "text-danger";
      });
    })
    .catch(error => {
      console.log(error);
      elemId("err_comment").className = "text-danger";
    });
  }

  function ws_onmessage(message, mode = "") {
    let ws_resdata, ws_reshtml;
    if (mode) { //KnzkLive Comment
      ws_resdata = {};
      ws_resdata.event = mode;
      ws_reshtml = message;
    } else { //Mastodon
      ws_resdata = JSON.parse(message.data);
      ws_reshtml = JSON.parse(ws_resdata.payload);
    }

    if (ws_resdata.event === 'update') {
      if (ws_reshtml['id']) {
        elemId("comment_count").textContent = parseInt(elemId("comment_count").textContent) + 1;
        const tmpl = Handlebars.compile(document.getElementById("com_tmpl").innerHTML);
        if (check_data(ws_reshtml)) {
          elemId("comments").innerHTML = (tmpl(buildCommentData(ws_reshtml, inst))) + elemId("comments").innerHTML;
          document.getElementById('iframe').contentWindow.comment_view(ws_reshtml['content']);
        }
      }
    } else if (ws_resdata.event === 'delete') {
      var del_toot = elemId('post_' + ws_resdata.payload);
      if (del_toot) del_toot.parentNode.removeChild(del_toot);
    }
  }

  function update_money_disp(item) {
    let point = 0;
    if (item === "emoji") {
      point += parseInt(elemId("item_emoji_count").value) * 5;
      point += elemId("item_emoji_spin").checked ? 30 : 0;
      point += elemId("item_emoji_big").checked ? 30 : 0;
    }
    elemId("item_" + item + "_point").textContent = point;
  }

  function item_buy(type, is_confirmed = false) {
    const body = {
      live_id: <?=s($live["id"])?>,
      csrf_token: `<?=$_SESSION['csrf_token']?>`,
      type: type,
      confirm: is_confirmed ? 1 : 0
    };
    if (type === "emoji") {
      body["count"] = parseInt(elemId("item_emoji_count").value);
      body["dir"] = elemId("item_emoji_dir").value;
      body["emoji"] = elemId("item_emoji_emoji").value;
      body["spin"] = elemId("item_emoji_spin").checked ? 1 : 0;
      body["big"] = elemId("item_emoji_big").checked ? 1 : 0;
    } else if (type === "knzk_kongyo") {
    } else {
      return null;
    }

    fetch('<?=u("api/client/item_buy")?>', {
      headers: {'content-type': 'application/x-www-form-urlencoded'},
      method: 'POST',
      credentials: 'include',
      body: buildQuery(body)
    }).then(function(response) {
      if (response.ok) {
        return response.json();
      } else {
        throw response;
      }
    }).then(function(json) {
      if (json["error"]) {
        alert(json["error"]);
        return null;
      }
      if (json["confirm"]) {
        if (confirm(json["point"] + "KP消費します。よろしいですか？")) {
          const p = $(".now_user_point");
          p.html(parseInt(p.html()) - json["point"]);
          item_buy(type, true);
        }
      }
      if (json["success"]) {
        $('#itemModal').modal('hide');
      }
    }).catch(function(error) {
      console.error(error);
      alert("内部エラーが発生しました");
    });
  }

  function userDropdown(obj, id, acct, url) {
    let is_local = false, local_icon = "";
    if (acct.match(/\(local\)/i)) {
      is_local = true;
      acct = acct.replace(" (local)", "");
      local_icon = `<i class="fas fa-home" title="ローカルコメント"></i> `;
    }

    $(".user-dropdown").remove();
    let html = "";
    if (url) html += `<a class="dropdown-item" href="${url}" target="_blank">ウェブページに移動</a>`;

    <?php if ($my["id"] === $live["user_id"]) : ?>
    html += `
<div class="dropdown-divider"></div>
<a class="dropdown-item text-danger" href="#" onclick="open_blocking_modal('${acct}');return false">ユーザーブロック</a>
`;
    if (id) html += `<a class="dropdown-item text-danger" href="#" onclick="comment_delete('${id}', '${acct}');return false">投稿を削除</a>`;
    <?php endif; ?>

    $(obj).popover({
      title: '',
      content: 'aaaa',
      placement: 'bottom',
      trigger: 'focus',
      template: `
<div class="dropdown-menu user-dropdown" tabindex="0" onclick="$('.user-dropdown').popover('dispose')">
  <h6 class="dropdown-header">${local_icon}@${acct}</h6>
  ${html}
  <div class="dropdown-divider"></div>
  <a class="dropdown-item text-muted" href="#" onclick="return false">閉じる</a>
</div>
`,
      html: true
    });
    $(obj).popover('show');
  }

  function liveSetting(mode) {
    // admin_panel_comment_display
    if (confirm('よろしいですか？')) {
      fetch('<?=u("api/client/live/setting")?>', {
        headers: {'content-type': 'application/x-www-form-urlencoded'},
        method: 'POST',
        credentials: 'include',
        body: buildQuery({
          type: mode,
          csrf_token: `<?=$_SESSION['csrf_token']?>`,
        })
      }).then(function(response) {
        if (response.ok) {
          return response.json();
        } else {
          throw response;
        }
      }).then(function(json) {
        if (json["error"]) {
          alert(json["error"]);
          return null;
        }
        if (json["success"]) {
          const elem = "#admin_panel_" + mode + "_display";
          $(elem).removeClass("off on");
          $(elem).addClass(json["result"] ? "on" : "off");
          if ($(elem).hasClass("btn-warning")) {
            $(elem).addClass("btn-info");
            $(elem).removeClass("btn-warning");
          } else {
            $(elem).addClass("btn-warning");
            $(elem).removeClass("btn-info");
          }
        }
      }).catch(function(error) {
        console.error(error);
        alert("内部エラーが発生しました");
      });
    }
  }

  function getNgs() {
    fetch('<?=u("api/client/ngs/get")?>', {
      headers: {'content-type': 'application/x-www-form-urlencoded'},
      method: 'POST',
      credentials: 'include',
      body: buildQuery({
        csrf_token: `<?=$_SESSION['csrf_token']?>`,
        live_id: <?=$live["id"]?>
      })
    }).then(function(response) {
      if (response.ok) {
        return response.json();
      } else {
        throw response;
      }
    }).then(function(json) {
      if (json["error"]) {
        alert(json["error"]);
        return null;
      }
      if (json["w"]) {
        config.nw = JSON.parse(atob(json["w"]));
      }
      if (json["u"]) {
        config.nu = JSON.parse(atob(json["u"]));
        if (config.nu.indexOf("#ME#") !== -1) location.reload();
      }
      if (json["p"]) {
        config.np = JSON.parse(atob(json["p"]));
      }
      if (json["donator"]) {
        for (let item of json["donator"]) {
          add_donator(item);
        }
      }
      loadComment();
    }).catch(function(error) {
      console.error(error);
      alert("内部エラーが発生しました");
    });
  }

  function open_listener_modal() {
    $("#listenerModal").modal("show");
    fetch('<?=u("api/client/live/listener")?>', {
      headers: {'content-type': 'application/x-www-form-urlencoded'},
      method: 'GET',
      credentials: 'include',
    }).then(function(response) {
      if (response.ok) {
        return response.json();
      } else {
        throw response;
      }
    }).then(function(json) {
      if (json["error"]) {
        alert(json["error"]);
        return null;
      }
      if (json) {
        let html = "";
        for (let item of json) {
          item.name = escapeHTML(item.name);
          html += `<tr><td><img src="${item.avatar_url}" width="25" height="25"/> <b>${item.name}</b> <small>@${item.acct}</small></td></tr>`;
        }
        elemId("listener_list").innerHTML = html;
      }
    }).catch(function(error) {
      console.error(error);
      alert("内部エラーが発生しました");
    });
  }

  /*
  window.onload = function () {
    getNgs();
    check_limit();
    watch(true);
  };
  */
</script>
<?php if ($my["id"] === $live["user_id"]) : ?>
  <script>

    function edit_live() {
      const name = elemId('edit_name').value;
      const desc = elemId('edit_desc').value;

      if (!name || !desc) {
        alert('エラー: タイトルか説明が入力されていません。');
        return;
      }

      fetch('<?=u("api/client/edit_live")?>', {
        headers: {
          'content-type': 'application/x-www-form-urlencoded',
        },
        method: 'POST',
        credentials: 'include',
        body: buildQuery({
          name: name,
          description: desc,
          csrf_token: `<?=$_SESSION['csrf_token']?>`
        })
      }).then(function(response) {
        if (response.ok) {
          return response.json();
        } else {
          throw response;
        }
      }).then(function(json) {
        if (json["error"]) {
          alert(json["error"]);
        } else {
          $('.live_info').removeClass('invisible');
          $('.live_edit').addClass('invisible');
          watch();
        }
      }).catch(function(error) {
        console.error(error);
        alert('送信中にエラーが発生しました。');
      });
    }

    function undo_edit_live() {
      elemId('edit_name').value = watch_data["name"];

      const parser = document.createElement('div');
      parser.innerHTML = watch_data["description"];
      elemId('edit_desc').value = parser.textContent;

      $('.live_info').removeClass('invisible');
      $('.live_edit').addClass('invisible');
    }

    function openEditLive() {
      $('.live_info').addClass('invisible');
      $('.live_edit').removeClass('invisible');
    }
  </script>
<?php endif; ?>
<?php endif; // sensitive ?>
</body>
</html>
