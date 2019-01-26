// Generate map
// TODO: make separate debug, player, item layers
var map = L.map('map', {
    crs: L.CRS.Simple,
    attributionControl: false
});

// Apply base map tiles
// TODO: add image-rendering:pixelated when zoom level is >15; I think the
// right place to do this is when a tileload event fires
var mapLayer = L.tileLayer.deepzoom('zebes/zebes_files/', {
  width: 16896, //.515625, 1056 16-pixel tiles
  height: 14336, //-.4375, 896 16-pixel tiles
  imageFormat: 'png',
  maxZoom: 17,
  minZoom: 10
}).addTo(map);

// Set up view options
map.fitBounds(mapLayer.options.bounds);
//map.setMaxBounds(mapLayer.options.bounds); // TODO: give some slop around edges?
map.setZoom(15);

/*
// Debug grid overlay
L.GridLayer.DebugGrid = L.GridLayer.extend({
  createTile: function(coords) {
    var tile = L.DomUtil.create('div');
    tile.innerHTML = [coords.x, coords.y, coords.z].join(', ');
    tile.style.outline = '1px solid green';
    tile.style.color = 'white';
    return tile;
  }
});

L.gridLayer.debugGrid = function(opts) {
  return new L.GridLayer.DebugGrid(opts);
};

map.addLayer(L.gridLayer.debugGrid());
*/

// Item layer
// TODO: on item acquisition, update automatically
L.GridLayer.ItemGrid = L.GridLayer.extend({
  createTile: function(coords) {

    var tile = L.DomUtil.create('div');

    // Only draw stuff when at zoom 13 or higher, otherwise you really
    // can't see anything anyway
    if(coords.z >= 13) {
      
      // TODO: make this not suck
      var zoom = 256 / (16*(2**(coords.z-15)));
      var izoom = 256 / (16*(2**(15-coords.z)));
      
      if(map.gameData) {
        // TODO: performance-wise it's probably better to just make a static
        // mapping to allow for fast lookups
        for(var x in map.gameData.itemlocations) {
          
          var i = map.gameData.itemlocations[x];
          
          var intile = i.xpos !== null &&
            coords.x*zoom <= i.xpos && (coords.x+1)*zoom >= i.xpos &&
            coords.y*zoom <= i.ypos && (coords.y+1)*zoom >= i.ypos;
          
          // Is this item in this tile?
          if(intile) {
            
            var item = L.DomUtil.create('div', 'item item-pulse' +
              (i.itemid ? ' item-' + [map.gameData.items[i.itemid].sourceid, map.gameData.items[i.itemid].icon].join('-') : ''), tile);
            item.style.left = (i.xpos-(coords.x*zoom))*izoom + 'px';
            item.style.top = (i.ypos-(coords.y*zoom))*izoom + 'px';
            item.style.transform = 'scale('+(2**(coords.z-15))+')';
            if(coords.z > 15) item.style.imageRendering = 'pixelated';
            
            // TODO: flags for players who have/haven't collected the item via a marker?
          }
        }
      }
    }
    
    return tile;
  }
});

L.gridLayer.itemGrid = function(opts) {
  return new L.GridLayer.ItemGrid(opts);
};

map.addLayer(L.gridLayer.itemGrid());


// Project a point to a 16/16 grid
map.projectGrid = function(point, zoom) {
  // Pixel = real coordinates * 2^(zoom-15)
  var p = this.project(point, zoom);
  p.x = Math.floor(p.x / (16*(2**(zoom-15))));
  p.y = Math.floor(p.y / (16*(2**(zoom-15))));
  return p;
};


// Player icons
var PlayerIcon = L.Icon.extend({
    options: {
        iconSize:     [48, 48],
        iconAnchor:   [24, 24],
        popupAnchor:  [0, -30]
    }
});


/*
var gridtrack = L.rectangle([[0,0], [0,0]], {
  color: "#ff7800",
  weight: 1
}).addTo(map);

// Highlight grid as mouse moves over
map.on('mousemove', function(e) {

  var zoom = e.sourceTarget.getZoom();
  var zoomfactor = 16*(2**(zoom-15));
  
  var gl = map.projectGrid(e.latlng, zoom);

  gridtrack.setBounds([map.unproject([gl.x*zoomfactor,gl.y*zoomfactor]), [map.unproject([(gl.x+1)*zoomfactor,(gl.y+1)*zoomfactor])]]);

  gridtrack.bindTooltip(gl.x + ' ' + gl.y);
});
*/


map.flyToPlayer = function(playerid) {
  map.flyTo(map.gameData.players[playerid].marker.getLatLng(), 14, { duration: 1 });
}

map.followPlayer = function(playerid) {
  if(playerid) {
    map.flyToPlayer(playerid);
  }
  
  map.gameData.following = playerid;
}

map.clearPlayerSelections = function() {
  var x = document.getElementsByClassName('player-selected');
  while(x.length) {
    x[0].classList.remove('player-selected');
  }
}

map.updateRealtime = function(interval, cycle) {
  fetch('game.php?g='+(new URLSearchParams(window.location.search).get('g'))+(interval?'&i=1':''))
  .then(function(response) {
    return response.json();
  })
  .then(function(data) {
    
    // If interval is set then it's the first run, otherwise it's not
    
    var zoom = 16*(2**(map.getZoom()-15));
    
    // Set up players, room, and UI data if first time
    if(interval) {
      
      map.gameData = {};
      map.gameData.items = data.items;
      map.gameData.itemlocations = data.itemlocations;
      map.gameData.rooms = data.rooms;
      map.gameData.players = data.players;
      
      map.gameData.ui = {};
      map.gameData.ui.maxcycle = cycle;
      map.gameData.ui.curcycle = cycle;
      map.gameData.ui.oncycle = null;
      map.gameData.ui.menu = document.getElementById('menu');
      map.gameData.ui.players = document.getElementById('players');
      
      for(var player in map.gameData.players) {
        
        var p = map.gameData.players[player];
        p.playerid = player;
        p.items = {};
        
        var icon = 'images/player-'+p.playerid+'.png';
        p.marker = L.marker([0, 0], {icon: new PlayerIcon({iconUrl: icon})});
        p.marker.visible = false;
        
        // SUPER UI HACKS LOL
        
        // Create UI icon for player
        var uiicon = document.createElement('img');
        uiicon.src = icon;
        uiicon.id = 'icon-'+player;
        uiicon.classList.add('player-icon');
        uiicon.title = p.displayname;
        uiicon.playerid = player;
        uiicon.onclick = function() {
          if(this.classList.contains('player-selected')) {
            // Stop all the following
            map.followPlayer(null);
            map.clearPlayerSelections();
          }
          else {
            map.followPlayer(this.playerid);
            map.clearPlayerSelections();
            this.classList.add('player-selected');
          }
        };
        map.gameData.ui.players.appendChild(uiicon);
        
      }
    }
    
    // Update inventory
    // Note that this doesn't allow for items to be lost live, but that doesn't
    // happen in these kinds of games anyway for now let's not worry about it
    for(var x in data.playeritems) {
      
      var pi = data.playeritems[x];
      
      map.gameData.items[pi.itemid].itemlocid = pi.itemlocid;
      map.gameData.itemlocations[pi.itemlocid].itemid = pi.itemid;
      map.gameData.players[pi.playerid].items[pi.itemlocid] = pi.itemid;
    }
    
    // Update vitals
    for(var player in map.gameData.players) {
      
      var p = map.gameData.players[player];
      p.vitals = data.players[player].vitals;
      
      // TODO: need a good mechanism to know which game the player is
      // currently "in," which seems to be identifiable by combining a
      // few vitals together. For now, we're just hacking it with the
      // ALTTP game mode, which seems to only ever be over 100 when in SM
      if(p.vitals) {
        
        if(p.vitals.alttp && p.vitals.alttp.gamemode < 100) {
          // ALTTP
          p.marker.visible = false;
          p.marker.remove();
        }
        else if(map.gameData.rooms.sm[p.vitals.sm.region][p.vitals.sm.room] !== undefined) {
          // SM
          if(!p.marker.visible) {
            p.marker.visible = true;
            p.marker.addTo(map);
          }
          
          // TODO: if this is an unmapped room (or some sort of error) either
          // toss up a notification or put the player into a "timeout zone"
          var newx = (16*map.gameData.rooms.sm[p.vitals.sm.region][p.vitals.sm.room].xpos+(p.vitals.sm.xpos/16))*zoom;
          var newy = (16*map.gameData.rooms.sm[p.vitals.sm.region][p.vitals.sm.room].ypos+(p.vitals.sm.ypos/16))*zoom;
          p.marker.setLatLng(map.unproject([newx, newy]));
          
          p.marker.bindPopup(JSON.stringify(Object.values(p.items).filter(function (value, index, self) { 
            return self.indexOf(value) === index && +map.gameData.items[value].istracked;
          })));
        }
      }
    }
    
    // Zoom to anyone we're currently following
    if(map.gameData.following) {
      map.flyToPlayer(map.gameData.following)
    }
    
    // Cycle for the viewers
    if(map.gameData.ui.maxcycle && !--map.gameData.ui.curcycle) {
      map.gameData.ui.curcycle = map.gameData.ui.maxcycle;
      
      var firstplayer = null;
      var cycleplayer = null;
      var newplayer = null;
      var stopnext = false;
      
      // TODO: make this hack not horrible (by making it not a hack)
      for(var player in map.gameData.players) {
        cycleplayer = map.gameData.players[player];
        
        
        if(cycleplayer.marker.visible) {
          
          if(stopnext) {
            newplayer = cycleplayer;
            break;
          }
          
          // No players are currently cycling, so just pick the first visible
          if(!map.gameData.ui.oncycle) {
            firstplayer = cycleplayer;
            newplayer = cycleplayer;
            break;
          }
          
          if(!firstplayer) firstplayer = cycleplayer;
          
          if(map.gameData.ui.oncycle.playerid == cycleplayer.playerid) stopnext = true;
        }
      }
      
      // If firstplayer is still null, that means no players are currently
      // in SM; just wait for the next cycle in that case
      if(firstplayer) {
        map.gameData.ui.oncycle = newplayer ? newplayer : firstplayer;
        document.getElementById('icon-'+map.gameData.ui.oncycle.playerid).click();
      }
    }

    // Keep going forever!
    if(interval) {
      setInterval(map.updateRealtime, interval);
    }
  })
  .catch(function(response) {
    console.log('Fetch error: ' + response);
  });
}

// And now do the first update
map.updateRealtime(2000, location.search.indexOf('cycle') >= 0 ? 30 : null);
