local gametoken = 'CHANGEME'
local playerid = 0
local playertoken = 'CHANGEME'

-- TODO: use tables/objects instead of crazy stringcat

-- Creates/updates the data file to POST
function makepost()

  local file = io.open('upload-' .. playerid .. '.json', 'w')
  io.output(file)
  io.write(
  '{' ..
    '"gametoken": "' .. gametoken .. '",' ..
    '"playerid": ' .. playerid .. ',' ..
    '"playertoken": "' .. playertoken .. '",' ..
    '"timestamp": ' .. os.time() .. ',' ..
    '"items": {' ..
      '"sm": {' ..
        '"7ED870": "' .. mainmemory.readbyte(0x00D870) .. '",' .. -- Items
        '"7ED871": "' .. mainmemory.readbyte(0x00D871) .. '",' .. -- Items
        '"7ED872": "' .. mainmemory.readbyte(0x00D872) .. '",' .. -- Items
        '"7ED873": "' .. mainmemory.readbyte(0x00D873) .. '",' .. -- Items
        '"7ED874": "' .. mainmemory.readbyte(0x00D874) .. '",' .. -- Items
        '"7ED875": "' .. mainmemory.readbyte(0x00D875) .. '",' .. -- Items
        '"7ED876": "' .. mainmemory.readbyte(0x00D876) .. '",' .. -- Items
        '"7ED877": "' .. mainmemory.readbyte(0x00D877) .. '",' .. -- Items
        '"7ED878": "' .. mainmemory.readbyte(0x00D878) .. '",' .. -- Items
        '"7ED879": "' .. mainmemory.readbyte(0x00D879) .. '",' .. -- Items
        '"7ED87A": "' .. mainmemory.readbyte(0x00D87A) .. '",' .. -- Items
        '"7ED880": "' .. mainmemory.readbyte(0x00D880) .. '",' .. -- Items
        '"7ED881": "' .. mainmemory.readbyte(0x00D881) .. '",' .. -- Items
        '"7ED882": "' .. mainmemory.readbyte(0x00D882) .. '",' .. -- Items
        '"7ED883": "' .. mainmemory.readbyte(0x00D883) .. '",' .. -- Items
        '"7E09A8": "' .. mainmemory.readbyte(0x0009A8) .. '",' .. -- Beams
        '"7E09A4": "' .. mainmemory.readbyte(0x0009A4) .. '"'  .. -- Suits
      '},' ..
      '"alttp": {' ..
        '"7EF340": "' .. mainmemory.readbyte(0x00F340) .. '",' ..
        '"7EF344": "' .. mainmemory.readbyte(0x00F344) .. '",' ..
        '"7EF348": "' .. mainmemory.readbyte(0x00F348) .. '",' ..
        '"7EF34C": "' .. mainmemory.readbyte(0x00F34C) .. '",' ..
        '"7EF350": "' .. mainmemory.readbyte(0x00F350) .. '",' ..
        '"7EF354": "' .. mainmemory.readbyte(0x00F354) .. '",' ..
        '"7EF358": "' .. mainmemory.readbyte(0x00F358) .. '",' ..
        '"7EF35C": "' .. mainmemory.readbyte(0x00F35C) .. '"'  ..
      '}' ..
    '},' ..
    '"vitals": {' ..
      '"sm": {' ..
        '"7E079F": "' .. mainmemory.readbyte(0x00079F)    .. '",' .. -- Region ID
        '"7E079D": "' .. mainmemory.readbyte(0x00079D)    .. '",' .. -- Room ID
        '"7E0AF6": "' .. mainmemory.read_u16_le(0x000AF6) .. '",' .. -- X position (2 bytes)
        '"7E0AFA": "' .. mainmemory.read_u16_le(0x000AFA) .. '",' .. -- Y position (2 bytes)
        '"7E09C2": "' .. mainmemory.read_u16_le(0x0009C2) .. '",' .. -- Energy (2 bytes)
        '"7E09C8": "' .. mainmemory.readbyte(0x0009C8)    .. '",' .. -- Max missiles (meta?)
        '"7E09CC": "' .. mainmemory.readbyte(0x0009CC)    .. '",' .. -- Max super missiles (meta?)
        '"7E09D0": "' .. mainmemory.readbyte(0x0009D0)    .. '"'  .. -- Max power missiles (meta?)
      '},' ..
      '"alttp": {' ..
        '"7E0010": "' .. mainmemory.readbyte(0x000010)    .. '"' .. -- 0x97 when in SM, 0x00-0x1B when in ALTTP
      '}' ..
    '}' ..
  '}')

  io.close(file)

end

local i = 0

-- Generate the file once a second (60 frames)
while true do

  if i == 60 then
    makepost()
    i = 0
  end

  i = i+1

  emu.frameadvance()

end