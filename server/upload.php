<?php

include('config.php');

if($_SERVER['REQUEST_METHOD'] === 'POST') {

  try {

    $now = time();

    // Update player info
    $data = json_decode(file_get_contents('php://input'), true);

    $db = new PDO("mysql:host={$config['db']['host']};dbname={$config['db']['db']}",
      $config['db']['user'], $config['db']['pass']);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $db->beginTransaction();

    // If this player exists in the current game with the given
    // password, do a "check-in" and consider us authenticated
    // TODO: make sure the game is active
    // TODO: check data timestamp against our timestamp for crazy discrepancies
    $st = $db->prepare("
      UPDATE player AS p
        INNER JOIN game AS g ON
          p.gameid = g.gameid
      SET p.lastcheckinon = :lastcheckinon
      WHERE p.playerid = :playerid
        AND p.playertoken = :playertoken
        AND g.gametoken = :gametoken
    ");

    $st->execute([
      ':lastcheckinon' => $now,
      ':playerid' => $data['playerid'],
      ':playertoken' => $data['playertoken'],
      ':gametoken' => $data['gametoken']
    ]);

    if(!$st->rowCount()) {
      $db->rollback();
      die("Bad player or token, or the game's not even active, dude.");
    }

    // TODO: Only do this stuff if we know the player is actually playing
    // the game (i.e., RAM address combinations make sense, known things like
    // playing through the opening demo screens are not set, etc.)
  
    // ITEM MANAGEMENT
    // Get items that this player hasn't already found
    // TODO: consider caching this or something since the base list never
    // changes over the course of a single game
    // TODO: can we just dump everything into the database via a query?
    // Guess we'd have to do bitmask comparisons on the server in that case
    // or do some kind of precomputing or whatnot
    $st = $db->prepare("
      SELECT il.sourceid, il.itemlocid, il.memoryoffset, il.bitmask
      FROM player AS p
        INNER JOIN gameitem AS gi ON
          p.gameid = gi.gameid
        INNER JOIN itemloc AS il ON
          gi.itemlocid = il.itemlocid
        LEFT JOIN playeritemloc AS pil ON
          p.playerid = pil.playerid AND il.itemlocid = pil.itemlocid
      WHERE p.playerid = :playerid
        AND pil.collectedon IS NULL
        AND il.memoryoffset IS NOT NULL
    ");
    $st->execute([':playerid' => $data['playerid']]);
    $items = $st->fetchAll();


    // Check to see if the item has been obtained (taking care to look at
    // memory related only to the currently-running game) and mark in the
    // database if so
    // TODO: this is very much shortcut-hacked at the moment; obviously
    // we'll want a more robust and non-hardcoded way of determining
    // which game is currently going
    $cursource = $data['vitals']['alttp']['7E0010'] < '100' ? 'alttp' : 'sm';

    $st = $db->prepare("
      INSERT INTO playeritemloc (playerid, itemlocid, collectedon)
      VALUES (:playerid, :itemlocid, :collectedon)
    ");
    
    foreach($items as $item) {

      if($item['sourceid'] == $cursource) {
        $memory = $data['items'][$item['sourceid']][$item['memoryoffset']];
        $mask = bindec($item['bitmask']);

        if($memory & $mask) {
          $st->execute([
            ':playerid' => $data['playerid'],
            ':itemlocid' => $item['itemlocid'],
            ':collectedon' => $now
          ]);
        }
      }
    }

    // VITAL MANAGEMENT

    // TODO: link up sources to games so we can be sure we only
    // use vitals relevant to games we're actually playing
    $st = $db->prepare("
      REPLACE INTO playervital (playerid, sourceid, vitalid, value, updatedon)
      SELECT :playerid, sourceid, vitalid, :value, :updatedon
      FROM vital
      WHERE sourceid = :sourceid AND memoryoffset = :memoryoffset
    ");

    foreach($data['vitals'] as $source => $vitals) {
      
      if($source == $cursource) {

        foreach($vitals as $memoryoffset => $value) {

          $st->execute([
            ':playerid' => $data['playerid'],
            ':sourceid' => $source,
            ':memoryoffset' => $memoryoffset,
            ':value' => $value,
            ':updatedon' => $now
          ]);
        }
      }
    }

    // Cool?
    $db->commit();
  }
  catch(Exception $e) {
    $db->rollback();
    var_export($e);
    die('Epic failure!');
  }
  
  http_response_code(204);
}

?>
