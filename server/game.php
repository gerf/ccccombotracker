<?php

include('config.php');

$db = new PDO("mysql:host={$config['db']['host']};dbname={$config['db']['db']}",
  $config['db']['user'], $config['db']['pass']);
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

if($_SERVER['REQUEST_METHOD'] === 'POST') {

  // Create new game
  $spoilers = json_decode(file_get_contents('php://input'), true);

  if(!$spoilers['meta']) {
    die("What's up with your spoilers file? No meta is no fun.");
  }

  try {

    // Create a new game
    // TODO: kill game when failures occur later
    // TODO: link up sources to games
    // TODO: better random token generation (currently just 8 random numbers)
    $gametoken = str_pad(rand(0, pow(10, 8)-1), 8, '0', STR_PAD_LEFT);
    
    $st = $db->prepare("
      INSERT INTO game (gametoken)
      VALUES (:gametoken)
    ");
    
    $st->execute([
      ':gametoken' => $gametoken
    ]);
    
    $gameid = $db->lastInsertId();

    $db->beginTransaction();
  
    $st = $db->prepare("
      INSERT INTO gamemeta (gameid, metaid, value)
      VALUES (:gameid, :metaid, :value)
    ");

    // Load up game metadata
    foreach($spoilers['meta'] as $metaid => $value) {
      $st->execute([
        ':gameid' => $gameid,
        ':metaid' => $metaid,
        ':value' => $value
      ]);
    }

    // Load up the rest of the spoilers
    // TODO: restrict it to just the sources linked to the game - maybe
    // need ANOTHER map that links spoiler metadata to sources?
    $st = $db->prepare("
      SELECT sourceid, regionid
      FROM region
    ");
    
    $st->execute();
    
    $regions = $st->fetchAll(PDO::FETCH_ASSOC);

    $st = $db->prepare("
      INSERT INTO gameitem (gameid, itemlocid, itemid)
      VALUES (:gameid, :itemlocid, :itemid)
    ");
  
    foreach($regions as $row) {

      foreach($spoilers[$row['regionid']] as $itemlocid => $itemid) {
        $st->execute([
        ':gameid' => $gameid,
        ':itemlocid' => $itemlocid,
        ':itemid' => $itemid
        ]);
      }
    }

    $db->commit();
  }
  catch(Exception $e) {
    $db->rollback();
    die('Epic failure!');
  }

  http_response_code(201);

  $https = $_SERVER['HTTPS'] == 'on' ? 's' : null;
  header("Location: http{$https}://{$_SERVER['SERVER_NAME']}{$_SERVER['PHP_SELF']}?g=$gameid");
  header('Content-Type: application/json');

  $game['gameid'] = $gameid;
  $game['gametoken'] = $gametoken;

  echo json_encode($game);
}
elseif($_SERVER['REQUEST_METHOD'] === 'GET') {

  try {
    
    // Get individual game info, or return a list of all games
    $gameid = filter_var($_GET['g'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);

    if($gameid) {
      
      $st = $db->prepare("
        SELECT gameid, createdon, isactive
        FROM game
        WHERE gameid = :gameid
      ");
    
      $st->execute([':gameid' => $gameid]);
    
      $game = $st->fetch(PDO::FETCH_ASSOC);
      
      if($game) {
        
        // Some queries only need to be run once
        $init = filter_var($_GET['i'], FILTER_VALIDATE_BOOLEAN);
        
        if($init) {
          
          // ONE-TIME INITIALIZATION QUERIES
          
          // Item display details
          $st = $db->prepare("
            SELECT itemid, sourceid, icon, istracked
            FROM item
          ");
          
          $st->execute();
          $game['items'] = $st->fetchAll(PDO::FETCH_ASSOC|PDO::FETCH_UNIQUE);
          
          // Item locations
          // TODO: rooms and regions are left-joined for now because not all
          // mappings are complete; technically they should be inners
          $st = $db->prepare("
            SELECT gi.itemlocid, il.roomid, il.regionid, il.sourceid,
                16*(re.xpos + ro.xpos) + il.xpos AS xpos,
                16*(re.ypos + ro.ypos) + il.ypos AS ypos
            FROM gameitem AS gi
                INNER JOIN itemloc AS il ON
                  gi.itemlocid = il.itemlocid
                LEFT JOIN room AS ro ON
                  il.roomid = ro.roomid AND il.regionid = ro.regionid
                LEFT JOIN region AS re ON
                  ro.regionid = re.regionid
            WHERE gi.gameid = :gameid
          ");
          
          $st->execute([':gameid' => $gameid]);
          $game['itemlocations'] = $st->fetchAll(PDO::FETCH_ASSOC|PDO::FETCH_UNIQUE);
        }
        
        // QUERIES RUN ON EACH UPDATE
        
        // Items that have been collected
        $st = $db->prepare("
          SELECT pil.playerid, pil.itemlocid, gi.itemid
          FROM playeritemloc AS pil
            INNER JOIN player AS p ON
              pil.playerid = p.playerid AND p.gameid = :gameid
            INNER JOIN gameitem AS gi ON
              p.gameid = gi.gameid AND pil.itemlocid = gi.itemlocid
        ");
        
        $st->execute([':gameid' => $gameid]);
        $game['playeritems'] = $st->fetchAll(PDO::FETCH_ASSOC);
        
        // Map info: rooms and map offsets
        // TODO: ids with hex and text values are all whack
        $st = $db->prepare("
          SELECT re.sourceid,
            CAST(r.region_hex AS UNSIGNED) AS regionid,
            r.roomid,
            re.xpos + r.xpos AS xpos,
            re.ypos + r.ypos AS ypos
          FROM room r
            INNER JOIN region AS re ON
              r.sourceid = re.sourceid AND r.regionid = re.regionid
        ");
        
        $st->execute();
        $roomdata = $st->fetchAll(PDO::FETCH_ASSOC|PDO::FETCH_GROUP);
        
        foreach($roomdata as $source => $rooms) {
          foreach($rooms as $room) {
            $game['rooms'][$source][$room['regionid']][$room['roomid']] = [
              'xpos' => $room['xpos'],
              'ypos' => $room['ypos']
            ];
          }
        }
        
        // Players in this game
        $st = $db->prepare("
          SELECT p.playerid, p.displayname, p.lastcheckinon
          FROM game AS g
            INNER JOIN player AS p ON
              g.gameid = p.gameid
          WHERE g.gameid = :gameid
        ");
        
        $st->execute([':gameid' => $gameid]);
        $players = $st->fetchAll(PDO::FETCH_ASSOC|PDO::FETCH_UNIQUE);
        
        // Player vitals
        $st = $db->prepare("
          SELECT p.playerid, pv.sourceid, pv.vitalid, pv.value
          FROM game AS g
            INNER JOIN player AS p ON
              g.gameid = p.gameid
            INNER JOIN playervital AS pv ON
              p.playerid = pv.playerid
          WHERE g.gameid = :gameid
        ");
        
        $st->execute([':gameid' => $gameid]);
        $vitals = $st->fetchAll(PDO::FETCH_ASSOC|PDO::FETCH_GROUP);
        
        foreach($vitals as $player => $vitallist) {
          foreach($vitallist as $vital) {
            $players[$player]['vitals'][$vital['sourceid']][$vital['vitalid']] = $vital['value'];
          }
        }
        
        $game['players'] = $players;
        
        echo json_encode($game);
      
      }
      else {
        die("No game {$_GET['g']} bro.");
      }
    }
    else {
      // ALL THE GAEMZ
      $st = $db->prepare("
        SELECT g.gameid, g.createdon, g.isactive,
          COUNT(p.playerid) AS players
        FROM game AS g
          INNER JOIN player AS p ON
            g.gameid = p.gameid
        GROUP BY g.gameid, g.createdon, g.isactive
      ");
      $st->execute();
      $games = $st->fetchAll(PDO::FETCH_ASSOC);
      
      echo json_encode($games);
    }
  }
  catch(Exception $e) {
    var_export($e);
    die('Epic failure!');
  }
}
else {
  
  die('Burp.');
  
}

?>
