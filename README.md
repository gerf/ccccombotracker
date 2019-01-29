# ccccombotracker
Live pseudo-multiplayer map and inventory tracking for randomizer-type games.

[![ccccombotracker](/screenshot.jpg?raw=true "cccombotracker")]

Enables enables several players to cooperatively progress through the same randomization seed simultaneously and get live updates on fellow player locations, inventory, and the oh-so-important "which item is in which location?" question (which requires that any player obtain the item first... no spoilers!). All position and inventory data are directly extracted from the emulators in real-time so players do not need to do anything other than focus on playing the game. Online spectators can view the current game state via a cross-browser/platform slippy map (powered by the excellent [Leaflet.js](https://leafletjs.com)), zoom in on a particular player, check out which items have (or have not) been collected, or just let the map cycle through each player in sequence.

## Current Game Support
* [Super Metroid and A Link to the Past Item Randomizer](https://alttsm.speedga.me) (v10, v10.1; Super Metroid map only)

## Setup Instructions

### Server-side
* PHP with PDO database support
* MySQL database
* Leaflet.js (included)

Create a database for ccccombotracker in MySQL and run ```database/ccccombotracker.sql``` to create the core tables and pre-populate them with ALttP-SM randomizer mappings. Then, copy all files in the ```server``` folder to a web server and add the database connection details to ```server/config.php```. The server is now more or less ready to rock.

Create a new game by POSTing a spoiler JSON file to ```server/game.php```. Players must currently be added to a game manually via direct database interaction (this will be addressed in future updates so it's more streamlined).

### Client-side
* BizHawk
* cURL

After creating a game, each player should customize ```client/ccccomborandomizer.lua``` with their playerid, playertoken, and the gametoken (all generated server-side ahead of time; in the future this will also be more automated). Then, open the in BizHawk and run the script in the Lua Console to generate an "upload" file and periodically POST it to ```server/upload.php``` (e.g., via cURL and a batch script; example script included) to inform the server of the player's current whereabouts, vitals, and inventory.

Players and spectators can then point their browsers to ```server/index.html?g=###``` (where ### is the gameid) to watch the randomness unfold in sort-of-real-time.

Of course, prior to a stable 1.0.0 release, setup is going to be a bit of a hack job in and of itself!

## Changelog

### v0.1.0 (2019-01-26)
* Initial minimally-viable release used for a cooperative ALttP-SM Randomizer Showdown with friends.
* Provides automated game generation (via spoiler file upload), but adding players still requires some direct database access.
* Only supports the Super Metroid map, though ALttP items that were randomized into Zebes do appear.
* Users can pan/zoom around the map manually, follow a player by clicking on the corresponding player icon, or let the tracker automatically cycle through players in sequence (good for livestreaming to an audience). 
* Chock-full of duct tape and bubblegum!