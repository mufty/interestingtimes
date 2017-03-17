<?php

define("DOMAIN", 'interestingtimes.enjin.com');

class EnjinRequest {
  public $jsonrpc = "2.0";
  public $id;
  public $params;
  public $method;
  private $defaultParams = array(
    'api_key' => 'API_KEY',
    'site_id' => '1311885' //site_id for interesting times
  );

  public function __construct($p, $m){
    $this->id = rand(0,1000000);
    if(array_key_exists('session_id', $p)){
      $this->params = array_merge($p, array('site_id' => $this->defaultParams['site_id']));
    } else {
      $this->params = array_merge($p, $this->defaultParams);
    }
    $this->method = $m;
  }
}

class EmbedRequest {
  public $type = 'rich';
  public $url;
  public $description;
  public $timestamp;

  public function __construct($post){
    $this->url = 'http://' . DOMAIN . '/home/m/' . $post->{'preset_id'} . '/article/' . $post->{'article_id'};
    $this->timestamp = $post->{'timestamp'};
    $this->description = $post->{'content'};
  }
}

class DiscordRequest {
  public $embeds;
  public $content;

  public function __construct($post, $embeds, $isForumPost){
    if($isForumPost){
      $mixin = strtolower($post->{'title'});
      $mixin = preg_replace('/\s+/', '-', $mixin);
      $url = 'http://' . DOMAIN . '/forum/m/' . $post->{'container_id'} . '/viewthread/' . $post->{'extra1'} . '-' . $mixin . '/post/last#last';

      $c = $post->{'username'} . ' replied to: ' . $post->{'title'} . ' -> ' . $post->{'description'} . '   >>> ' . $url;

      $this->content = $c;
    } else {
      if($embeds){
        $em = new EmbedRequest($post);
        $this->embeds = array($em);
      } else {
        $url = 'http://' . DOMAIN . '/home/m/' . $post->{'preset_id'} . '/article/' . $post->{'article_id'};
        $c = $post->{'title'} . ' ' . $url;
        $this->content = $c;
      }
    }
  }
}

class Sync {
  private $enjinRequest;
  private $maxSeconds = 600;
  private $enjinURL = "http://interestingtimes.enjin.com/api/v1/api.php";
  private $discordURL = "https://discordapp.com/api/webhooks/DISCORD_API";

  public function __construct() {
    //LATESTS POSTS
    $sessionId = file_get_contents('session');

    if($sessionId == NULL){
      $sessionId = $this->login();
      file_put_contents("session", $sessionId);
    } else {
      $wall = new EnjinRequest(array('session_id' => $sessionId), 'User.checkSession');
      $res = $this->send($this->enjinURL, $wall);
      $hasIdentity = $res->{'hasIdentity'};
      if(!$hasIdentity){
        $sessionId = $this->login();
        file_put_contents("session", $sessionId);
      }
    }

    $wall = new EnjinRequest(array('session_id' => $sessionId, 'filter' => 'forums'), 'Notifications.getList');
    $res = $this->send($this->enjinURL, $wall);
    $posts = $res->{'result'};
    $error = $res->{'error'};

    if($error != NULL){
      echo 'Notifications.getList<br/>';
      var_dump($res);
    }

    foreach ($posts as $post) {
      if($this->isSafeToPost($post)){
        $dr = new DiscordRequest($post, false, true);
        $res = $this->send($this->discordURL, $dr);
      }
    }

    //NEWS

    $wall = new EnjinRequest(array('limit' => 5), 'News.getLatest');
    $res = $this->send($this->enjinURL, $wall);
    $posts = $res->{'result'};
    $error = $res->{'error'};

    if($error != NULL){
      echo 'News.getLatest<br/>';
      var_dump($res);
    }

    foreach ($posts as $post) {
      if($this->isSafeToPost($post)){
        $dr = new DiscordRequest($post, false);
        $res = $this->send($this->discordURL, $dr);
      }
    }

  }

  private function login(){
    $this->enjinRequest = new EnjinRequest(array('email' => 'ITBOT_MAIL', 'password' => 'ITBOT_PASSWORD'), 'User.login');

    $res = $this->send($this->enjinURL, $this->enjinRequest);
    $resultObj = $res->{'result'};
    $error = $res->{'error'};
    $sessionId = $resultObj->{'session_id'};

    if($error != NULL){
      echo 'User.login<br/>';
      var_dump($res);
    }

    return $sessionId;
  }

  private function isSafeToPost($post){
    $timestamp = $post->{'timestamp'};
    if($timestamp == NULL){
      $timestamp = $post->{'time'};
      if($post->{'extra2'} != 'Interesting Times'){
        return false;
      }
    }

    $diff = $this->timeAgo($timestamp);
    if($diff >= $this->maxSeconds){
      return false;
    } else {
      return true;
    }
  }

  private function timeAgo($date){
    $today = new DateTime('now');
    $diff = $today->getTimestamp() - $date;
    return $diff;
  }

  private function send($url, $r){
    $options = array(
      'http' => array(
        'method' => 'POST',
        'content' => json_encode($r),
        'header' => array(
          'Content-Type' => 'application/json'
        )
      )
    );

    $context = stream_context_create($options);
    $result = file_get_contents($url, false, $context);

    if($result !== FALSE){
      return json_decode($result);
    }

  }
}

$enjin = new Sync();

 ?>
